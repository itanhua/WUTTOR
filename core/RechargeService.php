<?php
/**
 * 积分充值服务
 *
 * 三套充值通道（共用一张 recharge_orders 表，入账逻辑零重复）：
 *   - personal   个人收款码 + 金额尾数匹配（无商户资质即可跑通，开发/内测用）
 *   - aggregate  聚合支付 SDK（码支付 / 易支付 / 虎皮椒 等）
 *   - merchant   自有企业官方商户号（微信支付 + 支付宝 各自独立配置）
 *
 * 卡密充值：recharge_cards 表，前缀 + 4 段随机字符，含生成/使用/过期/作废全生命周期。
 * 折扣活动：recharge_campaigns 表，bonus(加赠)/discount(折扣)/first(首充) 三类，可叠加。
 *
 * 入账统一走 PointService::grant()（直接发放，绕过规则表与风控闸门，但保留幂等与并发安全）。
 */
class RechargeService
{
    const TYPE_BONUS   = 'bonus';
    const TYPE_DISCOUNT = 'discount';
    const TYPE_FIRST   = 'first';

    const SCHEME_PERSONAL   = 'personal';
    const SCHEME_AGGREGATE  = 'aggregate';
    const SCHEME_MERCHANT   = 'merchant';

    // 全局兜底默认比例：1 元 = 100 积分（仅用于「按币种未单独配置」时兜底，不可在后台设置）
    const DEFAULT_BASE_RATE = 100;

    // 卡密随机字符集：去掉易混淆 0/O/1/I
    const CARD_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * 充值相关流水来源中文名映射（卡密/在线支付走 RechargeService::grant 直发，不经过 point_rules，
     * 流水 source 列在用户「我的积分」页需要中文展示）。
     * 新增充值来源时在此补一行 code => '中文名'；UserController::points 会自动合并进 ruleNames。
     */
    private static $sourceLabels = [
        'recharge_card'  => '卡密充值',
        'recharge_order' => '在线充值',
    ];

    public static function sourceLabels()
    {
        return self::$sourceLabels;
    }

    // ============ 配置（settings 表 key=recharge_config，JSON）============

    /**
     * 读取充值配置（带默认值兜底）
     */
    public static function getConfig()
    {
        $raw = setting('recharge_config', '');
        $cfg = $raw ? @json_decode($raw, true) : [];
        if (!is_array($cfg)) $cfg = [];
        $defaults = [
            'base_rate'       => self::DEFAULT_BASE_RATE, // 全局兜底：1 元 = N 积分（按币种配置缺失时兜底）
            'base_rates'      => [],  // 按币种分别配置 base_rates[code] = N；后续新增币种会自动占一个槽位
            'enabled_scheme'  => self::SCHEME_PERSONAL,
            'schemes'         => [
                self::SCHEME_PERSONAL  => ['enabled' => true,  'tail_precision' => 2, 'wechat_qr' => '', 'alipay_qr' => ''],
                self::SCHEME_AGGREGATE => ['enabled' => false, 'provider' => '', 'merchant_id' => '', 'key' => '', 'notify_url' => ''],
                self::SCHEME_MERCHANT  => [
                    'enabled' => false,
                    'wechat'  => ['appid' => '', 'mch_id' => '', 'api_v3_key' => '', 'cert_path' => '', 'key_path' => '', 'notify_url' => ''],
                    'alipay'  => ['app_id' => '', 'private_key' => '', 'public_key' => '', 'gateway' => ''],
                ],
            ],
        ];
        // 逐层合并，避免缺字段
        $cfg = array_merge($defaults, $cfg);
        foreach ($defaults['schemes'] as $k => $v) {
            if (!isset($cfg['schemes'][$k]) || !is_array($cfg['schemes'][$k])) {
                $cfg['schemes'][$k] = $v;
            } else {
                $cfg['schemes'][$k] = array_merge($v, $cfg['schemes'][$k]);
            }
        }
        // 防御：base_rates 必须是数组；逐项强制转为 int 并保证 >= 1
        if (!is_array($cfg['base_rates'])) $cfg['base_rates'] = [];
        $cleaned = [];
        foreach ($cfg['base_rates'] as $code => $val) {
            $code = (string)$code;
            $v = (int)$val;
            if ($code !== '' && $v >= 1) $cleaned[$code] = $v;
        }
        $cfg['base_rates'] = $cleaned;
        // 防御：enabled_scheme 必须是合法值（含空串=无任何启用方案）
        if (!in_array($cfg['enabled_scheme'], [self::SCHEME_PERSONAL, self::SCHEME_AGGREGATE, self::SCHEME_MERCHANT, ''], true)) {
            $cfg['enabled_scheme'] = self::SCHEME_PERSONAL;
        }
        return $cfg;
    }

