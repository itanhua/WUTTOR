<?php
/**
 * 积分服务：Token（流通主积分）+ Byte（成长值）双体系
 *
 * - earn()  获取积分：唯一键幂等 + 行锁单事务 + 原子 UPDATE + 重算等级
 * - spend() 消耗积分：余额校验 + 行锁单事务 + 流水
 * - 所有数值走 point_rules 配置表（代码零硬编码，配置缺失用兜底常量）
 *
 * 字段约定：
 *   users.token 主流通积分（可获取/消耗/兑换，永久不清零，最低 0）
 *   users.byte  成长值（仅获取不可消耗，决定 9 级阶梯）
 *   users.level 当前等级（1-9），只随 byte 变化
 */
class PointService
{
    const CURRENCY_TOKEN = 'token';
    const CURRENCY_BYTE  = 'byte';

    // 9 级阶梯 Byte 阈值（兜底；user_levels 表有数据时优先从表读取）
    const DEFAULT_LEVEL_THRESHOLDS = [0, 100, 300, 700, 1500, 3000, 6000, 12000, 25000];
    const LEVEL_NAMES = ['学徒', '新手', '进阶', '活跃', '资深', '精英', '专家', '大师', '传奇'];

    // 等级加成来源（仅互动/发帖/签到类 earn 享受等级加成；具体加成倍数由 user_levels.bonus_factor 决定）
    const BONUS_SOURCES = ['post_create', 'comment_create', 'reply_create', 'daily_sign', 'post_liked'];

    /**
     * 等级缓存（进程级 static；表数据变更时调 self::clearLevelCache()）。
     * 结构：[['level'=>1,'name'=>'学徒','byte_required'=>0,'bonus_factor'=>1.0,'privilege_text'=>''], ...]
     * @var array|null
     */
    private static $levelCache = null;

    /**
     * 从 user_levels 表读取所有等级（enabled=1；按 level 升序）。
     * 表不存在 / 无数据 / 列缺失时回退到 DEFAULT_LEVEL_THRESHOLDS + LEVEL_NAMES。
     * 每行包含 level / name / byte_required / bonus_factor / privilege_text。
     */
    public static function loadLevels()
    {
        if (self::$levelCache !== null) return self::$levelCache;
        try {
            $rows = Model::table('user_levels')->where('enabled', 1)->orderBy('level')->get();
        } catch (\Throwable $e) {
            $rows = [];
        }
        $out = [];
        if (is_array($rows) && count($rows) > 0) {
            foreach ($rows as $r) {
                $out[] = [
                    'level'          => (int)$r['level'],
                    'name'           => (string)($r['name'] ?? ''),
                    'byte_required'  => (int)($r['byte_required'] ?? 0),
                    'bonus_factor'   => (float)($r['bonus_factor'] ?? 1.0),
                    'privilege_text' => (string)($r['privilege_text'] ?? ''),
                ];
            }
        }
        // 表无数据：兜底到 DEFAULT_LEVEL_THRESHOLDS + LEVEL_NAMES
        if (empty($out)) {
            foreach (self::DEFAULT_LEVEL_THRESHOLDS as $i => $thr) {
                $out[] = [
                    'level'          => $i + 1,
                    'name'           => self::LEVEL_NAMES[$i] ?? ('Lv.' . ($i + 1)),
                    'byte_required'  => (int)$thr,
                    'bonus_factor'   => 1.0,
                    'privilege_text' => '',
                ];
            }
        }
        self::$levelCache = $out;
        return $out;
    }

    /**
     * 清空等级缓存（后台保存等级后调用）
     */
    public static function clearLevelCache()
    {
        self::$levelCache = null;
    }

    /**
     * 币种注册表缓存（进程级 static；表数据变更时调 self::clearCurrencyCache()）。
     * 结构：[{'code'=>,'name'=>,'symbol'=>,'icon'=>,'type'=>,'enabled'=>,'is_system'=>,'sort_order'=>,...}, ...]
     * @var array|null
     */
    private static $currencyCache = null;

    /**
     * 读取全部币种（currencies 表；表不存在 / 无数据时回退内置 token/byte 兜底）。
     * 每次调用返回同一引用，外部按需按 enabled 过滤。
     */
    public static function loadCurrencies()
    {
        if (self::$currencyCache !== null) return self::$currencyCache;
        try {
            $rows = Model::table('currencies')->orderBy('sort_order')->orderBy('id')->get();
        } catch (\Throwable $e) {
            $rows = [];
        }
        if (empty($rows)) {
            $rows = [
                ['code' => 'token', 'name' => 'Token', 'symbol' => 'T', 'icon' => 'T', 'type' => 'consumable', 'enabled' => 1, 'is_system' => 1, 'sort_order' => 1, 'description' => '主流通积分'],
                ['code' => 'byte',  'name' => 'Byte',  'symbol' => 'B', 'icon' => 'B', 'type' => 'growth',     'enabled' => 1, 'is_system' => 1, 'sort_order' => 2, 'description' => '成长值（决定等级）'],
            ];
        }
        self::$currencyCache = $rows;
        return $rows;
    }

    /**
     * 全部币种（默认全部；enabledOnly=true 仅返回启用项；spendableOnly=true 仅返回可消耗项（type='consumable'））。
     * 用于前台付费主题、奖励、悬赏等需要扣费的场景：用户只能选用可消耗币种（如积分 token），不允许混用成长值 byte 之类。
     */
    public static function currencies($enabledOnly = false, $spendableOnly = false)
    {
        $all = self::loadCurrencies();
        if (!$enabledOnly && !$spendableOnly) return $all;
        return array_values(array_filter($all, function ($c) use ($enabledOnly, $spendableOnly) {
            if ($enabledOnly && (int)$c['enabled'] !== 1) return false;
            if ($spendableOnly && ($c['type'] ?? 'normal') !== 'consumable') return false;
            return true;
        }));
    }

    /**
     * 币种 code 是否合法（存在于 currencies 表，或内置 token/byte）
     */
    public static function currencyExists($code)
    {
        if ($code === self::CURRENCY_TOKEN || $code === self::CURRENCY_BYTE) return true;
        foreach (self::loadCurrencies() as $c) {
            if ($c['code'] === $code) return true;
        }
        return false;
    }

    /**
     * 返回币种的「完整展示配置」（code + 显示名 + 图标 SVG 文本/字符 + 配色 class）。
     * 找不到时返回 token 兜底。模板里直接 <?= ...['icon_html'] ?> 渲染即可。
     *
     * icon_html 优先级：
     *   1. 后台手上传的 icon_svg（完整 <svg>...</svg> 文本，已 sanitize）
     *   2. icon 字段（单字符 fallback）
     *   3. 名称首字符 fallback
     */
    public static function currencyIconHtml($code)
    {
        $cur = null;
        foreach (self::loadCurrencies() as $c) {
            if ($c['code'] === $code) { $cur = $c; break; }
        }
        if (!$cur) {
            return '<span class="cur-ico-letter">' . e(mb_substr((string)$code, 0, 1)) . '</span>';
        }
        if (!empty($cur['icon_svg'])) {
            // 已由后端保存接口 strip <script> + 强制 viewBox，再次防御只取 <svg>...</svg>
            if (preg_match('#<svg\b[^>]*>.*?</svg>#si', $cur['icon_svg'], $m)) {
                return $m[0];
            }
        }
        $fallback = $cur['icon'] !== '' ? $cur['icon'] : mb_substr((string)$cur['name'], 0, 1);
        return '<span class="cur-ico-letter">' . e((string)$fallback) . '</span>';
    }