    public static function saveConfig($cfg)
    {
        $pdo  = Database::pdo();
        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE);
        $pdo->prepare("INSERT INTO settings (key_name, value) VALUES ('recharge_config', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)")
            ->execute([$json]);
    }

    /**
     * 启用某一支付方案（互斥：同时仅一个启用）
     */
    public static function setEnabledScheme($scheme)
    {
        if (!in_array($scheme, [self::SCHEME_PERSONAL, self::SCHEME_AGGREGATE, self::SCHEME_MERCHANT])) {
            return false;
        }
        $cfg = self::getConfig();
        $cfg['enabled_scheme'] = $scheme;
        self::saveConfig($cfg);
        return true;
    }

    // ============ 卡密生成 ============

    private static function randSeg()
    {
        $a = self::CARD_ALPHABET;
        $len = strlen($a);
        $out = '';
        for ($i = 0; $i < 4; $i++) {
            $out .= $a[random_int(0, $len - 1)];
        }
        return $out;
    }

    /**
     * 生成完整卡号：前缀 + 4 段独立随机字符（每段 4 位，共 16 位），如 VIP-8F3K-2X9Q-7M1A-3B6D
     */
    public static function genCardNo($prefix)
    {
        $prefix = rtrim((string)$prefix, '-') . '-';
        $segs = [];
        for ($s = 0; $s < 4; $s++) {
            $segs[] = self::randSeg();
        }
        return $prefix . implode('-', $segs);
    }

    /**
     * 批量生成卡密
     * @return array 生成的卡号列表
     */
    public static function generateCards($prefix, $count, $amount, $currency, $expireDays, $batchNote)
    {
        $count = max(1, min(500, (int)$count));
        $expireAt = null;
        if ((int)$expireDays > 0) {
            $expireAt = date('Y-m-d H:i:s', strtotime("+{$expireDays} days"));
        }
        $now = date('Y-m-d H:i:s');
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $no = self::genCardNo($prefix);
            $guard = 0;
            while (Model::table('recharge_cards')->where('card_no', $no)->first() && $guard++ < 10) {
                $no = self::genCardNo($prefix);
            }
            Model::table('recharge_cards')->insert([
                'card_no'     => $no,
                'prefix'      => (string)$prefix,
                'currency'    => (string)$currency,
                'amount'      => (int)$amount,
                'status'      => 'unused',
                'created_at'  => $now,
                'expire_at'   => $expireAt,
                'batch_note'  => (string)$batchNote,
            ]);
            $rows[] = $no;
        }
        return $rows;
    }

    // ============ 卡密兑换 ============

    /**
     * 兑换卡密，给当前登录用户充值
     * @return array ['ok'=>bool,'msg'=>?,'amount'=>?,'currency'=>?]
     */
    public static function redeem($cardNo, $userId)
    {
        $userId = (int)$userId;
        $cardNo = trim((string)$cardNo);
        if ($cardNo === '') return ['ok' => false, 'msg' => '请输入卡密'];

        $card = Model::table('recharge_cards')->where('card_no', $cardNo)->first();
        if (!$card) return ['ok' => false, 'msg' => '卡密无效或不存在'];
        if ($card['status'] === 'used')    return ['ok' => false, 'msg' => '该卡密已被使用'];
        if ($card['status'] === 'invalid') return ['ok' => false, 'msg' => '该卡密已作废'];
        if ($card['status'] === 'expired' || ($card['expire_at'] && strtotime($card['expire_at']) < time())) {
            Model::table('recharge_cards')->where('id', (int)$card['id'])->update(['status' => 'expired']);
            return ['ok' => false, 'msg' => '该卡密已过期'];
        }
        if ((int)$card['used_by'] !== 0) return ['ok' => false, 'msg' => '该卡密已被其他账户使用'];

        $res = PointService::grant(
            $userId,
            $card['currency'],
            (int)$card['amount'],
            'recharge_card',
            (int)$card['id'],
            '卡密充值 ' . $card['card_no']
        );
        if (!$res['ok']) {
            $reason = $res['reason'] ?? 'unknown';
            if ($reason === 'duplicate') return ['ok' => false, 'msg' => '该卡密已充值，不可重复领取'];
            return ['ok' => false, 'msg' => PointService::reasonText($reason)];
        }

        Model::table('recharge_cards')->where('id', (int)$card['id'])->update([
            'status'  => 'used',
            'used_at' => date('Y-m-d H:i:s'),
            'used_by' => $userId,
        ]);
        return [
            'ok'       => true,
            'amount'   => (int)$card['amount'],
            'currency' => $card['currency'],
            'card'     => $card['card_no'],
        ];
    }

    // ============ 折扣活动 ============

    /**
     * 当前生效的活动（按渠道 + 时间窗过滤）
     */
    public static function activeCampaigns($channel, $userId = null)
    {
        $now = date('Y-m-d H:i:s');
        $list = Model::table('recharge_campaigns')->where('enabled', 1)->get();
        $out = [];
        foreach ($list as $c) {
            $ch = json_decode($c['channels'] ?? '[]', true);
            if (!is_array($ch)) $ch = [];
            if (!empty($ch) && !in_array($channel, $ch, true)) continue;
            if ($c['start_at'] && $c['start_at'] > $now) continue;
            if ($c['end_at'] && $c['end_at'] < $now) continue;
            $out[] = $c;
        }
        return $out;
    }

    public static function baseRateFor($currency)
    {
        $cfg = self::getConfig();
        $currency = (string)$currency;
        if ($currency !== '' && isset($cfg['base_rates'][$currency]) && (int)$cfg['base_rates'][$currency] >= 1) {
            return (int)$cfg['base_rates'][$currency];
        }
        return self::DEFAULT_BASE_RATE;
    }

    /**
     * 计算充值可得（基础比例 + 活动叠加）
     * @return array ['base','gained','pay','tag','note','campaign_id','currency']
     */
    public static function calcGain($yuan, $currency, $channel, $userId = null)
    {
        $yuan = (float)$yuan;
        $cfg  = self::getConfig();
        // 按币种分别取基础比例（无配置则回退全局 base_rate），便于「Token = 100/Byte = 2」类分层精度
        $rate = self::baseRateFor($currency);
        $base = (int)round($yuan * $rate); // 该币种基础可得数量
        $gained = $base;
        $pay = $yuan;
        $tag = '';
        $note = '';
        $campaignId = null;

        $campaigns = self::activeCampaigns($channel, $userId);
        foreach ($campaigns as $c) {
            if ((float)$c['min_amount'] > 0 && $yuan < (float)$c['min_amount']) continue;
            if ($c['type'] === self::TYPE_FIRST) {
                if ($userId === null) continue;
                if (self::hasRecharged($userId)) continue; // 非首次跳过
                $gained = (int)round($base * (1 + (float)$c['value']));
                $tag = 'first';
                $note = '首充 ×' . (1 + (float)$c['value']);
                $campaignId = (int)$c['id'];
                break;
            } elseif ($c['type'] === self::TYPE_BONUS) {
                $add = (int)round($base * (float)$c['value']);
                if ((int)$c['cap'] > 0) $add = min($add, (int)$c['cap']);
                $gained = $base + $add;
                $tag = 'bonus';
                $note = '加赠 ' . round((float)$c['value'] * 100) . '%' . ((int)$c['cap'] > 0 ? ('（封顶 ' . (int)$c['cap'] . '）') : '');
                $campaignId = (int)$c['id'];
                break;
            } elseif ($c['type'] === self::TYPE_DISCOUNT) {
                $pay = round($yuan * (float)$c['value'], 2);
                $gained = $base; // 按原档位发
                $tag = 'discount';
                $dn = (float)$c['value'] * 10;
                $note = '充' . round($dn, 1) . '折（少付多得）';
                $campaignId = (int)$c['id'];
                break;
            }
        }
        return [
            'base'        => $base,
            'gained'      => $gained,
            'pay'         => (float)$pay,
            'tag'         => $tag,
            'note'        => $note,
            'campaign_id' => $campaignId,
            'currency'    => $currency,
        ];
    }

    /**
     * 该用户是否曾经成功充值（用于首充判定）
     */
    public static function hasRecharged($userId)
    {
        $userId = (int)$userId;
        $n1 = (int)Model::scalar("SELECT COUNT(*) FROM recharge_orders WHERE user_id = ? AND status = 'paid'", [$userId]);
        $n2 = (int)Model::scalar("SELECT COUNT(*) FROM recharge_cards WHERE used_by = ? AND status = 'used'", [$userId]);
        return ($n1 + $n2) > 0;
    }

    // ============ 订单 ============

    private static function randStr($len)
    {
        $a = self::CARD_ALPHABET;
        $len0 = strlen($a);
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $a[random_int(0, $len0 - 1)];
        }
        return $out;
    }

    /**
     * 创建充值订单（微信/支付宝）
     * @return array
     */
    public static function createOrder($userId, $channel, $yuan, $currency)
    {
        $userId = (int)$userId;
        $yuan   = (float)$yuan;
        if ($yuan <= 0) return ['ok' => false, 'msg' => '请输入有效金额'];
        if (!in_array($channel, ['wechat', 'alipay'], true)) return ['ok' => false, 'msg' => '渠道不合法'];
        if (!PointService::currencyExists($currency)) return ['ok' => false, 'msg' => '币种不存在'];

        $gain  = self::calcGain($yuan, $currency, $channel, $userId);
        $cfg   = self::getConfig();
        $scheme = (string)($cfg['enabled_scheme'] ?? '');
        // 兜底：未启用任何在线方案 → 拒绝下单（防止 ajax 被绕过 tab 隐藏直接调）
        if ($scheme === '') {
            return ['ok' => false, 'msg' => '当前未启用任何在线支付方案，请联系站长'];
        }

        $payAmount = $gain['pay'];
        $tail = null;
        // 个人码方案：应付 = 充值额 + 随机尾数（后台按尾数匹配未支付订单）
        if ($scheme === self::SCHEME_PERSONAL) {
            $tail = (float)number_format(mt_rand(1, 98) / 100, 2); // 0.01 ~ 0.98
            $payAmount = round($payAmount + $tail, 2);
        }

        $orderNo = 'RC' . date('Ymd') . self::randStr(10);
        $expireAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        Model::table('recharge_orders')->insert([
            'order_no'    => $orderNo,
            'user_id'     => $userId,
            'channel'     => $channel,
            'amount'      => $payAmount,
            'currency'    => $currency,
            'gained'      => $gain['gained'],
            'scheme'      => $scheme,
            'pay_tail'    => $tail,
            'campaign_id' => $gain['campaign_id'],
            'status'      => 'pending',
            'created_at'  => date('Y-m-d H:i:s'),
            'expire_at'   => $expireAt,
        ]);

        return [
            'ok'         => true,
            'order_no'   => $orderNo,
            'pay_amount' => $payAmount,
            'gained'     => $gain['gained'],
            'currency'   => $currency,
            'scheme'     => $scheme,
            'tail'       => $tail,
            'note'       => $gain['note'],
            'channel'    => $channel,
        ];
    }

    /**
     * 支付回调 / 模拟支付成功：入账并标记已支付
     * 真实通道回调时传 $raw（如微信 resource.ciphertext 解密后的明文 / 支付宝 notify 参数），
     * 这里统一按 order_no 入账，仅做幂等保护。
     * @return array
     */
    public static function completeOrder($orderNo, $raw = null)
    {
        $order = Model::table('recharge_orders')->where('order_no', $orderNo)->first();
        if (!$order) return ['ok' => false, 'msg' => '订单不存在'];
        if ($order['status'] === 'paid') return ['ok' => false, 'msg' => '订单已处理'];
        if ($order['status'] === 'expired') return ['ok' => false, 'msg' => '订单已过期'];

        $res = PointService::grant(
            (int)$order['user_id'],
            $order['currency'],
            (int)$order['gained'],
            'recharge_order',
            (int)$order['id'],
            '充值订单 ' . $orderNo
        );
        if (!$res['ok']) {
            return ['ok' => false, 'msg' => PointService::reasonText($res['reason'] ?? 'unknown')];
        }

        $upd = ['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')];
        if ($raw !== null) $upd['callback_raw'] = is_string($raw) ? $raw : json_encode($raw, JSON_UNESCAPED_UNICODE);
        Model::table('recharge_orders')->where('id', (int)$order['id'])->update($upd);

        return ['ok' => true, 'gained' => (int)$order['gained'], 'currency' => $order['currency']];
    }

    /**
     * 个人码尾数匹配：传入账单实付金额，找出金额精确相等且待支付的订单。
     * 真实场景由后台定时对账任务调用（拉取微信/支付宝账单 → 逐笔匹配）。
     * @return array|null
     */
    public static function findPendingByAmount($payAmount)
    {
        $payAmount = (string)round((float)$payAmount, 2);
        return Model::table('recharge_orders')
            ->where('status', 'pending')
            ->where('scheme', self::SCHEME_PERSONAL)
            ->whereRaw("ROUND(amount,2) = ?", [$payAmount])
            ->orderBy('created_at', 'DESC')
            ->first();
    }

    // ============ 折扣活动 CRUD ============

    /**
     * 新增折扣活动（含前端一致的校验 + 渠道互斥校验）
     * 规则：每个「启用中」的活动必须占用一套**互不重叠**的渠道集合。
     *       例：活动A = [wechat, alipay]，就不能再建第二个 enabled=1 包含 wechat 或 alipay 的活动。
     *       "空渠道"=不挑渠道（适用全部），只能存在一个。
     * @return array ['ok'=>bool,'msg'=>?,'id'=>?,'conflict'=>[]?]
     */
    public static function addCampaign($data)
    {
        $name  = trim((string)($data['name'] ?? ''));
        $type  = $data['type'] ?? self::TYPE_BONUS;
        $value = (float)($data['value'] ?? 0);
        $channels = $data['channels'] ?? [];
        if (!is_array($channels)) $channels = [];
        // 规范化：只保留三选一合法值，去重
        $channels = array_values(array_unique(array_filter(array_map(function($x){
            $s = (string)$x;
            return in_array($s, ['wechat','alipay','card'], true) ? $s : '';
        }, $channels), function($x){ return $x !== ''; })));
        $min = (float)($data['min_amount'] ?? 0);
        $cap = (int)($data['cap'] ?? 0);

        if ($name === '') return ['ok' => false, 'msg' => '请填写活动名称'];
        if (!in_array($type, [self::TYPE_BONUS, self::TYPE_DISCOUNT, self::TYPE_FIRST], true)) {
            return ['ok' => false, 'msg' => '活动类型不合法'];
        }
        // 折扣：0 < n ≤ 1；首充：n ≥ 1；加赠：任意数（可正可负）
        if ($type === self::TYPE_DISCOUNT && !($value > 0 && $value <= 1)) {
            return ['ok' => false, 'msg' => '无效设置：折扣比例需在 (0, 1] 之间'];
        }
        if ($type === self::TYPE_FIRST && $value < 1) {
            return ['ok' => false, 'msg' => '无效设置：首充比例不可低于 1'];
        }

        // 渠道唯一性校验：与所有「启用中」活动比对
        $conflict = self::findChannelConflict($channels, 0);
        if (!empty($conflict['conflicts'])) {
            $chnText = ['wechat'=>'微信','alipay'=>'支付宝','card'=>'卡密'];
            $detail = [];
            foreach ($conflict['conflicts'] as $row) {
                $chsLabel = array_map(function($x) use ($chnText){ return $chnText[$x] ?? $x; }, $row['mine']);
                $detail[] = '与「' . $row['name'] . '」的【' . implode('/', $chsLabel) . '】渠道冲突';
            }
            return ['ok' => false, 'msg' => '渠道已被其他启用中的活动占用：' . implode('；', $detail), 'conflict' => $conflict['conflicts']];
        }

        $id = Model::table('recharge_campaigns')->insert([
            'name'       => $name,
            'type'       => $type,
            'value'      => $value,
            'channels'   => json_encode($channels, JSON_UNESCAPED_UNICODE),
            'start_at'   => !empty($data['start_at']) ? $data['start_at'] : null,
            'end_at'     => !empty($data['end_at']) ? $data['end_at'] : null,
            'min_amount' => $min,
            'cap'        => $cap,
            'enabled'    => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => true, 'id' => $id];
    }

    /**
     * 校验「传入的 channels」与所有「启用中」活动是否冲突
     * @param array $channels 候选渠道集合（空 = 表示全部）
     * @param int $excludeId 编辑某活动自身时排除自己
     * @return array ['conflicts' => [{name, mine: channelsTheOtherAlreadyUses}]]
     */
    public static function findChannelConflict(array $channels, $excludeId = 0)
    {
        $excludeId = (int)$excludeId;
        $list = Model::table('recharge_campaigns')->where('enabled', 1)->get();
        $conflicts = [];
        $mineEmpty = empty($channels);
        foreach ($list as $c) {
            if ((int)$c['id'] === $excludeId) continue;
            $chs = json_decode($c['channels'] ?? '[]', true);
            if (!is_array($chs)) $chs = [];
            $otherEmpty = empty($chs);
            // 空 vs 空：互相冲突（都适用「全部渠道」，只能存在一个）
            if ($mineEmpty && $otherEmpty) {
                $conflicts[] = ['id' => (int)$c['id'], 'name' => $c['name'], 'mine' => $chs];
                continue;
            }
            // 我的 vs 别人（具体渠道）：只要有交集就是冲突
            if (!$mineEmpty && !$otherEmpty && array_intersect($channels, $chs)) {
                $conflicts[] = ['id' => (int)$c['id'], 'name' => $c['name'], 'mine' => array_values(array_intersect($channels, $chs))];
            }
            // 空 vs 别人：我的「全部」自然包含别人所有具体渠道 → 冲突
            if ($mineEmpty && !$otherEmpty) {
                $conflicts[] = ['id' => (int)$c['id'], 'name' => $c['name'], 'mine' => $chs];
            }
        }
        return ['conflicts' => $conflicts];
    }

    /**
     * 删除活动（仅允许停用的活动被删除）
     * @return array ['ok'=>bool,'msg'=>?]
     */
    public static function deleteCampaign($id)
    {
        $id = (int)$id;
        if ($id <= 0) return ['ok' => false, 'msg' => '无效的活动 id'];
        $row = Model::table('recharge_campaigns')->where('id', $id)->first();
        if (!$row) return ['ok' => false, 'msg' => '活动不存在'];
        if ((int)$row['enabled'] === 1) {
            return ['ok' => false, 'msg' => '启用中的活动不可删除，请先停用后再删除'];
        }
        Model::table('recharge_campaigns')->where('id', $id)->delete();
        return ['ok' => true];
    }

    /**
     * 卡密是否可物理删除：
     *   - 已作废（invalid）始终可删；
     *   - 已使用（used）须度过 7 天对账期（used_at 距今 >= 7×86400 秒）才可删；
     *   - 其余（unused / expired）不可删。
     * @param array $row recharge_cards 单行
     */
    private static function canDeleteCard(array $row)
    {
        if (($row['status'] ?? '') === 'invalid') return true;
        if (($row['status'] ?? '') === 'used') {
            $usedAt = !empty($row['used_at']) ? strtotime($row['used_at']) : 0;
            if ($usedAt > 0 && (time() - $usedAt) >= 7 * 86400) return true;
        }
        return false;
    }

    /**
     * 删除单张卡密（仅「已作废」或「已使用且度过 7 天对账期」可物理删除）
     * @return array ['ok'=>bool,'msg'=>?,'affected'=>int]
     */
    public static function deleteCard($id)
    {
        $id = (int)$id;
        if ($id <= 0) return ['ok' => false, 'msg' => '无效的卡密 id'];
        $row = Model::table('recharge_cards')->where('id', $id)->first();
        if (!$row) return ['ok' => false, 'msg' => '卡密不存在'];
        if (!self::canDeleteCard($row)) {
            if (($row['status'] ?? '') === 'used') {
                return ['ok' => false, 'msg' => '该卡密处于对账期（使用后 7 天内不可删除）'];
            }
            return ['ok' => false, 'msg' => '仅「已作废」或「已使用且超过 7 天」的卡密可删除'];
        }
        Model::table('recharge_cards')->where('id', $id)->delete();
        return ['ok' => true, 'affected' => 1];
    }

    /**
     * 批量删除卡密（仅「已作废」或「已使用超 7 天」可删，其余精确跳过）
     * @return array ['ok'=>bool,'msg'=>?,'affected'=>int,'skipped'=>int]
     */
    public static function batchDeleteCards($ids)
    {
        $ids = array_filter(array_map('intval', (array)$ids), function ($x) { return $x > 0; });
        if (empty($ids)) return ['ok' => false, 'msg' => '请选择要删除的卡密'];
        $rows = Model::table('recharge_cards')->whereIn('id', $ids)->get();
        $deleted = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            if (self::canDeleteCard($row)) {
                Model::table('recharge_cards')->where('id', (int)$row['id'])->delete();
                $deleted++;
            } else {
                $skipped++;
            }
        }
        return ['ok' => true, 'affected' => $deleted, 'skipped' => $skipped];
    }

    /**
     * 活动启用/停用切换
     * 启用时校验渠道唯一性：若与现有启用活动有冲突，拒绝启用并告知用户
     */
    public static function toggleCampaign($id, $enabled)
    {
        $id = (int)$id;
        $enabled = $enabled ? 1 : 0;
        if ($enabled === 1) {
            $row = Model::table('recharge_campaigns')->where('id', $id)->first();
            if (!$row) return false;
            $chs = json_decode($row['channels'] ?? '[]', true) ?: [];
            $conflict = self::findChannelConflict($chs, $id);
            if (!empty($conflict['conflicts'])) {
                $chnText = ['wechat'=>'微信','alipay'=>'支付宝','card'=>'卡密'];
                $detail = [];
                foreach ($conflict['conflicts'] as $row2) {
                    $chsLabel = array_map(function($x) use ($chnText){ return $chnText[$x] ?? $x; }, $row2['mine']);
                    $detail[] = '「' . $row2['name'] . '」已占用【' . implode('/', $chsLabel) . '】';
                }
                throw new \Exception('渠道已被占用：' . implode('；', $detail));
            }
        }
        Model::table('recharge_campaigns')->where('id', $id)->update(['enabled' => $enabled]);
        return true;
    }
}