    /**
     * 自定义币种（启用、且非内置 token/byte），用于 earnAction 自动发放
     */
    public static function customCurrencies()
    {
        $out = [];
        foreach (self::currencies(true) as $c) {
            if ($c['code'] === self::CURRENCY_TOKEN || $c['code'] === self::CURRENCY_BYTE) continue;
            $out[] = $c;
        }
        return $out;
    }

    /**
     * 币种显示名（Token/Byte 内置映射，其余取 currencies.name）
     */
    public static function currencyLabel($code)
    {
        // 优先从 currencies 表读取（含内置 token/byte 改名后的名称），保证后台改名前台实时联动
        foreach (self::loadCurrencies() as $c) {
            if ($c['code'] === $code) return $c['name'];
        }
        // 表无数据时回退内置默认值（与 loadCurrencies 的兜底一致）
        if ($code === self::CURRENCY_TOKEN) return 'Token';
        if ($code === self::CURRENCY_BYTE) return 'Byte';
        return $code;
    }

    /**
     * 清空币种缓存（后台保存币种后调用）
     */
    public static function clearCurrencyCache()
    {
        self::$currencyCache = null;
    }

    /**
     * 消耗类型中文名映射（point_rules 只管 earn 规则，spend source 不在其中）。
     * 新增消耗类型时在此补一行 code => '中文名'；流水 source 列即按此显示。
     * 用户点「我的积分 → 消耗记录」时，UserController 会把本表合并进 ruleNames。
     */
    private static $spendSourceLabels = [
        'reward'        => '打赏',
        'self_pin'      => '自助置顶',
        'cert_submit'   => '提交认证申请',
        'bounty_stake'  => '悬赏质押',
        'pay_buy'       => '付费查看',
        'lottery_join'  => '参与抽奖',
        'lottery_stake' => '抽奖质押',
    ];

    public static function spendSourceLabel($code)
    {
        return self::$spendSourceLabels[$code] ?? $code;
    }

    public static function spendSourceLabels()
    {
        return self::$spendSourceLabels;
    }

    /**
     * 主动发放（grant）来源中文名映射（point_rules 只管 earn 规则，grant 直发的 source 不在其中）。
     * 新增主动发放类型时在此补一行 code => '中文名'；流水 source 列即按此显示。
     * 用户点「我的积分 → 获取记录」时，UserController 会把本表合并进 ruleNames。
     */
    private static $grantSourceLabels = [
        'bounty_refund'  => '悬赏返还',
        'bounty_reward'  => '悬赏采纳奖励',
        'pay_income'     => '付费入账',
        'lottery_win'    => '抽奖中奖',
        'lottery_refund' => '抽奖未发完返还',
    ];

    public static function grantSourceLabel($code)
    {
        return self::$grantSourceLabels[$code] ?? $code;
    }

    public static function grantSourceLabels()
    {
        return self::$grantSourceLabels;
    }

    /**
     * earn 派来源中文名映射（PointService 直接写 points_log、不经过 point_rules 表的特殊来源，
     * 如被打赏入账。point_rules 只覆盖「默认行为奖励」，不覆盖这些。
     * 新增时在此补一行 code => '中文名'；流水 source 列即按此显示。
     * 用户点「我的积分 → 获取记录」时，UserController 会把本表合并进 ruleNames。
     */
    private static $earnSourceLabels = [
        'rewarded' => '获得打赏',
    ];

    public static function earnSourceLabel($code)
    {
        return self::$earnSourceLabels[$code] ?? $code;
    }

    public static function earnSourceLabels()
    {
        return self::$earnSourceLabels;
    }

    /**
     * 读取用户某币种余额：内置 token/byte 走 users 列，自定义走 user_balances 表。
     */
    public static function balanceOf($userId, $code)
    {
        $userId = (int)$userId;
        if ($code === self::CURRENCY_TOKEN || $code === self::CURRENCY_BYTE) {
            $u = Model::table('users')->select($code)->where('id', $userId)->first();
            return $u ? (int)($u[$code] ?? 0) : 0;
        }
        $row = Model::table('user_balances')->where('user_id', $userId)->where('currency', $code)->first();
        return $row ? (int)($row['balance'] ?? 0) : 0;
    }

    /**
     * 用户在「我的积分」页展示的所有启用币种余额列表
     */
    public static function userBalances($userId)
    {
        $userId = (int)$userId;
        $out = [];
        foreach (self::currencies(true) as $c) {
            $out[] = [
                'code'     => $c['code'],
                'name'     => $c['name'],
                'symbol'   => $c['symbol'] ?? '',
                'icon'     => $c['icon'] ?? '',
                'icon_svg' => $c['icon_svg'] ?? '',
                'type'     => $c['type'] ?? 'consumable',
                'balance'  => self::balanceOf($userId, $c['code']),
            ];
        }
        return $out;
    }

    /**
     * 在事务内读取某币种当前余额（已对相关行加锁）。
     * 内置币种直接读 users 行；自定义币种对 user_balances 行加锁读取。
     */
    private static function readBalanceLocked($pdo, $userId, $code, $userRow)
    {
        if ($code === self::CURRENCY_TOKEN || $code === self::CURRENCY_BYTE) {
            return (int)($userRow[$code] ?? 0);
        }
        $b = $pdo->prepare("SELECT balance FROM user_balances WHERE user_id = ? AND currency = ? FOR UPDATE");
        $b->execute([(int)$userId, $code]);
        $br = $b->fetch(PDO::FETCH_ASSOC);
        return $br ? (int)$br['balance'] : 0;
    }

    /**
     * 在事务内写入某币种最新余额（路由到正确的存储）。
     * 内置币种更新 users 列；自定义币种 upsert 到 user_balances 表。
     */
    private static function applyBalance($pdo, $userId, $code, $balanceAfter)
    {
        $userId = (int)$userId;
        $balanceAfter = (int)$balanceAfter;
        if ($code === self::CURRENCY_TOKEN || $code === self::CURRENCY_BYTE) {
            $pdo->prepare("UPDATE users SET {$code} = ? WHERE id = ?")->execute([$balanceAfter, $userId]);
            return;
        }
        $now = date('Y-m-d H:i:s');
        $pdo->prepare("INSERT INTO user_balances (user_id, currency, balance, created_at, updated_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE balance = VALUES(balance), updated_at = ?")
            ->execute([$userId, $code, $balanceAfter, $now, $now]);
    }

    /**
     * 读取规则配置（point_rules 表），不存在返回 null
     */
    public static function rule($code)
    {
        $row = Model::table('point_rules')->where('code', $code)->first();
        return $row ?: null;
    }

    /**
     * 风控配置（settings 表，缺失用兜底默认值）
     *  - newbie_hours  新注册账号风控时长（小时）：该时间内单日获取上限减半
     *  - newbie_factor 新号积分额度系数（0.5=减半）
     *  - burst_count   高频熔断阈值：同一来源在 burst_window 秒内达到该次数即暂停当日获取
     *  - burst_window  高频熔断时间窗（秒）
     */
    public static function riskConfig()
    {
        return [
            'newbie_hours'  => (int)setting('risk_newbie_hours', 24),
            'newbie_factor' => (float)setting('risk_newbie_factor', 0.5),
            'burst_count'   => (int)setting('risk_burst_count', 10),
            'burst_window'  => (int)setting('risk_burst_window', 60),
        ];
    }

    /**
     * 获取前置风控闸门（earn 入口调用）：封禁 / 新号单日上限减半 / 单日获取上限 / 高频熔断
     *
     * 设计要点：
     *  - 对缺失列（升级前）做 try/catch 容错，保证旧库未跑 upgrade 时 earn 不崩溃
     *  - 被拦截返回 blocked=true；suspended=true 表示已写入封禁（高频熔断）
     *  - daily_cap=0 的规则视为「无单日上限」（后台可逐规则配置为 0 实现无限增长）
     * @return array ['blocked'=>bool,'reason'=>string,'suspended'=>bool]
     */
    public static function earnGate($userId, $source)
    {
        $userId = (int)$userId;
        $cfg = self::riskConfig();
        $pdo = Database::pdo();

        // 1) 封禁校验（points_banned_until 未到期 → 暂停当日积分获取权限）
        $row = null;
        try {
            $st = $pdo->prepare("SELECT created_at, points_banned_until FROM users WHERE id = ?");
            $st->execute([$userId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // 列尚不存在（旧库未升级）→ 视为无限制，继续
            $row = ['created_at' => null, 'points_banned_until' => null];
        }
        if (!$row) return ['blocked' => true, 'reason' => 'no_user', 'suspended' => false];

        $now = date('Y-m-d H:i:s');
        if (!empty($row['points_banned_until']) && $row['points_banned_until'] > $now) {
            return ['blocked' => true, 'reason' => 'suspended', 'suspended' => true];
        }

        // 2) 新号判定（注册未满 newbie_hours 小时）
        $isNewbie = false;
        if (!empty($row['created_at'])) {
            $ageHours = (time() - strtotime($row['created_at'])) / 3600;
            if ($ageHours < $cfg['newbie_hours']) $isNewbie = true;
        }

        // 3) 单日获取上限（仅 earn 规则带 daily_cap；新号额度减半）
        $rule = self::rule($source);
        $dailyCap = ($rule && isset($rule['daily_cap'])) ? (int)$rule['daily_cap'] : 0;
        if ($dailyCap > 0) {
            $effectiveCap = $dailyCap;
            if ($isNewbie) {
                $effectiveCap = max(1, (int)ceil($dailyCap * $cfg['newbie_factor']));
            }
            $today = date('Y-m-d 00:00:00');
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM points_log WHERE user_id = ? AND source = ? AND type = 'earn' AND created_at >= ?");
            $cnt->execute([$userId, $source, $today]);
            if ((int)$cnt->fetchColumn() >= $effectiveCap) {
                return ['blocked' => true, 'reason' => 'daily_cap', 'suspended' => false];
            }
        }

        // 4) 高频熔断：短时间内同一来源次数过多 → 封禁至当日 23:59:59
        if ($cfg['burst_count'] > 0 && $cfg['burst_window'] > 0) {
            $since = date('Y-m-d H:i:s', time() - $cfg['burst_window']);
            $b = $pdo->prepare("SELECT COUNT(*) FROM points_log WHERE user_id = ? AND source = ? AND type = 'earn' AND created_at >= ?");
            $b->execute([$userId, $source, $since]);
            if ((int)$b->fetchColumn() >= $cfg['burst_count']) {
                try {
                    $end = date('Y-m-d 23:59:59');
                    $pdo->prepare("UPDATE users SET points_banned_until = ? WHERE id = ?")->execute([$end, $userId]);
                } catch (PDOException $e) { /* 列不存在则跳过封禁，仅拦截本次 */ }
                return ['blocked' => true, 'reason' => 'burst', 'suspended' => true];
            }
        }

        return ['blocked' => false, 'reason' => '', 'suspended' => false];
    }

    /**
     * 等级阈值数组（优先 user_levels 表，其次 DEFAULT_LEVEL_THRESHOLDS）
     */
    public static function levelThresholds()
    {
        $lv = self::loadLevels();
        $arr = [];
        foreach ($lv as $l) $arr[] = (int)$l['byte_required'];
        return $arr ?: self::DEFAULT_LEVEL_THRESHOLDS;
    }

    /**
     * 根据 byte 计算等级（基于加载的等级阶梯；不限制 maxLevel——后台可任意加 9 以上的等级）
     */
    public static function computeLevel($byte)
    {
        $byte = (int)$byte;
        $lv = self::loadLevels();
        if (empty($lv)) {
            $th = self::DEFAULT_LEVEL_THRESHOLDS;
            $level = 1;
            foreach ($th as $i => $min) {
                if ($byte >= (int)$min) $level = $i + 1;
            }
            return $level;
        }
        $level = 1;
        foreach ($lv as $i => $row) {
            if ($byte >= (int)$row['byte_required']) $level = (int)$row['level'];
        }
        return $level;
    }

    /**
     * 当前用户真实等级（按 byte 反算；不受 users.level 字段滞后影响）
     */
    public static function userLevel($userId)
    {
        $u = Model::table('users')->select('level', 'byte')->where('id', (int)$userId)->first();
        if (!$u) return 1;
        // 优先用 byte 实时反算等级（这样后台新增等级后无需触发积分变动即可联动展示）
        $byte = (int)($u['byte'] ?? 0);
        if ($byte > 0) return self::computeLevel($byte);
        return (int)($u['level'] ?? 1);
    }

    /**
     * 等级加成倍数：来源在 BONUS_SOURCES 才享受；加成系数由 user_levels.bonus_factor 决定。
     * 表无数据时一律 1.0（保持历史行为稳定）。
     */
    private static function bonusMultiplier($userId, $source)
    {
        if (!in_array($source, self::BONUS_SOURCES)) return 1.0;
        $lv = self::userLevel($userId);
        $levels = self::loadLevels();
        if (empty($levels)) return 1.0;
        // 找到与 users.level 匹配的那一行
        foreach ($levels as $row) {
            if ((int)$row['level'] === $lv) {
                $factor = (float)$row['bonus_factor'];
                // 防御：factor <= 1 视为无加成（保留小数）
                return $factor > 0 ? $factor : 1.0;
            }
        }
        return 1.0;
    }

    /**
     * 解析基础发放值：
     *  - 若规则配置了有效随机区间（amount_min>0 且 amount_max>=amount_min），则在该区间内随机取整；
     *  - 否则回退到固定 amount（老规则零改动，默认行为不变）。
     * 兼容未升级的库（列不存在 → $rule 无该键 → ??0 → 走固定值），不崩。
     * @return int
     */
    private static function resolveBaseAmount($rule)
    {
        $min = (int)($rule['amount_min'] ?? 0);
        $max = (int)($rule['amount_max'] ?? 0);
        if ($max > 0 && $min > 0 && $max >= $min) {
            // 加密安全随机；极旧 PHP 无 random_int 时回退 mt_rand
            return function_exists('random_int') ? random_int($min, $max) : mt_rand($min, $max);
        }
        return (int)($rule['amount'] ?? 0);
    }

    /**
     * 风控 / 发奖失败「内部 reason code」→ 前台友好中文。
     * ⚠️ 严禁把 code 或原始 SQL 直接透传给前端，统一经此函数转义。
     * 未知 code（含 PDO 异常原文）一律走兜底中文，不回显技术细节。
     * @param string $code
     * @return string
     */
    public static function reasonText($code)
    {
        static $map = [
            // earnGate 风控闸门
            'daily_cap'   => '已达积分获取单日上限，请明日再来',
            'suspended'   => '今日积分获取已被风控暂停（操作过于频繁），请明日再来',
            'burst'       => '操作过于频繁，已临时限制积分获取，请稍后再试',
            'no_user'     => '用户不存在或已注销',
            // earn / earnAction
            'no_rule'     => '该动作未配置积分规则',
            'bad_currency'=> '积分币种不存在或已删除',
            'zero_amount' => '该动作的积分规则金额为 0，未发放',
            'duplicate'   => '该动作已发放过积分，不可重复领取',
            // spend
            'insufficient'=> '余额不足',
            // reward 打赏
            'min'         => '金额低于最小限制',
            'self'        => '不能对自己操作',
            'max_amount'  => '金额超过后台设置的上限',
            'no_receiver' => '接收方不存在',
            'daily_limit' => '已达今日次数上限',
            'too_fast'    => '操作过于频繁，请稍后再试',
            'db_error'    => '系统繁忙，请稍后再试',
            // 自助置顶 / 通用
            'hours'       => '时长需为 1-72 小时',
            'unknown'     => '本次未发放积分',
        ];
        if (isset($map[$code])) return $map[$code];
        return '积分操作未完成，请稍后再试';
    }

    /**
     * 获取积分（幂等）
     * @return array ['ok'=>bool, 'amount'=>int, 'balance'=>int, 'level'=>int, 'bonus'=>float, 'reason'=>string]
     */
    public static function earn($userId, $source, $currency, $sourceId = 0, $remark = '')
    {
        $userId = (int)$userId;
        $currency = (string)$currency;

        $rule = self::rule($source);
        if (!$rule || $rule['type'] !== 'earn' || !(int)$rule['enabled']) {
            return ['ok' => false, 'reason' => 'no_rule'];
        }
        $baseAmount = self::resolveBaseAmount($rule);

        $ruleCurrency = isset($rule['currency']) ? (string)$rule['currency'] : '';
        if ($ruleCurrency !== '' && self::currencyExists($ruleCurrency)) {
            $currency = $ruleCurrency;
        }
        if (!self::currencyExists($currency)) return ['ok' => false, 'reason' => 'bad_currency'];
        if ($baseAmount <= 0) return ['ok' => false, 'reason' => 'zero_amount'];

        // 风控闸门：封禁 / 单日上限 / 高频熔断（命中则本次不计分，不影响主业务流程）
        $gate = self::earnGate($userId, $source);
        if ($gate['blocked']) {
            return ['ok' => false, 'reason' => $gate['reason']];
        }

        $bonus = self::bonusMultiplier($userId, $source);
        $amount = (int)round($baseAmount * $bonus);

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            // 行锁当前用户，避免并发竞态导致重复加分
            $u = $pdo->prepare("SELECT id, token, byte, level FROM users WHERE id = ? FOR UPDATE");
            $u->execute([$userId]);
            $row = $u->fetch(PDO::FETCH_ASSOC);
            if (!$row) { $pdo->rollBack(); return ['ok' => false, 'reason' => 'no_user']; }

            // 幂等：同一 (user,currency,source,source_id,type) 已加过则跳过（防重试/重复请求资损）
            $chk = $pdo->prepare("SELECT id FROM points_log WHERE user_id = ? AND currency = ? AND source = ? AND source_id = ? AND type = 'earn'");
            $chk->execute([$userId, $currency, $source, $sourceId]);
            if ($chk->fetchColumn()) { $pdo->rollBack(); return ['ok' => false, 'reason' => 'duplicate']; }

            // 读取当前币种余额（内置走 users 列，自定义走 user_balances；已对相关行加锁）
            $balance = self::readBalanceLocked($pdo, $userId, $currency, $row);

            $balanceAfter = $balance + $amount;
            $ins = $pdo->prepare("INSERT INTO points_log (user_id, currency, type, source, source_id, amount, balance_after, remark, created_at) VALUES (?, ?, 'earn', ?, ?, ?, ?, ?, NOW())");
            $ins->execute([$userId, $currency, $source, $sourceId, $amount, $balanceAfter, $remark]);

            // 写入最新余额（路由到正确的存储）
            self::applyBalance($pdo, $userId, $currency, $balanceAfter);

            $newLevel = $row['level'];
            if ($currency === self::CURRENCY_BYTE) {
                $newLevel = self::computeLevel($balanceAfter);
                if ($newLevel != $row['level']) {
                    $pdo->prepare("UPDATE users SET level = ? WHERE id = ?")->execute([$newLevel, $userId]);
                }
            }
            $pdo->commit();
            return ['ok' => true, 'amount' => $amount, 'balance' => $balanceAfter, 'level' => $newLevel, 'bonus' => $bonus];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (stripos($e->getMessage(), 'Duplicate') !== false) return ['ok' => false, 'reason' => 'duplicate'];
            // 严禁把 PDO 原文当 reason 透传；统一给 db_error 码 + detail（仅写日志）
            return ['ok' => false, 'reason' => 'db_error', 'detail' => $e->getMessage()];
        }
    }

    /**
     * 直接发放积分（绕过 point_rules 规则表与 earnGate 风控闸门）。
     * 用于充值（卡密 / 微信支付宝）、后台直充、运营补偿等「明确要给用户加币」的场景。
     * 仍保持幂等（同一 user+currency+source+source_id 只发一次）与行锁并发安全，并正确写入 points_log / 余额 / 等级。
     *
     * @param int    $userId
     * @param string $currency 币种 code（token/byte/自定义）
     * @param int    $amount   本次发放数量（整数，<=0 直接失败）
     * @param string $source   流水来源标识（如 recharge_card / recharge_order / admin_grant）
     * @param int    $sourceId 来源主键（卡密 id / 订单 id），用于幂等去重
     * @param string $remark
     * @return array ['ok'=>bool,'amount'=>int,'balance'=>int,'level'=>int,'reason'=>string]
     */
    public static function grant($userId, $currency, $amount, $source, $sourceId = 0, $remark = '')
    {
        $userId   = (int)$userId;
        $currency = (string)$currency;
        $amount   = (int)$amount;
        if ($amount <= 0) return ['ok' => false, 'reason' => 'zero_amount'];
        if (!self::currencyExists($currency)) return ['ok' => false, 'reason' => 'bad_currency'];

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            // 行锁当前用户，避免并发竞态导致重复加分
            $u = $pdo->prepare("SELECT id, token, byte, level FROM users WHERE id = ? FOR UPDATE");
            $u->execute([$userId]);
            $row = $u->fetch(PDO::FETCH_ASSOC);
            if (!$row) { $pdo->rollBack(); return ['ok' => false, 'reason' => 'no_user']; }

            // 幂等：同一 (user,currency,source,source_id,type) 已发放则跳过
            $chk = $pdo->prepare("SELECT id FROM points_log WHERE user_id = ? AND currency = ? AND source = ? AND source_id = ? AND type = 'earn'");
            $chk->execute([$userId, $currency, $source, $sourceId]);
            if ($chk->fetchColumn()) { $pdo->rollBack(); return ['ok' => false, 'reason' => 'duplicate']; }

            $balance = self::readBalanceLocked($pdo, $userId, $currency, $row);
            $balanceAfter = $balance + $amount;

            $ins = $pdo->prepare("INSERT INTO points_log (user_id, currency, type, source, source_id, amount, balance_after, remark, created_at) VALUES (?, ?, 'earn', ?, ?, ?, ?, ?, NOW())");
            $ins->execute([$userId, $currency, $source, $sourceId, $amount, $balanceAfter, $remark]);

            self::applyBalance($pdo, $userId, $currency, $balanceAfter);

            $newLevel = $row['level'];
            if ($currency === self::CURRENCY_BYTE) {
                $newLevel = self::computeLevel($balanceAfter);
                if ($newLevel != $row['level']) {
                    $pdo->prepare("UPDATE users SET level = ? WHERE id = ?")->execute([$newLevel, $userId]);
                }
            }
            $pdo->commit();
            return ['ok' => true, 'amount' => $amount, 'balance' => $balanceAfter, 'level' => $newLevel];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'reason' => 'db_error', 'detail' => $e->getMessage()];
        }
    }

    /**
     * 一次性发放「Token + Byte」双份积分（同一个动作的两套规则：xxx / xxx_byte）。
     * 规则缺失或停用时自动跳过，不影响主业务流程。
     * @return array ['ok'=>bool,'token'=>int,'byte'=>int,'level'=>int|null,'bonus'=>float]
     */
    public static function earnAction($userId, $source, $sourceId = 0, $remark = '')
    {
        $res = [
            'ok'    => false,
            'token' => 0,
            'byte'  => 0,
            'level' => null,
            'bonus' => 1.0,
            'extra' => [],
            // 实际发奖到的币种代码 → 显示名（联动后台改名）。前端 toast 用这个拼 "+N {name}"。
            // 即使 amount=0（规则关闭）也收集，便于前端知道「本来要发的是这个币种」。
            'labels' => [],
        ];
        $awarded = []; // 已发放币种集合，配合「按 code 命名的自定义币种规则」去重
        $reasons = []; // 每条未发奖规则的可见原因，便于后台调试 + 前台诊断

        // 1) 基础规则（如 post_create）→ 按规则自身「币种」字段发放（后台下拉选择即时生效）
        $baseRule = self::rule($source);
        if (!$baseRule) {
            $reasons[$source] = '规则不存在';
        } elseif ((int)$baseRule['enabled'] !== 1) {
            $reasons[$source] = '规则未启用';
        } elseif ((float)$baseRule['amount'] <= 0) {
            $reasons[$source] = '规则金额为 0';
        } else {
            $baseCur = !empty($baseRule['currency']) && self::currencyExists($baseRule['currency'])
                ? $baseRule['currency'] : self::CURRENCY_TOKEN;
            $res['labels'][$baseCur] = self::currencyLabel($baseCur);
            $r = self::earn($userId, $source, self::CURRENCY_TOKEN, $sourceId, $remark);
            if ($r['ok']) {
                $awarded[$baseCur] = true;
                $res['ok'] = true;
                $res['bonus'] = (float)$r['bonus'];
                if ($res['level'] === null) $res['level'] = (int)$r['level'];
                $amt = (int)$r['amount'];
                if ($baseCur === self::CURRENCY_TOKEN) $res['token'] = $amt;
                elseif ($baseCur === self::CURRENCY_BYTE) $res['byte'] = $amt;
                else $res['extra'][$baseCur] = ($res['extra'][$baseCur] ?? 0) + $amt;
            } else {
                $reasons[$source] = self::reasonText($r['reason'] ?? 'unknown');
            }
        }

        // 2) Byte 成长值规则（source_byte）→ 按规则币种发放（默认 byte，影响等级）。
        //    **不与基础规则去重**：若用户把两条规则都配成同一币种（如都改 X），本应累加（10+20=30），
        //    旧版 $awarded 去重会在第二步直接跳过，导致少发 _byte 那一份。
        $byteCode = $source . '_byte';
        $byteRule = self::rule($byteCode);
        if ($byteRule && (int)$byteRule['enabled'] === 1 && (float)$byteRule['amount'] > 0) {
            $byteCur = !empty($byteRule['currency']) && self::currencyExists($byteRule['currency'])
                ? $byteRule['currency'] : self::CURRENCY_BYTE;
            $res['labels'][$byteCur] = self::currencyLabel($byteCur);
            $r = self::earn($userId, $byteCode, self::CURRENCY_BYTE, $sourceId, $remark);
            if ($r['ok']) {
                $res['ok'] = true;
                if ($res['bonus'] === 1.0) $res['bonus'] = (float)$r['bonus'];
                if ($res['level'] === null) $res['level'] = (int)$r['level'];
                $amt = (int)$r['amount'];
                if ($byteCur === self::CURRENCY_TOKEN) $res['token'] += $amt;
                elseif ($byteCur === self::CURRENCY_BYTE) $res['byte'] += $amt;
                else $res['extra'][$byteCur] = ($res['extra'][$byteCur] ?? 0) + $amt;
            } else {
                $reasons[$byteCode] = self::reasonText($r['reason'] ?? 'unknown');
            }
        }

        // 3) 自定义币种 {source}_{code}：仅在「基础/byte 都没踩中该币种」时由这里发，
        //    避免同一 (user,source,source_id) 写多条 earn 流水。已被 awarded 包含的 code 跳过。
        foreach (self::customCurrencies() as $c) {
            $code = (string)$c['code'];
            $res['labels'][$code] = self::currencyLabel($code);
            if (isset($awarded[$code])) continue;
            $r = self::earn($userId, $source . '_' . $code, $code, $sourceId, $remark);
            if ($r['ok']) {
                $res['ok'] = true;
                if ($res['bonus'] === 1.0) $res['bonus'] = (float)$r['bonus'];
                if ($res['level'] === null) $res['level'] = (int)$r['level'];
                $amt = (int)$r['amount'];
                $res['extra'][$code] = ($res['extra'][$code] ?? 0) + $amt;
            }
        }
        $res['reasons'] = $reasons;
        return $res;
    }

    /**
     * 消耗积分（余额校验，单事务）
     * @param int|null $amount 不传则按规则表 amount（spend 为正值 = 扣减额）
     * @return array ['ok'=>bool, 'amount'=>int, 'balance'=>int, 'reason'=>string]
     */
    public static function spend($userId, $source, $currency, $sourceId = 0, $amount = null, $remark = '')
    {
        $userId = (int)$userId;
        $currency = (string)$currency;
        if (!self::currencyExists($currency)) return ['ok' => false, 'reason' => 'bad_currency'];

        if ($amount === null) {
            $rule = self::rule($source);
            if (!$rule || $rule['type'] !== 'spend' || !(int)$rule['enabled']) return ['ok' => false, 'reason' => 'no_rule'];
            $amount = (int)$rule['amount'];
        }
        if ($amount <= 0) return ['ok' => false, 'reason' => 'zero_amount'];

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            $u = $pdo->prepare("SELECT id, token, byte, level FROM users WHERE id = ? FOR UPDATE");
            $u->execute([$userId]);
            $row = $u->fetch(PDO::FETCH_ASSOC);
            if (!$row) { $pdo->rollBack(); return ['ok' => false, 'reason' => 'no_user']; }

            $balance = self::readBalanceLocked($pdo, $userId, $currency, $row);
            if ($balance < $amount) { $pdo->rollBack(); return ['ok' => false, 'reason' => 'insufficient']; }

            $balanceAfter = $balance - $amount;
            $ins = $pdo->prepare("INSERT INTO points_log (user_id, currency, type, source, source_id, amount, balance_after, remark, created_at) VALUES (?, ?, 'spend', ?, ?, ?, ?, ?, NOW())");
            $ins->execute([$userId, $currency, $source, $sourceId, -$amount, $balanceAfter, $remark]);

            self::applyBalance($pdo, $userId, $currency, $balanceAfter);

            // 消耗 byte 时也重算等级（理论上 byte 不可消耗，此处为兼容兜底）
            if ($currency === self::CURRENCY_BYTE) {
                $newLevel = self::computeLevel($balanceAfter);
                if ($newLevel != $row['level']) {
                    $pdo->prepare("UPDATE users SET level = ? WHERE id = ?")->execute([$newLevel, $userId]);
                }
            }

            $pdo->commit();
            return ['ok' => true, 'amount' => $amount, 'balance' => $balanceAfter];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // 严禁把 PDO 原文当 reason 透传给前台；统一给 db_error 码 + detail（仅写日志）
            return ['ok' => false, 'reason' => 'db_error', 'detail' => $e->getMessage()];
        }
    }

    /**
     * 删帖/删评回溯：把某来源(source)某对象(source_id)下所有 earn 流水反向回收。
     * - 同时回溯 token（source 原值）+ byte（source_byte），因为 earnAction() 的 byte 流水 source 字段
     *   约定为 "<原 source>_byte"（如 post_create → post_create_byte），单查 source=会漏掉 byte。
     * - 对每一条 earn 流水生成一条 type='refund' 的负流水，并从用户余额扣减（不低于 0），
     *   若扣减的是 byte 则重算等级。已回溯过的（同 user/currency/source/source_id/refund）自动跳过，幂等。
     * @return bool 是否执行成功
     */
    public static function refundBySource($source, $sourceId, $userId = null)
    {
        $source = (string)$source;
        $sourceId = (int)$sourceId;
        // 匹配所有相关 source：精确 $source + 以 "{$source}_" 开头的所有规则（含 _byte 与自定义币种如 _diamond）
        $sources = [$source];
        try {
            $pattern = str_replace('_', '\\_', $source) . '\\_%';
            $like = Model::table('point_rules')->select('code')->where('code', 'LIKE', $pattern)->get();
            foreach ($like as $l) $sources[] = $l['code'];
        } catch (\Throwable $e) { /* 表不存在则仅退 token/byte */ }
        $sources = array_unique($sources);
        $sourceIn = implode(',', array_fill(0, count($sources), '?'));
        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            // 查该 source(s)+source_id 下所有用户的 earn 流水（仅 amount>0 的入账记录；不退款 spend）
            $sql = "SELECT * FROM points_log WHERE source IN ($sourceIn) AND source_id = ? AND type = 'earn' AND amount > 0";
            $params = $sources;
            $params[] = $sourceId;
            if ($userId !== null) { $sql .= ' AND user_id = ?'; $params[] = (int)$userId; }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $earns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($earns as $e) {
                $uid = (int)$e['user_id'];
                $currency = $e['currency'];
                $eSource = $e['source']; // 实际命中的 source 字符串（可能是原 source、source_byte 或自定义币种 source）
                // 幂等：同一 user/currency/source/source_id 已存在任意一条 refund 流水则跳过
                $chk = $pdo->prepare("SELECT id FROM points_log WHERE user_id = ? AND currency = ? AND source = ? AND source_id = ? AND type = 'refund'");
                $chk->execute([$uid, $currency, $eSource, $sourceId]);
                if ($chk->fetchColumn()) continue;

                $u = $pdo->prepare("SELECT id, token, byte, level FROM users WHERE id = ? FOR UPDATE");
                $u->execute([$uid]);
                $row = $u->fetch(PDO::FETCH_ASSOC);
                if (!$row) continue;

                $amount = (int)$e['amount'];
                $balance = self::readBalanceLocked($pdo, $uid, $currency, $row);
                $balanceAfter = max(0, $balance - $amount); // 余额最低 0，绝不允许负

                $remark = ($currency === self::CURRENCY_BYTE) ? '删除内容回溯回收 Byte' : ('删除内容回溯回收 ' . self::currencyLabel($currency));
                $ins = $pdo->prepare("INSERT INTO points_log (user_id, currency, type, source, source_id, amount, balance_after, remark, created_at) VALUES (?, ?, 'refund', ?, ?, ?, ?, ?, NOW())");
                $ins->execute([$uid, $currency, $eSource, $sourceId, -$amount, $balanceAfter, $remark]);

                self::applyBalance($pdo, $uid, $currency, $balanceAfter);

                if ($currency === self::CURRENCY_BYTE) {
                    $newLevel = self::computeLevel($balanceAfter);
                    if ($newLevel != (int)$row['level']) {
                        $pdo->prepare("UPDATE users SET level = ? WHERE id = ?")->execute([$newLevel, $uid]);
                    }
                }
            }
            $pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return false;
        }
    }

    /**
     * 帖子被删除时，整体回溯该帖相关的所有积分：
     *  - 帖子本身：post_create（作者）、post_liked（作者，被赞）
     *  - 帖子下每条评论：comment_create / reply_create / post_liked（按评论 id）
     * 全部在同一事务内完成，已回溯的自动跳过。
     */
    public static function refundPostPoints($postId)
    {
        $postId = (int)$postId;
        self::refundBySource('post_create', $postId);
        self::refundBySource('post_liked', $postId);
        $commentIds = Model::table('comments')->select('id')->where('post_id', $postId)->where('status', '>=', 0)->get();
        foreach ($commentIds as $c) {
            $cid = (int)$c['id'];
            self::refundBySource('comment_create', $cid);
            self::refundBySource('reply_create', $cid);
            self::refundBySource('post_liked', $cid);
        }
        return true;
    }

    /**
     * 恢复（refund 的反操作）：把某 source/sourceId 下已经 refund 出去的积分重新发回给作者。
     * - 匹配 type='refund' 流水（amount<0），对每一条生成 type='restore' 的正向流水并把余额加回。
     * - 同一 user/currency/source/source_id 已存在任意 restore 流水则跳过，幂等。
     * - 与 refundBySource 对称：source 字段既可能是原 source 也可能是 "<source>_byte"。
     * @return int 实际退回数（>0 即成功恢复过）
     */
    public static function refundReverseBySource($source, $sourceId, $userId = null)
    {
        $source = (string)$source;
        $sourceId = (int)$sourceId;
        // 与 refundBySource 对称：精确 $source + 所有 "{$source}_" 开头的规则
        $sources = [$source];
        try {
            $pattern = str_replace('_', '\\_', $source) . '\\_%';
            $like = Model::table('point_rules')->select('code')->where('code', 'LIKE', $pattern)->get();
            foreach ($like as $l) $sources[] = $l['code'];
        } catch (\Throwable $e) {}
        $sources = array_unique($sources);
        $sourceIn = implode(',', array_fill(0, count($sources), '?'));
        $pdo = Database::pdo();
        $restoredCount = 0;
        try {
            $pdo->beginTransaction();
            $sql = "SELECT * FROM points_log WHERE source IN ($sourceIn) AND source_id = ? AND type = 'refund' AND amount < 0";
            $params = $sources;
            $params[] = $sourceId;
            if ($userId !== null) { $sql .= ' AND user_id = ?'; $params[] = (int)$userId; }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($refunds as $r) {
                $uid = (int)$r['user_id'];
                $currency = $r['currency'];
                $rSource = $r['source'];
                // 幂等：同 user/currency/source/source_id 已存在 restore 则跳过
                $chk = $pdo->prepare("SELECT id FROM points_log WHERE user_id = ? AND currency = ? AND source = ? AND source_id = ? AND type = 'restore'");
                $chk->execute([$uid, $currency, $rSource, $sourceId]);
                if ($chk->fetchColumn()) continue;

                $u = $pdo->prepare("SELECT id, token, byte, level FROM users WHERE id = ? FOR UPDATE");
                $u->execute([$uid]);
                $row = $u->fetch(PDO::FETCH_ASSOC);
                if (!$row) continue;

                $amount = (int)abs($r['amount']); // refund amount 是负数，取绝对值发回去
                $balance = self::readBalanceLocked($pdo, $uid, $currency, $row);
                $balanceAfter = $balance + $amount;
                $remark = ($currency === self::CURRENCY_BYTE) ? '恢复内容退还 Byte' : ('恢复内容退还 ' . self::currencyLabel($currency));
                $ins = $pdo->prepare("INSERT INTO points_log (user_id, currency, type, source, source_id, amount, balance_after, remark, created_at) VALUES (?, ?, 'restore', ?, ?, ?, ?, ?, NOW())");
                $ins->execute([$uid, $currency, $rSource, $sourceId, $amount, $balanceAfter, $remark]);

                self::applyBalance($pdo, $uid, $currency, $balanceAfter);

                if ($currency === self::CURRENCY_BYTE) {
                    $newLevel = self::computeLevel($balanceAfter);
                    if ($newLevel != (int)$row['level']) {
                        $pdo->prepare("UPDATE users SET level = ? WHERE id = ?")->execute([$newLevel, $uid]);
                    }
                }
                $restoredCount++;
            }
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return 0;
        }
        return $restoredCount;
    }

    /**
     * 恢复一个帖子的全部积分（refundPostPoints 的反操作）：
     *  - 帖子本身：post_create（作者）、post_liked（作者，被赞）
     *  - 帖子下每条评论：comment_create / reply_create / post_liked（按评论 id）
     * 调用场景：管理员在回收站"恢复"了一个被删除的帖子，连带把已退的积分退还给作者 + 评论者 + 被赞者。
     */
    public static function refundReversePostPoints($postId)
    {
        $postId = (int)$postId;
        self::refundReverseBySource('post_create', $postId);
        self::refundReverseBySource('post_liked', $postId);
        $commentIds = Model::table('comments')->select('id')->where('post_id', $postId)->get();
        foreach ($commentIds as $c) {
            $cid = (int)$c['id'];
            self::refundReverseBySource('comment_create', $cid);
            self::refundReverseBySource('reply_create', $cid);
            self::refundReverseBySource('post_liked', $cid);
        }
        return true;
    }

    /**
     * 恢复一条评论的全部积分（与 refundReversePostPoints 类似但只针对单条评论）
     */
    public static function refundReverseCommentPoints($commentId)
    {
        $commentId = (int)$commentId;
        self::refundReverseBySource('comment_create', $commentId);
        self::refundReverseBySource('reply_create', $commentId);
        self::refundReverseBySource('post_liked', $commentId);
        return true;
    }

    /**
     * 打赏（双向流水，同一事务）：
     *  - 打赏者：spend Token（余额不足拒绝）
     *  - 受赏者：earn 等额 Token（固定值，不享受等级加成）
     *  - 写 reward_log 记录
     * @return array ['ok'=>bool,'amount'=>int,'balance'=>int,'reason'=>string]
     */
    public static function reward($fromUid, $toUid, $postId, $commentId, $amount)
    {
        $fromUid = (int)$fromUid;
        $toUid = (int)$toUid;
        $postId = (int)$postId;
        $commentId = (int)$commentId;
        $amount = (int)$amount;
        if ($amount < 5) return ['ok' => false, 'reason' => 'min'];
        if ($fromUid === $toUid) return ['ok' => false, 'reason' => 'self'];

        // —— 后台「打赏配置」读取：每日上限 / 间隔 / 单笔上限 ——
        $cfg = self::rewardConfig();
        if ($cfg['max_amount'] > 0 && $amount > $cfg['max_amount']) {
            return ['ok' => false, 'reason' => 'max_amount'];
        }
        if ($cfg['daily_count'] > 0) {
            $cnt = (int)Model::scalar(
                'SELECT COUNT(*) FROM reward_log WHERE from_uid = ? AND DATE(created_at) = CURDATE()',
                [$fromUid]
            );
            if ($cnt >= $cfg['daily_count']) {
                return ['ok' => false, 'reason' => 'daily_limit'];
            }
        }
        if ($cfg['interval'] > 0) {
            $last = Model::scalar(
                'SELECT UNIX_TIMESTAMP(MAX(created_at)) FROM reward_log WHERE from_uid = ?',
                [$fromUid]
            );
            if ($last && (time() - (int)$last) < $cfg['interval']) {
                $wait = $cfg['interval'] - (time() - (int)$last);
                return ['ok' => false, 'reason' => 'too_fast', 'wait' => $wait];
            }
        }

        // 不限制重复打赏（同一用户对同一帖可多次打赏）。
        // 唯一键冲突的根因修复：points_log 有 uk_unique(user_id,currency,source,source_id,type)，
        // 旧逻辑用 $postId 作为 source_id → 同一帖二次打赏必撞唯一键。
        // 新逻辑：先 INSERT reward_log 拿到本次唯一 id，再用它作 points_log.source_id。
        $attempts = 0;
        $maxAttempts = 2;
        retry:
        $attempts++;
        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            // 打赏者余额校验 + 扣减
            $u = $pdo->prepare("SELECT id, token FROM users WHERE id = ? FOR UPDATE");
            $u->execute([$fromUid]);
            $row = $u->fetch(PDO::FETCH_ASSOC);
            if (!$row) { $pdo->rollBack(); return ['ok' => false, 'reason' => 'no_user']; }
            $balance = (int)$row['token'];
            if ($balance < $amount) { $pdo->rollBack(); return ['ok' => false, 'reason' => 'insufficient']; }
            $balanceAfter = $balance - $amount;

            // —— 关键修复：先 INSERT reward_log 拿本次唯一 id ——
            $insRw = $pdo->prepare("INSERT INTO reward_log (post_id, comment_id, from_uid, to_uid, token, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $insRw->execute([$postId, $commentId, $fromUid, $toUid, $amount]);
            $rewardId = (int)$pdo->lastInsertId();

            // —— 再用 rewardId 作 points_log.source_id —— 这样 source_id 在同一帖子下永不重复
            $pdo->prepare("INSERT INTO points_log (user_id, currency, type, source, source_id, amount, balance_after, remark, created_at) VALUES (?, 'token', 'spend', 'reward', ?, ?, ?, ?, NOW())")
                ->execute([$fromUid, $rewardId, -$amount, $balanceAfter, '打赏支出']);
            $pdo->prepare("UPDATE users SET token = ? WHERE id = ?")->execute([$balanceAfter, $fromUid]);

            // 受赏者入账
            $u2 = $pdo->prepare("SELECT id, token FROM users WHERE id = ? FOR UPDATE");
            $u2->execute([$toUid]);
            $row2 = $u2->fetch(PDO::FETCH_ASSOC);
            if (!$row2) { $pdo->rollBack(); return ['ok' => false, 'reason' => 'no_receiver']; }
            $recBal = (int)$row2['token'] + $amount;
            $pdo->prepare("INSERT INTO points_log (user_id, currency, type, source, source_id, amount, balance_after, remark, created_at) VALUES (?, 'token', 'earn', 'rewarded', ?, ?, ?, ?, NOW())")
                ->execute([$toUid, $rewardId, $amount, $recBal, '收到打赏']);
            $pdo->prepare("UPDATE users SET token = token + ? WHERE id = ?")->execute([$amount, $toUid]);

            $pdo->commit();
            return ['ok' => true, 'amount' => $amount, 'balance' => $balanceAfter];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = $e->getMessage();
            // 死锁 / 锁等待：自动重试一次（提交给用户的仍是确定的失败，不会偷偷多扣）
            if ($attempts < $maxAttempts && (stripos($msg, 'Deadlock') !== false || stripos($msg, 'Lock wait timeout') !== false)) {
                usleep(50000); // 50ms 让 InnoDB 先释放锁
                goto retry;
            }
            return ['ok' => false, 'reason' => 'db_error', 'detail' => $msg];
        }
    }

    /**
     * 读取「打赏配置」（settings 表，缺失用兜底默认值）
     *  - min_amount    单笔下限（≤0 表示不限，前端表单最低值仍走 5 防刷 UI 提示；后端校验走此值）
     *  - max_amount    单笔上限（0=不限）
     *  - default_amount 弹窗默认 / 前端 "+N" 第一个推荐值（用于 openReward 回填）
     *  - daily_count   单日打赏次数上限（0=不限）
     *  - interval      两次打赏最小间隔秒数（0=不限）
     */
    public static function rewardConfig()
    {
        return [
            'min_amount'     => 5,
            'max_amount'     => (int)setting('reward_max_amount', 0),
            'default_amount' => (int)setting('reward_default_amount', 5),
            'daily_count'    => (int)setting('reward_daily_count', 0),
            'interval'       => (int)setting('reward_interval', 0),
        ];
    }

    /**
     * 自助置顶：用户花 Token 按时长购买置顶。
     * 费率取 point_rules.code='self_pin' 的 amount（元/小时，缺失兜底 1000）；
     * 扣费成功后写 self_pin 记录并把 posts.self_pin_until 置为到期时间。
     * @return array ['ok'=>bool,'expire_at'=>string,'cost'=>int,'reason'=>string]
     */
    public static function selfPin($postId, $userId, $hours)
    {
        $postId = (int)$postId;
        $userId = (int)$userId;
        $hours = (int)$hours;
        if ($hours < 1 || $hours > 72) return ['ok' => false, 'reason' => 'hours'];

        $rule = self::rule('self_pin');
        $rate = $rule ? (int)$rule['amount'] : 1000;
        $cost = $rate * $hours;

        $spend = self::spend($userId, 'self_pin', self::CURRENCY_TOKEN, $postId, $cost, "自助置顶{$hours}小时");
        if (!$spend['ok']) return $spend;

        $expireAt = date('Y-m-d H:i:s', time() + $hours * 3600);
        Model::table('self_pin')->insert([
            'post_id' => $postId,
            'user_id' => $userId,
            'hours' => $hours,
            'paid_token' => $cost,
            'expire_at' => $expireAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        Model::table('posts')->where('id', $postId)->update(['self_pin_until' => $expireAt]);
        return ['ok' => true, 'expire_at' => $expireAt, 'cost' => $cost];
    }

    /**
     * 等级信息（用于前台展示）：当前等级 / 名称 / 进度 / 下一级
     * 关键：当前等级按 byte 实时反算（computeLevel），不受 users.level 字段滞后影响；
     *       后台新增等级后无需触发积分变动即可联动展示。
     */
    public static function getLevelInfo($user)
    {
        $byte = (int)($user['byte'] ?? 0);
        $lv = self::loadLevels();
        // 按 byte 实时反算真实等级（取代旧的"直接读 $user['level']"——后者只在写路径更新，会滞后）
        $level = self::computeLevel($byte);
        $cur = null;
        foreach ($lv as $row) {
            if ((int)$row['level'] === $level) { $cur = $row; break; }
        }
        // 找不到（异常数据）则回退第一级
        if (!$cur) $cur = $lv[0] ?? ['level' => 1, 'name' => 'Lv.1', 'byte_required' => 0, 'bonus_factor' => 1.0, 'privilege_text' => ''];

        // 下一级（按 level 升序，比当前 level 更大的第一行）
        $nxt = null;
        foreach ($lv as $row) {
            if ((int)$row['level'] > $level) { $nxt = $row; break; }
        }
        $curMin = (int)$cur['byte_required'];
        $nextMin = $nxt ? (int)$nxt['byte_required'] : null;
        $isMax = ($nxt === null); // 没有更高档即为满级

        $progress = 0;
        if (!$isMax && $nextMin !== null && $nextMin > $curMin) {
            $progress = min(100, (int)round(($byte - $curMin) / ($nextMin - $curMin) * 100));
        } elseif ($isMax) {
            $progress = 100;
        }
        $thresholds = [];
        foreach ($lv as $row) $thresholds[] = (int)$row['byte_required'];
        $names = [];
        foreach ($lv as $row) $names[] = (string)$row['name'];
        return [
            'level'      => $level,
            'name'       => (string)$cur['name'],
            'byte'       => $byte,
            'cur_min'    => $curMin,
            'next_min'   => $nextMin,
            'is_max'     => $isMax,
            'to_next'    => $isMax ? 0 : max(0, $nextMin - $byte),
            'progress'   => $progress,
            'thresholds' => $thresholds,
            'names'      => $names,
            'rows'       => $lv,
        ];
    }
}
