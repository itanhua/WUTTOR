<?php
/**
 * 论坛 - 数据库升级脚本
 * 用于已安装的论坛升级到新版本（新增表/字段）
 * 访问 install/upgrade.php 执行一次即可
 */
session_start();
date_default_timezone_set('Asia/Shanghai');

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('PUBLIC_PATH', ROOT_PATH . '/public');

// 未安装则跳转安装
if (!file_exists(CONFIG_PATH . '/database.php')) {
    header('Location: install/');
    exit;
}

$dbConfig = require CONFIG_PATH . '/database.php';
$prefix = $dbConfig['prefix'] ?? '';

header('Content-Type: text/html; charset=utf-8');

// 防止大批量操作（如表情升级）因执行超时导致整页空白：尽量放开时间限制并显示错误
@set_time_limit(0);
@ini_set('display_errors', '1');
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// 文件下载辅助函数：用于升级 63 / 64 把远端表情图片缓存到本地 uploads/emoji/
//   优先 curl（绝大多数生产环境都装了，且对 HTTPS/重定向支持更好）；
//   fallback 到 file_get_contents（受 allow_url_fopen 和 SSL 扩展限制，部分环境不可用）。
//   $urls 支持字符串或数组（多镜像按顺序尝试）—— 升级 63 因 GitHub raw 国内访问受限，用 jsdelivr 主源 + GitHub raw fallback。
if (!function_exists('emojiHttpGet')) {
    function emojiHttpGet($urls, $timeout = 12) {
        if (is_string($urls)) $urls = [$urls];
        foreach ($urls as $url) {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER  => true,
                    CURLOPT_FOLLOWLOCATION  => true,
                    CURLOPT_MAXREDIRS       => 3,
                    CURLOPT_TIMEOUT         => $timeout,
                    CURLOPT_CONNECTTIMEOUT  => 5,
                    CURLOPT_USERAGENT       => 'Mozilla/5.0',
                    CURLOPT_SSL_VERIFYPEER  => false,
                    CURLOPT_SSL_VERIFYHOST  => 0,
                ]);
                $body = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($body !== false && $code >= 200 && $code < 300 && strlen($body) > 100) {
                    return ['ok' => true, 'body' => $body, 'url' => $url];
                }
            } else {
                $ctx = stream_context_create([
                    'http' => ['timeout' => $timeout, 'follow_location' => 1, 'max_redirects' => 3,
                               'header' => "User-Agent: Mozilla/5.0\r\n"]
                ]);
                $body = @file_get_contents($url, false, $ctx);
                if ($body && strlen($body) > 100) {
                    return ['ok' => true, 'body' => $body, 'url' => $url];
                }
            }
        }
        return ['ok' => false, 'body' => '', 'url' => ''];
    }
}
if (!function_exists('downloadEmojiFile')) {
    function downloadEmojiFile($urls, $localPath, $timeout = 8) {
        if (is_string($urls)) $urls = [$urls];
        // 已存在且大小合理（>100B）认为成功，避免重复下载
        if (file_exists($localPath) && filesize($localPath) > 100) return true;
        $dir = dirname($localPath);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        foreach ($urls as $url) {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                $fp = fopen($localPath, 'w+b');
                curl_setopt_array($ch, [
                    CURLOPT_FILE            => $fp,
                    CURLOPT_FOLLOWLOCATION  => true,
                    CURLOPT_MAXREDIRS       => 3,
                    CURLOPT_TIMEOUT         => $timeout,
                    CURLOPT_CONNECTTIMEOUT  => 5,
                    CURLOPT_USERAGENT       => 'Mozilla/5.0',
                    CURLOPT_SSL_VERIFYPEER  => false,
                    CURLOPT_SSL_VERIFYHOST  => 0,
                ]);
                $ok = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                fclose($fp);
                if ($ok && $code >= 200 && $code < 300 && filesize($localPath) > 100) return true;
                @unlink($localPath);
            } else {
                $ctx = stream_context_create([
                    'http' => [
                        'timeout' => $timeout,
                        'follow_location' => 1,
                        'max_redirects' => 3,
                        'header' => "User-Agent: Mozilla/5.0\r\n"
                    ]
                ]);
                $content = @file_get_contents($url, false, $ctx);
                if ($content && strlen($content) > 100) {
                    file_put_contents($localPath, $content);
                    return true;
                }
            }
            if (file_exists($localPath)) @unlink($localPath);
        }
        return false;
    }
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>数据库升级</title>';
echo '<style>body{font-family:sans-serif;max-width:680px;margin:40px auto;padding:20px;line-height:1.7;color:#333;}h1{color:#ea6f5a;}.ok{color:#52c41a;}.fail{color:#f5222d;}.log{background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;font-family:monospace;font-size:13px;line-height:1.9;white-space:pre-wrap;}a.btn{display:inline-block;padding:8px 20px;background:#ea6f5a;color:#fff;text-decoration:none;border-radius:4px;margin-top:16px;}</style>';
echo '</head><body><h1>论坛数据库升级</h1><div class="log">';

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "数据库连接成功\n";

    // 升级 SQL 列表
    $upgrades = [
        // 1. certifications 表新增 form_data 字段（动态表单数据）
        "ALTER TABLE `{$prefix}certifications` ADD COLUMN `form_data` TEXT NULL COMMENT '动态表单数据JSON' AFTER `extra_note`",

        // 2. users 表新增 email_verified 字段
        "ALTER TABLE `{$prefix}users` ADD COLUMN `email_verified` TINYINT NOT NULL DEFAULT 0 COMMENT '0未验证1已验证' AFTER `email`",

        // 3. 认证项配置表
        "CREATE TABLE IF NOT EXISTS `{$prefix}certification_items` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `name` VARCHAR(50) NOT NULL COMMENT '字段名',
          `label` VARCHAR(100) NOT NULL COMMENT '显示标签',
          `type` VARCHAR(20) NOT NULL DEFAULT 'text' COMMENT 'text/textarea/image/select',
          `options` TEXT COMMENT 'select选项JSON',
          `required` TINYINT NOT NULL DEFAULT 1,
          `sort_order` INT NOT NULL DEFAULT 0,
          `status` TINYINT NOT NULL DEFAULT 1,
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 4. 邮箱验证码表
        "CREATE TABLE IF NOT EXISTS `{$prefix}email_codes` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `email` VARCHAR(100) NOT NULL,
          `code` VARCHAR(10) NOT NULL,
          `purpose` VARCHAR(20) NOT NULL COMMENT 'register/forgot/change_email',
          `expires_at` DATETIME NOT NULL,
          `used` TINYINT NOT NULL DEFAULT 0,
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_email` (`email`, `purpose`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 5. sensitive_words 表增加 scope 字段（禁用范围：username/content）
        "ALTER TABLE `{$prefix}sensitive_words` ADD COLUMN `scope` VARCHAR(20) NOT NULL DEFAULT 'all' COMMENT 'all/username/content' AFTER `level`",

        // 6. categories 表增加颜色字段和更新时间字段
        "ALTER TABLE `{$prefix}categories` ADD COLUMN `color` VARCHAR(7) DEFAULT NULL COMMENT '板块标签颜色，如 #ea6f5a' AFTER `icon`",
        "ALTER TABLE `{$prefix}categories` ADD COLUMN `updated_at` DATETIME DEFAULT NULL AFTER `created_at`",

        // 7. categories 表新增版规字段（板块页可由版主及以上权限前台编辑）
        "ALTER TABLE `{$prefix}categories` ADD COLUMN `rule` TEXT DEFAULT NULL COMMENT '版规，版主可编辑' AFTER `description`",

        // 8. category_moderators 多版主关联表
        "CREATE TABLE IF NOT EXISTS `{$prefix}category_moderators` (
          `category_id` BIGINT NOT NULL,
          `user_id` BIGINT NOT NULL,
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`category_id`, `user_id`),
          KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 9. posts 表新增 attachments 字段（附件 JSON 数组）
        "ALTER TABLE `{$prefix}posts` ADD COLUMN `attachments` TEXT NULL COMMENT '附件JSON数组：[{name,url,size}]' AFTER `images`",

        // 10. posts 表新增 is_closed 字段（帖子关闭：仅可浏览，禁止回复）
        "ALTER TABLE `{$prefix}posts` ADD COLUMN `is_closed` TINYINT NOT NULL DEFAULT 0 COMMENT '0 正常 1 已关闭（仅可浏览，禁止回复）' AFTER `collect_count`",

        // 11. categories 表新增浏览/发表角色权限字段
        "ALTER TABLE `{$prefix}categories` ADD COLUMN `browse_roles` VARCHAR(255) NULL COMMENT '允许浏览的角色，逗号分隔；为空表示所有登录/游客' AFTER `is_certification_required`",
        "ALTER TABLE `{$prefix}categories` ADD COLUMN `publish_roles` VARCHAR(255) NULL COMMENT '允许发表的角色，逗号分隔；为空表示所有登录用户' AFTER `browse_roles`",

        // 12. posts 表新增拍卖相关字段
        "ALTER TABLE `{$prefix}posts` ADD COLUMN `is_auction` TINYINT NOT NULL DEFAULT 0 COMMENT '0 普通帖 1 拍卖帖' AFTER `is_closed`",
        "ALTER TABLE `{$prefix}posts` ADD COLUMN `start_price` DECIMAL(12,2) DEFAULT NULL COMMENT '起拍价' AFTER `is_auction`",
        "ALTER TABLE `{$prefix}posts` ADD COLUMN `step_price` DECIMAL(12,2) NOT NULL DEFAULT 10.00 COMMENT '最小加价幅度' AFTER `start_price`",
        "ALTER TABLE `{$prefix}posts` ADD COLUMN `current_price` DECIMAL(12,2) DEFAULT NULL COMMENT '当前最高出价' AFTER `step_price`",
        "ALTER TABLE `{$prefix}posts` ADD COLUMN `bid_count` INT NOT NULL DEFAULT 0 COMMENT '出价人数' AFTER `current_price`",
        "ALTER TABLE `{$prefix}posts` ADD COLUMN `end_time` DATETIME DEFAULT NULL COMMENT '拍卖结拍时间' AFTER `bid_count`",
        "ALTER TABLE `{$prefix}posts` ADD COLUMN `extended_minutes` INT NOT NULL DEFAULT 0 COMMENT '累计延时分钟数' AFTER `end_time`",

        // 12.5 posts 表新增 pin_scope 字段（置顶范围：0 不置顶 / 1 本版置顶 / 2 全局置顶）
        "ALTER TABLE `{$prefix}posts` ADD COLUMN `pin_scope` TINYINT NOT NULL DEFAULT 0 COMMENT '0 不置顶 1 本版置顶 2 全局置顶' AFTER `is_pinned`",

        // 13. 拍卖出价记录表
        "CREATE TABLE IF NOT EXISTS `{$prefix}bids` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `post_id` BIGINT NOT NULL,
          `user_id` BIGINT NOT NULL,
          `price` DECIMAL(12,2) NOT NULL COMMENT '出价金额',
          `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1有效 0被超越/撤销',
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_post` (`post_id`),
          KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 14. 认证项目组表（多套认证：实名认证 / 工作认证 / 技能认证 …）
        "CREATE TABLE IF NOT EXISTS `{$prefix}certification_groups` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `name` VARCHAR(50) NOT NULL COMMENT '认证项目名称',
          `sort_order` INT NOT NULL DEFAULT 0,
          `status` TINYINT NOT NULL DEFAULT 1,
          `icon_svg` TEXT COMMENT '该认证项目独立图标SVG',
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 15. certification_items 加 group_id 字段
        "ALTER TABLE `{$prefix}certification_items` ADD COLUMN `group_id` BIGINT NOT NULL DEFAULT 1 COMMENT '所属认证项目组，1=实名认证' AFTER `id`",

        // 16. certifications 加 group_id 字段
        "ALTER TABLE `{$prefix}certifications` ADD COLUMN `group_id` BIGINT NOT NULL DEFAULT 1 COMMENT '所属认证项目组' AFTER `form_data`",

        // 17. certification_groups 加 icon_svg 字段（每组独立认证图标）
        "ALTER TABLE `{$prefix}certification_groups` ADD COLUMN `icon_svg` TEXT COMMENT '该认证项目独立图标SVG' AFTER `status`",

        // 18. certifications 表实名认证相关字段改为可空（新增的认证项目组无姓名/身份证/手机号）
        "ALTER TABLE `{$prefix}certifications` MODIFY COLUMN `real_name` VARCHAR(50) NULL COMMENT '真实姓名（仅实名认证组必填，其它组可空）'",
        "ALTER TABLE `{$prefix}certifications` MODIFY COLUMN `id_card` VARCHAR(255) NULL COMMENT '身份证号（仅实名认证组必填，其它组可空）'",
        "ALTER TABLE `{$prefix}certifications` MODIFY COLUMN `phone` VARCHAR(20) NULL COMMENT '手机号（仅实名认证组必填，其它组可空）'",

        // 19. users 加 show_cert_badges 字段（前端显示哪些认证图标，JSON 数组存 group_id；最多 3 个）
        "ALTER TABLE `{$prefix}users` ADD COLUMN `show_cert_badges` TEXT NULL COMMENT '前端展示的认证图标组ID（JSON数组，最多3个）' AFTER `certified_at`",

        // 20. 新增「站长（webmaster）」角色种子数据：roles 表存在才执行（建表脚本中也已包含此表）
        //     ——站长权限高于一切其他用户；role 字段 VARCHAR 足以容纳，无需 ALTER COLUMN

        // 21. 邀请注册功能：users 表新增 invited_by / invite_code 字段
        "ALTER TABLE `{$prefix}users` ADD COLUMN `invited_by` BIGINT DEFAULT NULL COMMENT '邀请人 users.id（邀请注册绑定）' AFTER `role`",
        "ALTER TABLE `{$prefix}users` ADD COLUMN `invite_code` VARCHAR(32) DEFAULT NULL COMMENT '注册时使用的邀请码' AFTER `invited_by`",

        // 22. 邀请注册表（邀请码 + 使用状态）
        "CREATE TABLE IF NOT EXISTS `{$prefix}invites` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `code` VARCHAR(32) NOT NULL COMMENT '邀请码（唯一）',
          `inviter_id` BIGINT NOT NULL COMMENT '邀请人 users.id',
          `inviter_role` VARCHAR(20) DEFAULT NULL COMMENT '邀请人角色快照',
          `max_uses` INT NOT NULL DEFAULT 1 COMMENT '最大可使用次数',
          `used_count` INT NOT NULL DEFAULT 0 COMMENT '已使用次数',
          `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1有效 0已停用',
          `expires_at` DATETIME DEFAULT NULL COMMENT '过期时间，NULL 表示永久有效',
          `created_at` DATETIME DEFAULT NULL,
          `updated_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_code` (`code`),
          KEY `idx_inviter` (`inviter_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 27. 「记住我」持久化登录 token 表
        "CREATE TABLE IF NOT EXISTS `{$prefix}remember_tokens` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `user_id` BIGINT NOT NULL COMMENT 'users.id',
          `token_hash` VARCHAR(64) NOT NULL COMMENT '随机 token 的 SHA-256 哈希（不存明文）',
          `expires_at` DATETIME NOT NULL COMMENT '过期时间',
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_user` (`user_id`),
          KEY `idx_token` (`token_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 28. 帖子表加 url_slug 列 + 唯一索引（伪静态 URL 用）
        // 升级器循环不识别 "Duplicate key name"，所以改用下面的 inline 幂等块（见 foreach 之后）

        // 29. 默认 permalink_structure = 'id'（之前为 'default'；自动升级到 ID 型 pretty URL）
        // 字段值由 savePermalinkSettings 写入；此处不强制，避免覆盖站长已有设置。

        // 30. 评论表加 image 列（仅顶层回复支持图片，楼中楼不支持）
        "ALTER TABLE `{$prefix}comments` ADD COLUMN `image` VARCHAR(255) DEFAULT NULL COMMENT '评论图片（仅顶层回复支持，楼中楼不支持）' AFTER `content`",

        // 33. 积分系统：users 表新增 token / byte / level 三列
        "ALTER TABLE `{$prefix}users` ADD COLUMN `token` INT NOT NULL DEFAULT 0 COMMENT '主流通积分 Token（可获取/消耗/兑换，永久不清零，最低0）' AFTER `updated_at`",
        "ALTER TABLE `{$prefix}users` ADD COLUMN `byte` INT NOT NULL DEFAULT 0 COMMENT '成长值 Byte（仅获取不可消耗，决定9级阶梯）' AFTER `token`",
        "ALTER TABLE `{$prefix}users` ADD COLUMN `level` TINYINT NOT NULL DEFAULT 1 COMMENT '当前等级（1-9）' AFTER `byte`",

        // 34. 积分流水表（唯一键防重复加分/扣减）
        "CREATE TABLE IF NOT EXISTS `{$prefix}points_log` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `user_id` BIGINT NOT NULL COMMENT 'users.id',
          `currency` VARCHAR(10) NOT NULL COMMENT 'token / byte',
          `type` VARCHAR(10) NOT NULL COMMENT 'earn / spend',
          `source` VARCHAR(40) NOT NULL COMMENT '规则 code（如 post_create / daily_sign）',
          `source_id` BIGINT DEFAULT NULL COMMENT '关联对象 id（帖子/评论/签到记录等）',
          `amount` INT NOT NULL COMMENT '变动数量（正=增加，负=扣减）',
          `balance_after` INT NOT NULL DEFAULT 0 COMMENT '变动后该币种余额',
          `remark` VARCHAR(255) DEFAULT NULL,
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_unique` (`user_id`, `currency`, `source`, `source_id`, `type`),
          KEY `idx_user` (`user_id`),
          KEY `idx_source` (`source`),
          KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 35. 积分规则表（获取/消耗动作的数值配置，后台可改，代码零硬编码）
        "CREATE TABLE IF NOT EXISTS `{$prefix}point_rules` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `code` VARCHAR(40) NOT NULL COMMENT '规则 code（如 post_create），全局唯一',
          `name` VARCHAR(100) NOT NULL COMMENT '规则显示名',
          `type` VARCHAR(10) NOT NULL COMMENT 'earn / spend',
          `currency` VARCHAR(10) NOT NULL COMMENT 'token / byte',
          `amount` INT NOT NULL DEFAULT 0 COMMENT '变动数值（earn 为正，spend 为正值代表扣减额）',
          `enabled` TINYINT NOT NULL DEFAULT 1 COMMENT '1 启用 0 停用',
          `description` VARCHAR(255) DEFAULT NULL,
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 36. 每日签到记录表（user_id + sign_date 唯一，幂等防重复签到）
        "CREATE TABLE IF NOT EXISTS `{$prefix}sign_logs` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `user_id` BIGINT NOT NULL COMMENT 'users.id',
          `sign_date` DATE NOT NULL COMMENT '签到日期（Y-m-d）',
          `streak` INT NOT NULL DEFAULT 1 COMMENT '截至当日连续签到天数',
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_user_date` (`user_id`, `sign_date`),
          KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 37. posts 表新增 self_pin_until（自助置顶到期时间，独立于管理员 pin_scope）
        "ALTER TABLE `{$prefix}posts` ADD COLUMN `self_pin_until` DATETIME DEFAULT NULL COMMENT '自助置顶到期时间（用户花 Token 购买，独立于管理员 pin_scope）' AFTER `pin_scope`",

        // 38. 自助置顶记录表（用户花 Token 按时长购买；排序时叠加管理员 pin_scope）
        "CREATE TABLE IF NOT EXISTS `{$prefix}self_pin` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `post_id` BIGINT NOT NULL COMMENT 'posts.id',
          `user_id` BIGINT NOT NULL COMMENT '下单用户（=帖子作者）',
          `hours` INT NOT NULL DEFAULT 1 COMMENT '购买时长（小时）',
          `paid_token` INT NOT NULL DEFAULT 0 COMMENT '实际扣费 Token',
          `expire_at` DATETIME NOT NULL COMMENT '置顶到期时间',
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_post` (`post_id`),
          KEY `idx_expire` (`expire_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 39. 打赏流水表（双向：打赏者 spend + 受赏者 earn，同一事务内完成）
        "CREATE TABLE IF NOT EXISTS `{$prefix}reward_log` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `post_id` BIGINT NOT NULL COMMENT '被打赏的帖子',
          `comment_id` BIGINT DEFAULT NULL COMMENT '若为评论打赏则填评论 id',
          `from_uid` BIGINT NOT NULL COMMENT '打赏者',
          `to_uid` BIGINT NOT NULL COMMENT '受赏者',
          `token` INT NOT NULL DEFAULT 0 COMMENT '打赏 Token 数量',
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_post` (`post_id`),
          KEY `idx_from` (`from_uid`),
          KEY `idx_to` (`to_uid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    $success = 0;
    $skipped = 0;
    foreach ($upgrades as $sql) {
        try {
            $pdo->exec($sql);
            echo "<span class='ok'>[OK]</span> " . substr($sql, 0, 70) . "...\n";
            $success++;
        } catch (PDOException $e) {
            // 字段已存在则跳过
            if (strpos($e->getMessage(), 'Duplicate column') !== false || $e->getCode() == '42S21') {
                echo "<span style='color:#bbb;'>[跳过]</span> 字段已存在: " . substr($sql, 0, 50) . "...\n";
                $skipped++;
            } else {
                echo "<span class='fail'>[失败]</span> " . $e->getMessage() . "\n";
            }
        }
    }

    // 28. 帖子表加 url_slug 列 + 唯一索引（伪静态 URL 用）—— 幂等版：
    //     单独抽出来是因为升级器循环只识别 "Duplicate column" 错误，不会识别
    //     "Duplicate key name"。这里先查 INFORMATION_SCHEMA 再决定要不要执行。
    try {
        echo "<strong>[升级 28] 帖子表加 url_slug 列 + 唯一索引...</strong>\n";
        $tbl = $prefix . 'posts';

        // 1) 字段
        $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'url_slug'");
        $colStmt->execute([$tbl]);
        $colExists = (int)$colStmt->fetchColumn() > 0;

        if ($colExists) {
            echo "<span style='color:#bbb;'>[跳过]</span> 字段已存在: ALTER TABLE `{$tbl}` ADD COLUMN `url_slug` ...\n";
            $skipped++;
        } else {
            $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `url_slug` VARCHAR(200) DEFAULT NULL COMMENT 'URL slug（标题转 ASCII；编辑标题不更新以保外链稳定）' AFTER `last_reply_at`");
            echo "<span class='ok'>[OK]</span> 已添加 posts.url_slug 列\n";
            $success++;
        }

        // 2) 唯一索引
        $idxStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = 'uk_url_slug'");
        $idxStmt->execute([$tbl]);
        $idxExists = (int)$idxStmt->fetchColumn() > 0;

        if ($idxExists) {
            echo "<span style='color:#bbb;'>[跳过]</span> 索引已存在: ALTER TABLE `{$tbl}` ADD UNIQUE KEY `uk_url_slug` ...\n";
            $skipped++;
        } else {
            $pdo->exec("ALTER TABLE `{$tbl}` ADD UNIQUE KEY `uk_url_slug` (`url_slug`)");
            echo "<span class='ok'>[OK]</span> 已添加 posts.uk_url_slug 唯一索引\n";
            $success++;
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 28（url_slug 列/索引）: " . $e->getMessage() . "\n";
    }

    // 29. 默认 permalink_structure = 'id'（之前为 'default'；自动升级到 ID 型 pretty URL）
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$prefix}roles` WHERE code = 'webmaster'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO `{$prefix}roles` (code, name, description, created_at) VALUES ('webmaster', '站长', '权限最高，可管理所有用户；自身 role/status 不可改', ?)")
                ->execute([date('Y-m-d H:i:s')]);
            echo "<span class='ok'>[OK]</span> 已初始化角色「站长（webmaster）」\n";
            $success++;
        } else {
            echo "<span style='color:#bbb;'>[跳过]</span> 角色「站长（webmaster）」已存在\n";
            $skipped++;
        }
    } catch (PDOException $e) {
        // 表不存在（极旧库）则跳过，不阻塞整体升级
        echo "<span class='fail'>[失败]</span> 初始化站长角色: " . $e->getMessage() . "\n";
    }

    // 初始化默认认证项目组「实名认证」id=1（如果还没有任何组）
    $groupCount = $pdo->query("SELECT COUNT(*) FROM `{$prefix}certification_groups`")->fetchColumn();
    if ($groupCount == 0) {
        $stmt = $pdo->prepare("INSERT INTO `{$prefix}certification_groups` (name, sort_order, status, created_at) VALUES (?, 0, 1, ?)");
        $stmt->execute(['实名认证', date('Y-m-d H:i:s')]);
        echo "<span class='ok'>[OK]</span> 已初始化默认认证项目组「实名认证」\n";
        $success++;
    }

    // 21. 把 id=1 的用户提升为站长（webmaster）—— 幂等执行
    try {
        $userOne = $pdo->prepare("SELECT id, username, role FROM `{$prefix}users` WHERE id = 1");
        $userOne->execute();
        $row = $userOne->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo "<span style='color:#bbb;'>[跳过]</span> 用户 id=1 不存在，跳过站长提升\n";
            $skipped++;
        } elseif ($row['role'] === 'webmaster') {
            echo "<span style='color:#bbb;'>[跳过]</span> 用户 id=1 ({$row['username']}) 已是站长\n";
            $skipped++;
        } else {
            $oldRole = $row['role'];
            $pdo->prepare("UPDATE `{$prefix}users` SET role = 'webmaster' WHERE id = 1")
                ->execute();
            echo "<span class='ok'>[OK]</span> 已将用户 id=1 ({$row['username']}) 从「{$oldRole}」提升为「站长（webmaster）」\n";
            $success++;
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 提升用户 id=1 为站长: " . $e->getMessage() . "\n";
    }

    // 22. 初始化各角色的默认权限矩阵（role_permissions）。
    //     切换为"真正查 role_permissions 表"判权后，必须为各角色准备好默认勾选，
    //     否则一旦执行该版本会全部无权限。
    //     默认策略：
    //       - webmaster：全部权限（`Auth::can()` 站长分支直接返回 true，无需此处）
    //       - super_admin：全部权限（保持既有 * 默认语义）
    //       - admin：全部权限（与既有"管理员=*"默认一致）
    //       - moderator：访问/浏览/发布/编辑自己/删除自己 + 板块级权限 + 评论相关 + 资料/social
    //       - certified_user / user：保留既有规则的子集（post.view/create/edit_own/delete_own/comment.create/certification.apply 等）
    //       - guest：仅 post.view / comment.view / search
    //     幂等：已存在的 (role_id, permission_id) 不会重复插入。
    try {
        echo "<strong>[升级 22] 初始化各角色默认权限矩阵（role_permissions）...</strong>\n";

        // 拿出所有 roles.id 与 code + 所有 permissions.id 与 code
        $allRoles = $pdo->query("SELECT id, code FROM `{$prefix}roles`")->fetchAll(PDO::FETCH_ASSOC);
        $allPerms = $pdo->query("SELECT id, code FROM `{$prefix}permissions`")->fetchAll(PDO::FETCH_ASSOC);
        $roleIdByCode = [];
        foreach ($allRoles as $r) $roleIdByCode[$r['code']] = (int)$r['id'];
        $permIdByCode = [];
        foreach ($allPerms as $p) $permIdByCode[$p['code']] = (int)$p['id'];

        $inserted = 0;
        $roleDefaults = [
            'super_admin' => array_keys($permIdByCode),
            'admin'       => array_keys($permIdByCode),
            'moderator'   => [
                'post.view', 'post.create', 'post.edit_own', 'post.delete_own',
                'post.pin_section', 'post.essence_section', 'post.delete_section', 'post.move_section',
                'comment.create', 'comment.delete_section',
                'certification.apply', 'search',
                'report.create', 'social.message',
                'profile.edit', 'notification.view',
                'category.view', 'user.view_public',
            ],
            'certified_user' => [
                'post.view', 'post.create', 'post.edit_own', 'post.delete_own',
                'comment.create', 'comment.delete_own',
                'certification.apply', 'search',
                'report.create', 'social.message', 'social.like', 'social.collect', 'social.follow',
                'profile.edit', 'notification.view', 'certification.badge',
                'category.view', 'category.certified_post', 'user.view_public',
            ],
            'user' => [
                'post.view', 'post.create', 'post.edit_own', 'post.delete_own',
                'comment.create', 'comment.delete_own',
                'certification.apply', 'search',
                'report.create', 'social.message', 'social.like', 'social.collect', 'social.follow',
                'profile.edit', 'notification.view',
                'category.view', 'user.view_public',
            ],
            'guest' => [
                'post.view', 'comment.view', 'category.view', 'user.view_public', 'search',
            ],
            // 'webmaster' 由 Auth::can() 站长分支直接返回 true，无需写入此处
        ];

        $insertStmt = $pdo->prepare("INSERT IGNORE INTO `{$prefix}role_permissions` (role_id, permission_id) VALUES (?, ?)");
        foreach ($roleDefaults as $roleCode => $permCodes) {
            if (!isset($roleIdByCode[$roleCode])) continue;
            $rid = $roleIdByCode[$roleCode];
            foreach ($permCodes as $code) {
                if (!isset($permIdByCode[$code])) continue;
                $pid = $permIdByCode[$code];
                $insertStmt->execute([$rid, $pid]);
                $inserted += $insertStmt->rowCount();
            }
            echo "<span class='ok'>[OK]</span> 角色 <code>{$roleCode}</code> 默认权限已写入（" . count(array_intersect($permCodes, array_keys($permIdByCode))) . " 项）\n";
        }
        // webmaster: 即便 Auth::can 已豁免，仍写入全部 permissions，便于"角色权限"页展示一致
        if (isset($roleIdByCode['webmaster'])) {
            foreach ($permIdByCode as $pid) {
                $insertStmt->execute([$roleIdByCode['webmaster'], $pid]);
                $inserted += $insertStmt->rowCount();
            }
            echo "<span class='ok'>[OK]</span> 角色 <code>webmaster</code> 默认全部权限已写入\n";
        }
        echo "<span class='ok'>共补/新写入 {$inserted} 行 role_permissions</span>\n";
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 初始化角色权限矩阵: " . $e->getMessage() . "\n";
    }

    // 初始化默认认证项（如果没有）
    $count = $pdo->query("SELECT COUNT(*) FROM `{$prefix}certification_items`")->fetchColumn();
    if ($count == 0) {
        $now = date('Y-m-d H:i:s');
        $items = [
            ['real_name', '真实姓名', 'text', '', 1, 1],
            ['id_card', '身份证号', 'text', '', 1, 2],
            ['phone', '联系电话', 'text', '', 1, 3],
            ['id_card_front', '身份证正面照', 'image', '', 1, 4],
            ['id_card_back', '身份证反面照', 'image', '', 1, 5],
            ['hand_photo', '手持身份证照', 'image', '', 1, 6],
            ['extra_note', '补充说明', 'textarea', '', 0, 7],
        ];
        foreach ($items as $it) {
            $stmt = $pdo->prepare("INSERT INTO `{$prefix}certification_items` (name,label,type,options,required,sort_order,status,created_at) VALUES (?,?,?,?,?,?,1,?)");
            $stmt->execute([$it[0], $it[1], $it[2], $it[3], $it[4], $it[5], $now]);
        }
        echo "<span class='ok'>[OK]</span> 已初始化 7 个默认认证项\n";
        $success++;
    }

    // 初始化默认认证图标 SVG（settings 表）
    $badgeExists = $pdo->prepare("SELECT COUNT(*) FROM `{$prefix}settings` WHERE key_name = ?");
    $badgeExists->execute(['cert_badge_svg']);
    if ($badgeExists->fetchColumn() == 0) {
        $defaultBadge = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="11" fill="#ea6f5a"/><path d="M9.5 16.2l-3-3 1.4-1.4 1.6 1.6 5.6-5.6 1.4 1.4z" fill="#fff"/></svg>';
        $stmt = $pdo->prepare("INSERT INTO `{$prefix}settings` (key_name,value,description,updated_at) VALUES ('cert_badge_svg',?, '认证图标SVG',?)");
        $stmt->execute([$defaultBadge, date('Y-m-d H:i:s')]);
        echo "<span class='ok'>[OK]</span> 已初始化默认认证图标\n";
        $success++;
    }

    // 7. users 表字段完整性检查：缺失的关键字段自动补齐（避免旧库注册/重置密码报 500 Unknown column）
    $userCols = [];
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
    $stmt->execute([$dbConfig['database'], $prefix . 'users']);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $col) $userCols[] = $col;

    $userFields = [
        'email_verified' => "ALTER TABLE `{$prefix}users` ADD COLUMN `email_verified` TINYINT NOT NULL DEFAULT 0 COMMENT '0未验证1已验证' AFTER `email`",
        'phone'          => "ALTER TABLE `{$prefix}users` ADD COLUMN `phone` VARCHAR(20) DEFAULT NULL AFTER `email_verified`",
        'avatar'         => "ALTER TABLE `{$prefix}users` ADD COLUMN `avatar` VARCHAR(255) DEFAULT NULL AFTER `password_hash`",
        'nickname'       => "ALTER TABLE `{$prefix}users` ADD COLUMN `nickname` VARCHAR(50) DEFAULT NULL AFTER `avatar`",
        'bio'            => "ALTER TABLE `{$prefix}users` ADD COLUMN `bio` TEXT AFTER `nickname`",
        'is_certified'   => "ALTER TABLE `{$prefix}users` ADD COLUMN `is_certified` TINYINT NOT NULL DEFAULT 0 COMMENT '0未认证1已认证' AFTER `role`",
        'certified_at'   => "ALTER TABLE `{$prefix}users` ADD COLUMN `certified_at` DATETIME DEFAULT NULL COMMENT '认证时间' AFTER `is_certified`",
        'last_login_at'  => "ALTER TABLE `{$prefix}users` ADD COLUMN `last_login_at` DATETIME DEFAULT NULL AFTER `status`",
        'updated_at'     => "ALTER TABLE `{$prefix}users` ADD COLUMN `updated_at` DATETIME DEFAULT NULL AFTER `created_at`",
    ];
    foreach ($userFields as $col => $sql) {
        if (in_array($col, $userCols)) {
            echo "<span style='color:#bbb;'>[跳过]</span> users.{$col} 字段已存在\n";
            $skipped++;
            continue;
        }
        try {
            $pdo->exec($sql);
            echo "<span class='ok'>[OK]</span> users 表新增字段 {$col}\n";
            $success++;
        } catch (PDOException $e) {
            echo "<span class='fail'>[失败]</span> users.{$col}: " . $e->getMessage() . "\n";
        }
    }

    // 23. posts 表新增 last_reply_at 字段（最近一次被回复（含楼中楼）的时间），用于首页/板块页置顶贴之下的自动顶起排序
    $postsCols = [];
    try {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
        $stmt->execute([$dbConfig['database'], $prefix . 'posts']);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $col) $postsCols[] = $col;
    } catch (PDOException $e) {}
    if (!in_array('last_reply_at', $postsCols)) {
        try {
            $pdo->exec("ALTER TABLE `{$prefix}posts` ADD COLUMN `last_reply_at` DATETIME DEFAULT NULL COMMENT '最近一次回复时间（含楼中楼），用于置顶贴之下的自动顶起排序' AFTER `extended_minutes`");
            $pdo->exec("ALTER TABLE `{$prefix}posts` ADD INDEX `idx_last_reply` (`last_reply_at`)");
            echo "<span class='ok'>[OK]</span> posts 表新增字段 last_reply_at + 索引\n";
            $success++;
        } catch (PDOException $e) {
            echo "<span class='fail'>[失败]</span> posts.last_reply_at: " . $e->getMessage() . "\n";
        }
    } else {
        echo "<span style='color:#bbb;'>[跳过]</span> posts.last_reply_at 字段已存在\n";
        $skipped++;
    }
    // 24. 把已有帖的 last_reply_at 初始化为 created_at（避免历史帖子全部为 NULL 被排到末尾）
    try {
        $cnt = $pdo->exec("UPDATE `{$prefix}posts` SET last_reply_at = created_at WHERE last_reply_at IS NULL");
        echo "<span class='ok'>[OK]</span> 已有帖 last_reply_at 回填（影响 {$cnt} 行）\n";
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 回填 last_reply_at: " . $e->getMessage() . "\n";
    }

    // 25. notifications 表新增 is_system_notification 字段，区分"系统通知广播"vs"用户行为触发的通知"
    $notifCols = [];
    try {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
        $stmt->execute([$dbConfig['database'], $prefix . 'notifications']);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $col) $notifCols[] = $col;
    } catch (PDOException $e) {}
    if (!in_array('is_system_notification', $notifCols)) {
        try {
            $pdo->exec("ALTER TABLE `{$prefix}notifications` ADD COLUMN `is_system_notification` TINYINT NOT NULL DEFAULT 0 COMMENT '站长通过系统通知菜单广播的官方通知=1' AFTER `is_read`");
            $pdo->exec("ALTER TABLE `{$prefix}notifications` ADD INDEX `idx_system` (`is_system_notification`)");
            echo "<span class='ok'>[OK]</span> notifications 表新增字段 is_system_notification + 索引\n";
            $success++;
        } catch (PDOException $e) {
            echo "<span class='fail'>[失败]</span> notifications.is_system_notification: " . $e->getMessage() . "\n";
        }
    } else {
        echo "<span style='color:#bbb;'>[跳过]</span> notifications.is_system_notification 字段已存在\n";
        $skipped++;
    }

    // 26. system_notification_logs 表（系统通知发送历史，站长可追溯）
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}system_notification_logs` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `title` VARCHAR(100) NOT NULL COMMENT '通知摘要（首行内容）',
          `content` VARCHAR(255) NOT NULL,
          `link` VARCHAR(255) DEFAULT NULL,
          `target_type` VARCHAR(20) NOT NULL COMMENT 'all=全员 / role=角色组 / users=指定用户',
          `target_value` TEXT COMMENT '目标值（角色 code 列表 或 user_id 列表，JSON）',
          `sender_id` BIGINT NOT NULL,
          `sent_count` INT NOT NULL DEFAULT 0 COMMENT '本次发送的接收人数',
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_sender` (`sender_id`),
          KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "<span class='ok'>[OK]</span> 创建 system_notification_logs 表\n";
        $success++;
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> system_notification_logs: " . $e->getMessage() . "\n";
    }

    // 31. comments.image 字段从 VARCHAR(255) 升级为 TEXT（多图 JSON 数组，最长 9 张 ≈ 9*~64 字符）
    try {
        echo "<strong>[升级 31] comments.image 升级为 TEXT（支持多图 JSON 数组）...</strong>\n";
        $tbl = $prefix . 'comments';

        $colStmt = $pdo->prepare("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'image'");
        $colStmt->execute([$tbl]);
        $currentType = $colStmt->fetchColumn();

        if ($currentType === false) {
            // 极旧库连 image 列都没有，直接补 TEXT
            $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `image` TEXT DEFAULT NULL COMMENT '评论图片 JSON（仅顶层回复支持，最多 9 张）' AFTER `content`");
            echo "<span class='ok'>[OK]</span> 已添加 comments.image (TEXT)\n";
            $success++;
        } elseif (strtolower((string)$currentType) === 'text') {
            echo "<span style='color:#bbb;'>[跳过]</span> comments.image 已是 TEXT 类型\n";
            $skipped++;
        } else {
            $pdo->exec("ALTER TABLE `{$tbl}` MODIFY COLUMN `image` TEXT DEFAULT NULL COMMENT '评论图片 JSON（仅顶层回复支持，最多 9 张）'");
            echo "<span class='ok'>[OK]</span> comments.image 已从 {$currentType} 升级为 TEXT\n";
            $success++;
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 31 (comments.image 改 TEXT): " . $e->getMessage() . "\n";
    }

    // 32. 图片上传 / 附件上传 权限迁移到角色权限矩阵
    //     - 新增 image.upload / attachment.upload 两条权限码
    //     - 把旧 settings.allow_image_roles / allow_attach_roles 读出来，按角色码写入 role_permissions
    //     - 旧 settings 缺失时使用与原默认行为一致的回退集合：
    //         image.upload    → 所有内置登录角色（user/certified_user/moderator/admin/super_admin）
    //         attachment.upload→ 不含 user（与历史默认一致）
    //     - 最后删除旧 settings 行，避免 can_upload_image() 的过渡回退误命中
    try {
        echo "<strong>[升级 32] 图片/附件上传权限迁移到角色权限矩阵...</strong>\n";

        $now = date('Y-m-d H:i:s');
        $newPerms = [
            ['图片上传', 'image.upload', '上传'],
            ['附件上传', 'attachment.upload', '上传'],
        ];
        $insertPerm = $pdo->prepare("INSERT IGNORE INTO `{$prefix}permissions` (name, code, module, created_at) VALUES (?, ?, ?, ?)");
        foreach ($newPerms as $np) {
            $insertPerm->execute([$np[0], $np[1], $np[2], $now]);
        }
        echo "<span class='ok'>[OK]</span> 已确保 image.upload / attachment.upload 权限码存在\n";

        // 读旧 settings（缺失时使用默认回退集合）
        $imageRolesOld = null;
        $attachRolesOld = null;
        try {
            $imgRow = $pdo->prepare("SELECT `value` FROM `{$prefix}settings` WHERE key_name = 'allow_image_roles'");
            $imgRow->execute();
            $imageRolesOld = $imgRow->fetchColumn();
            $attRow = $pdo->prepare("SELECT `value` FROM `{$prefix}settings` WHERE key_name = 'allow_attach_roles'");
            $attRow->execute();
            $attachRolesOld = $attRow->fetchColumn();
        } catch (PDOException $e) {}

        $imageRoles = ($imageRolesOld !== false && $imageRolesOld !== null && $imageRolesOld !== '')
            ? array_values(array_filter(array_map('trim', explode(',', (string)$imageRolesOld))))
            : ['user', 'certified_user', 'moderator', 'admin', 'super_admin'];
        $attachRoles = ($attachRolesOld !== false && $attachRolesOld !== null && $attachRolesOld !== '')
            ? array_values(array_filter(array_map('trim', explode(',', (string)$attachRolesOld))))
            : ['certified_user', 'moderator', 'admin', 'super_admin'];

        // 把虚拟 'certified' 角色码展开为 certified_user
        $expand = function ($roles) {
            $out = [];
            foreach ($roles as $r) {
                if ($r === 'certified') { $out[] = 'certified_user'; }
                else { $out[] = $r; }
            }
            return array_values(array_unique($out));
        };
        $imageRoles = $expand($imageRoles);
        $attachRoles = $expand($attachRoles);

        $roleIdByCode = [];
        foreach ($pdo->query("SELECT id, code FROM `{$prefix}roles`")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $roleIdByCode[(string)$r['code']] = (int)$r['id'];
        }
        $imagePermId  = (int)$pdo->query("SELECT id FROM `{$prefix}permissions` WHERE code = 'image.upload'")->fetchColumn();
        $attachPermId = (int)$pdo->query("SELECT id FROM `{$prefix}permissions` WHERE code = 'attachment.upload'")->fetchColumn();
        $insertRP = $pdo->prepare("INSERT IGNORE INTO `{$prefix}role_permissions` (role_id, permission_id) VALUES (?, ?)");
        $rpWritten = 0;
        foreach ($imageRoles as $rc) {
            if (!isset($roleIdByCode[$rc]) || !$imagePermId) continue;
            $insertRP->execute([$roleIdByCode[$rc], $imagePermId]);
            $rpWritten += $insertRP->rowCount();
        }
        foreach ($attachRoles as $rc) {
            if (!isset($roleIdByCode[$rc]) || !$attachPermId) continue;
            $insertRP->execute([$roleIdByCode[$rc], $attachPermId]);
            $rpWritten += $insertRP->rowCount();
        }
        echo "<span class='ok'>[OK]</span> image.upload → 角色：" . implode(', ', $imageRoles) . "\n";
        echo "<span class='ok'>[OK]</span> attachment.upload → 角色：" . implode(', ', $attachRoles) . "\n";
        echo "<span class='ok'>共写入 {$rpWritten} 行 role_permissions</span>\n";

        // 清理旧 settings（升级后 can_upload_image() 的回退路径会自然失效，不再需要这两行）
        try {
            $pdo->prepare("DELETE FROM `{$prefix}settings` WHERE key_name IN ('allow_image_roles', 'allow_attach_roles')")
                ->execute();
            echo "<span class='ok'>[OK]</span> 已清理旧的 allow_image_roles / allow_attach_roles 设置\n";
        } catch (PDOException $e) {
            echo "<span style='color:#bbb;'>[跳过]</span> 清理旧设置失败（不影响主流程）: " . $e->getMessage() . "\n";
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 32 (图片/附件权限迁移): " . $e->getMessage() . "\n";
    }

    // 33. 初始化默认积分规则（point_rules）—— 幂等（INSERT IGNORE）
    try {
        echo "<strong>[升级 33] 初始化默认积分规则（point_rules）...</strong>\n";
        $now = date('Y-m-d H:i:s');
        $defaultRules = [
            ['register',          '注册奖励',     'earn', 'token', 50, '新用户注册'],
            ['register_byte',     '注册成长值',   'earn', 'byte',  20, '新用户注册'],
            ['post_create',       '发帖',         'earn', 'token', 5,  '发布主题帖'],
            ['post_create_byte',  '发帖成长值',   'earn', 'byte',  10, '发布主题帖'],
            ['comment_create',    '评论',         'earn', 'token', 2,  '发表评论'],
            ['comment_create_byte','评论成长值',  'earn', 'byte',  5,  '发表评论'],
            ['reply_create',      '楼中楼回复',   'earn', 'token', 1,  '发表楼中楼'],
            ['reply_create_byte', '楼中楼成长值', 'earn', 'byte',  2,  '发表楼中楼'],
            ['daily_sign',        '每日签到',     'earn', 'token', 3,  '每日签到'],
            ['daily_sign_byte',   '每日签到成长值','earn', 'byte',  3,  '每日签到'],
            ['post_liked',        '被点赞',       'earn', 'token', 1,  '帖子/评论被点赞'],
            ['post_liked_byte',   '被点赞成长值', 'earn', 'byte',  1,  '帖子/评论被点赞'],
            ['certified',         '通过认证',     'earn', 'token', 30, '通过认证审核'],
            ['certified_byte',    '认证成长值',   'earn', 'byte',  50, '通过认证审核'],
            ['invite_success',    '邀请成功',     'earn', 'token', 10, '邀请用户注册成功'],
            // 消耗类规则（spend 为正值 = 扣减额；数值后台可改，代码零硬编码）
            ['self_pin',          '自助置顶',     'spend', 'token', 1000, '自助置顶每小时消耗 Token（按时长=小时×费率）'],
            ['cert_submit',       '认证提交',     'spend', 'token', 600,  '提交认证申请消耗 Token'],
        ];
        $insRule = $pdo->prepare("INSERT IGNORE INTO `{$prefix}point_rules` (code,name,type,currency,amount,enabled,description,created_at) VALUES (?,?,?,?,?,1,?,?)");
        $cnt = 0;
        foreach ($defaultRules as $r) {
            $insRule->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $now]);
            $cnt += $insRule->rowCount();
        }
        if ($cnt > 0) { echo "<span class='ok'>[OK]</span> 已写入 {$cnt} 条默认积分规则\n"; $success++; }
        else { echo "<span style='color:#bbb;'>[跳过]</span> 默认积分规则已存在\n"; $skipped++; }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 初始化默认积分规则: " . $e->getMessage() . "\n";
    }

    // 40. 新增「自助置顶」权限码（post.self_pin），并授权给登录角色
    try {
        echo "<strong>[升级 40] 新增「自助置顶」权限并授权给登录角色...</strong>\n";
        $now = date('Y-m-d H:i:s');
        $pdo->prepare("INSERT IGNORE INTO `{$prefix}permissions` (name, code, module, created_at) VALUES (?, ?, ?, ?)")
            ->execute(['自助置顶', 'post.self_pin', '帖子', $now]);
        $permId = (int)$pdo->query("SELECT id FROM `{$prefix}permissions` WHERE code = 'post.self_pin'")->fetchColumn();
        // 授权给所有登录角色（不含 guest）
        $grantRoles = ['user', 'certified_user', 'moderator', 'admin', 'super_admin'];
        $roleIdByCode = [];
        foreach ($pdo->query("SELECT id, code FROM `{$prefix}roles`")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $roleIdByCode[(string)$r['code']] = (int)$r['id'];
        }
        $insRP = $pdo->prepare("INSERT IGNORE INTO `{$prefix}role_permissions` (role_id, permission_id) VALUES (?, ?)");
        $rpWritten = 0;
        foreach ($grantRoles as $rc) {
            if (!isset($roleIdByCode[$rc]) || !$permId) continue;
            $insRP->execute([$roleIdByCode[$rc], $permId]);
            $rpWritten += $insRP->rowCount();
        }
        echo "<span class='ok'>[OK]</span> post.self_pin 权限已确保存在，已授权给：" . implode(', ', $grantRoles) . "（{$rpWritten} 行）\n";
        $success++;
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 40（自助置顶权限）: " . $e->getMessage() . "\n";
    }

    // 41. point_rules 增加 daily_cap（单日获取上限），并写入默认每日上限
    try {
        echo "<strong>[升级 41] point_rules 增加 daily_cap 字段并写入默认每日上限...</strong>\n";
        $tbl = $prefix . 'point_rules';
        $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'daily_cap'");
        $colStmt->execute([$tbl]);
        if ((int)$colStmt->fetchColumn() > 0) {
            echo "<span style='color:#bbb;'>[跳过]</span> point_rules.daily_cap 字段已存在\n";
            $skipped++;
        } else {
            $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `daily_cap` INT NOT NULL DEFAULT 0 COMMENT '单日获取上限（仅 earn 生效，0=不限）' AFTER `enabled`");
            echo "<span class='ok'>[OK]</span> 已添加 point_rules.daily_cap 列\n";
            $success++;
        }
        // 写入默认每日上限（幂等：仅对仍为 0 的按文档默认值补齐，已自定义的不覆盖）
        // 文档「单日上限」：签到1 / 评论20 / 楼中楼15 / 发帖5 / 被收藏50（被收藏 earn 源未实现，故仅配置已落地的 4 类）
        $caps = [
            'daily_sign'          => 1,
            'daily_sign_byte'     => 1,
            'comment_create'      => 20,
            'comment_create_byte' => 20,
            'reply_create'        => 15,
            'reply_create_byte'   => 15,
            'post_create'         => 5,
            'post_create_byte'    => 5,
        ];
        $upd = $pdo->prepare("UPDATE `{$tbl}` SET daily_cap = ? WHERE code = ? AND daily_cap = 0");
        $capWritten = 0;
        foreach ($caps as $code => $cap) {
            $upd->execute([$cap, $code]);
            $capWritten += $upd->rowCount();
        }
        echo "<span class='ok'>[OK]</span> 已写入/补齐 {$capWritten} 条每日上限配置\n";
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 41（point_rules daily_cap）: " . $e->getMessage() . "\n";
    }

    // 42. users 增加 points_banned_until（风控频控封禁到期时间）
    try {
        echo "<strong>[升级 42] users 增加 points_banned_until 字段...</strong>\n";
        $tbl = $prefix . 'users';
        $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'points_banned_until'");
        $colStmt->execute([$tbl]);
        if ((int)$colStmt->fetchColumn() > 0) {
            echo "<span style='color:#bbb;'>[跳过]</span> users.points_banned_until 字段已存在\n";
            $skipped++;
        } else {
            $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `points_banned_until` DATETIME DEFAULT NULL COMMENT '积分获取封禁到期时间（风控高频触发后封禁至当日23:59:59）' AFTER `level`");
            echo "<span class='ok'>[OK]</span> 已添加 users.points_banned_until 列\n";
            $success++;
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 42（users points_banned_until）: " . $e->getMessage() . "\n";
    }

    // 43. 风控配置默认值（settings 表，缺失才写，不覆盖站长已改）
    try {
        echo "<strong>[升级 43] 初始化风控配置默认值...</strong>\n";
        $riskDefaults = [
            'risk_newbie_hours'  => ['24', '新注册账号风控时长（小时）：该时间内单日积分获取上限减半'],
            'risk_newbie_factor' => ['0.5', '新号积分额度系数（单日上限乘以该值，0.5=减半）'],
            'risk_burst_count'   => ['10', '高频熔断阈值：同一来源在 burst_window 秒内达到该次数即暂停当日获取'],
            'risk_burst_window'  => ['60', '高频熔断时间窗（秒）'],
        ];
        $insSet = $pdo->prepare("INSERT IGNORE INTO `{$prefix}settings` (key_name, value, description, updated_at) VALUES (?, ?, ?, ?)");
        $now = date('Y-m-d H:i:s');
        $written = 0;
        foreach ($riskDefaults as $k => $v) {
            $insSet->execute([$k, $v[0], $v[1], $now]);
            $written += $insSet->rowCount();
        }
        if ($written > 0) { echo "<span class='ok'>[OK]</span> 已写入 {$written} 条风控配置\n"; $success++; }
        else { echo "<span style='color:#bbb;'>[跳过]</span> 风控配置已存在\n"; $skipped++; }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 43（风控配置）: " . $e->getMessage() . "\n";
    }

    // 44. point_rules 增加 amount_min / amount_max（随机区间发放）
    try {
        echo "<strong>[升级 44] point_rules 增加 amount_min / amount_max 字段（随机区间发放）...</strong>\n";
        $tbl = $prefix . 'point_rules';
        $added = 0;
        foreach (['amount_min' => '随机区间下限（仅 earn 生效；0 或 max<min 表示用固定 amount）',
                   'amount_max' => '随机区间上限（需>=amount_min>0 才启用随机发放）'] as $col => $comment) {
            $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $colStmt->execute([$tbl, $col]);
            if ((int)$colStmt->fetchColumn() > 0) {
                echo "<span style='color:#bbb;'>[跳过]</span> point_rules.{$col} 字段已存在\n";
                $skipped++;
            } else {
                $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `{$col}` INT NOT NULL DEFAULT 0 COMMENT '{$comment}' AFTER `daily_cap`");
                echo "<span class='ok'>[OK]</span> 已添加 point_rules.{$col} 列\n";
                $added++;
            }
        }
        if ($added > 0) $success++;
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 44（point_rules 随机区间列）: " . $e->getMessage() . "\n";
    }

    // 45. posts / comments 增加 deleted_at（回收站）
    try {
        echo "<strong>[升级 45] posts / comments 增加 deleted_at 字段（回收站）...</strong>\n";
        $added = 0;
        foreach ([$prefix . 'posts', $prefix . 'comments'] as $tbl) {
            $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'deleted_at'");
            $colStmt->execute([$tbl]);
            if ((int)$colStmt->fetchColumn() > 0) {
                echo "<span style='color:#bbb;'>[跳过]</span> {$tbl}.deleted_at 已存在\n";
                $skipped++;
            } else {
                $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `deleted_at` DATETIME DEFAULT NULL COMMENT '回收站：进入删除态的时间戳，满7天自动清空'");
                echo "<span class='ok'>[OK]</span> 已添加 {$tbl}.deleted_at 列\n";
                $added++;
            }
        }
        if ($added > 0) $success++;
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 45（回收站 deleted_at 列）: " . $e->getMessage() . "\n";
    }

    // 46. 用户等级表（user_levels）+ 默认 9 行等级（后台可自定义）
    try {
        echo "<strong>[升级 46] 创建 user_levels 表 + 初始化默认 9 级阶梯...</strong>\n";
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}user_levels` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `level` INT NOT NULL COMMENT '等级数字（唯一）',
          `name` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '等级名称',
          `byte_required` INT NOT NULL DEFAULT 0 COMMENT '所需 Byte 成长值',
          `bonus_factor` DECIMAL(4,2) NOT NULL DEFAULT 1.00 COMMENT '等级加成（1.00=无加成，1.10=+10%，1.20=+20%）',
          `privilege_text` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '等级特权描述（自由文本）',
          `enabled` TINYINT NOT NULL DEFAULT 1,
          `sort_order` INT NOT NULL DEFAULT 0,
          `created_at` DATETIME DEFAULT NULL,
          `updated_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_level` (`level`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "<span class='ok'>[OK]</span> user_levels 表已确保存在\n";
        $success++;

        // 初始化 9 行默认等级（表为空时才写，避免重复）
        $cnt = $pdo->query("SELECT COUNT(*) FROM `{$prefix}user_levels`")->fetchColumn();
        if ($cnt == 0) {
            $defaults = [
                [1, '学徒', 0,     '1.00', ''],
                [2, '新手', 100,   '1.00', ''],
                [3, '进阶', 300,   '1.00', ''],
                [4, '活跃', 700,   '1.00', ''],
                [5, '资深', 1500,  '1.10', '互动/发帖/签到 +10%'],
                [6, '精英', 3000,  '1.10', '互动/发帖/签到 +10%'],
                [7, '专家', 6000,  '1.20', '互动/发帖/签到 +20%'],
                [8, '大师', 12000, '1.20', '互动/发帖/签到 +20%'],
                [9, '传奇', 25000, '1.20', '互动/发帖/签到 +20%'],
            ];
            $ins = $pdo->prepare("INSERT INTO `{$prefix}user_levels` (level,name,byte_required,bonus_factor,privilege_text,enabled,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,1,?,?,?)");
            $now = date('Y-m-d H:i:s');
            $w = 0;
            foreach ($defaults as $i => $r) {
                $ins->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $i, $now, $now]);
                $w += $ins->rowCount();
            }
            echo "<span class='ok'>[OK]</span> 已写入 {$w} 行默认等级（Lv.1-Lv.9）\n";
            $success++;
        } else {
            echo "<span style='color:#bbb;'>[跳过]</span> user_levels 已有数据，不覆盖\n";
            $skipped++;
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 46（user_levels）: " . $e->getMessage() . "\n";
    }

    // 47. posts / comments 增加 deleted_by（回收站显示操作人；系统自动清理时为 NULL）
    try {
        echo "<strong>[升级 47] posts / comments 增加 deleted_by 列（回收站操作人）...</strong>\n";
        $added = 0;
        foreach ([$prefix . 'posts', $prefix . 'comments'] as $tbl) {
            $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'deleted_by'");
            $colStmt->execute([$tbl]);
            if ((int)$colStmt->fetchColumn() > 0) {
                echo "<span style='color:#bbb;'>[跳过]</span> {$tbl}.deleted_by 已存在\n";
                $skipped++;
            } else {
                $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `deleted_by` BIGINT DEFAULT NULL COMMENT '回收站：操作人 user_id（系统自动清理时为 NULL）'");
                echo "<span class='ok'>[OK]</span> 已添加 {$tbl}.deleted_by 列\n";
                $added++;
            }
        }
        if ($added > 0) $success++;
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 47（deleted_by 列）: " . $e->getMessage() . "\n";
    }

    // 48. 币种登记表（currencies）+ 用户自定义余额表（user_balances）
    try {
        echo "<strong>[升级 48] 创建 currencies + user_balances 表...</strong>\n";
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}currencies` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `code` VARCHAR(20) NOT NULL COMMENT '币种代码，全局唯一',
          `name` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '显示名',
          `symbol` VARCHAR(8) NOT NULL DEFAULT '' COMMENT '符号/字符',
          `icon` VARCHAR(16) NOT NULL DEFAULT '' COMMENT '图标字符或类名',
          `type` VARCHAR(16) NOT NULL DEFAULT 'consumable' COMMENT 'consumable/growth/normal',
          `enabled` TINYINT NOT NULL DEFAULT 1,
          `is_system` TINYINT NOT NULL DEFAULT 0 COMMENT '1=系统内置不可删',
          `sort_order` INT NOT NULL DEFAULT 0,
          `description` VARCHAR(255) DEFAULT NULL,
          `created_at` DATETIME DEFAULT NULL,
          `updated_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "<span class='ok'>[OK]</span> currencies 表已确保存在\n";
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}user_balances` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `user_id` BIGINT NOT NULL,
          `currency` VARCHAR(20) NOT NULL COMMENT 'currencies.code',
          `balance` INT NOT NULL DEFAULT 0,
          `created_at` DATETIME DEFAULT NULL,
          `updated_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_user_currency` (`user_id`, `currency`),
          KEY `idx_currency` (`currency`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "<span class='ok'>[OK]</span> user_balances 表已确保存在\n";
        $success++;

        // 初始化内置两币种（表为空时才写，避免重复）
        $cnt = $pdo->query("SELECT COUNT(*) FROM `{$prefix}currencies`")->fetchColumn();
        if ($cnt == 0) {
            $now = date('Y-m-d H:i:s');
            $ins = $pdo->prepare("INSERT INTO `{$prefix}currencies` (code,name,symbol,icon,type,enabled,is_system,sort_order,description,created_at,updated_at) VALUES (?,?,?,?,?,1,1,?,?,?,?)");
            $ins->execute(['token', 'Token', 'T', 'T', 'consumable', 1, '主流通积分（可获取/消耗/兑换）', $now, $now]);
            $ins->execute(['byte', 'Byte', 'B', 'B', 'growth', 2, '成长值（决定等级）', $now, $now]);
            echo "<span class='ok'>[OK]</span> 已写入内置币种 token / byte\n";
            $success++;
        } else {
            echo "<span style='color:#bbb;'>[跳过]</span> currencies 已有数据，不覆盖\n";
            $skipped++;
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 48（currencies/user_balances）: " . $e->getMessage() . "\n";
    }

    // 49. currencies.icon_svg —— 自定义币种支持上传 SVG 图标，存原始 XML 文本
    try {
        echo "<strong>[升级 49] currencies.icon_svg 列...</strong>\n";
        $exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$prefix}currencies' AND column_name = 'icon_svg'")->fetchColumn();
        if ($exists > 0) {
            echo "<span style='color:#bbb;'>[跳过]</span> currencies.icon_svg 已存在\n";
            $skipped++;
        } else {
            $pdo->exec("ALTER TABLE `{$prefix}currencies` ADD COLUMN `icon_svg` MEDIUMTEXT DEFAULT NULL COMMENT '后台自上传的 SVG 图标（原始 XML）' AFTER `icon`");
            echo "<span class='ok'>[OK]</span> 已添加 currencies.icon_svg 列\n";
            $success++;
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 49（currencies.icon_svg）: " . $e->getMessage() . "\n";
    }

    // 50. 积分充值系统：卡密 / 订单 / 折扣活动 三张表
    try {
        echo "<strong>[升级 50] 积分充值系统数据表...</strong>\n";
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}recharge_cards` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `card_no` VARCHAR(64) NOT NULL COMMENT '完整卡密（前缀+4段）',
          `prefix` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '卡密前缀',
          `currency` VARCHAR(32) NOT NULL COMMENT '充值币种 code',
          `amount` INT NOT NULL DEFAULT 0 COMMENT '面值（该币种数量）',
          `status` VARCHAR(16) NOT NULL DEFAULT 'unused' COMMENT 'unused/used/expired/invalid',
          `created_at` DATETIME DEFAULT NULL,
          `used_at` DATETIME DEFAULT NULL,
          `used_by` BIGINT UNSIGNED DEFAULT NULL COMMENT '充值账户 user_id',
          `expire_at` DATETIME DEFAULT NULL COMMENT '兑换截止，NULL=永久',
          `batch_note` VARCHAR(255) DEFAULT NULL COMMENT '批次备注',
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_card_no` (`card_no`),
          KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}recharge_orders` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `order_no` VARCHAR(64) NOT NULL COMMENT '订单号',
          `user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '充值账户 user_id',
          `channel` VARCHAR(16) NOT NULL COMMENT 'wechat/alipay',
          `amount` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '实付金额（元，含尾数）',
          `currency` VARCHAR(32) NOT NULL DEFAULT 'token',
          `gained` INT NOT NULL DEFAULT 0 COMMENT '实得币种数量',
          `scheme` VARCHAR(16) NOT NULL DEFAULT 'personal' COMMENT 'personal/aggregate/merchant',
          `pay_tail` DECIMAL(4,2) DEFAULT NULL COMMENT '个人码尾数匹配用',
          `campaign_id` BIGINT UNSIGNED DEFAULT NULL,
          `status` VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending/paid/expired/failed',
          `callback_raw` MEDIUMTEXT DEFAULT NULL,
          `created_at` DATETIME DEFAULT NULL,
          `paid_at` DATETIME DEFAULT NULL,
          `expire_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_order_no` (`order_no`),
          KEY `idx_status` (`status`),
          KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}recharge_campaigns` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `name` VARCHAR(100) NOT NULL COMMENT '活动名称',
          `type` VARCHAR(16) NOT NULL DEFAULT 'bonus' COMMENT 'bonus/discount/first',
          `value` DECIMAL(10,4) NOT NULL DEFAULT 0 COMMENT 'bonus:n 实得=base*(1+n); discount:n∈(0,1] 应付=amt*n; first:n≥1 实得=base*(1+n)',
          `channels` JSON DEFAULT NULL COMMENT '适用渠道数组 wechat/alipay/card',
          `start_at` DATETIME DEFAULT NULL,
          `end_at` DATETIME DEFAULT NULL,
          `min_amount` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '最低充值金额（元）',
          `cap` INT NOT NULL DEFAULT 0 COMMENT '加赠封顶，0=不限',
          `enabled` TINYINT NOT NULL DEFAULT 1,
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_enabled` (`enabled`),
          KEY `idx_type` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        echo "<span class='ok'>[OK]</span> 充值系统三表已创建（recharge_cards / recharge_orders / recharge_campaigns）\n";
        $success++;
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 50（充值系统表）: " . $e->getMessage() . "\n";
    }

    // ============================================================
    // 特殊主题扩展（付费/活动/悬赏/投票/辩论/采访）
    // ============================================================
    try {
        // 统一主题类型字段（拍卖兼容 is_auction）—— 幂等：先查 INFORMATION_SCHEMA 再决定是否 ALTER
        $tbl = $prefix . 'posts';
        $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'topic_type'");
        $colStmt->execute([$tbl]);
        $colExists = (int)$colStmt->fetchColumn() > 0;
        if ($colExists) {
            echo "<span style='color:#bbb;'>[跳过]</span> posts.topic_type 字段已存在\n";
            $skipped++;
        } else {
            $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `topic_type` VARCHAR(20) NOT NULL DEFAULT 'normal' COMMENT 'normal/pay/event/bounty/poll/debate/interview（拍卖兼容 is_auction）' AFTER `is_auction`");
            echo "<span class='ok'>[OK]</span> posts.topic_type 字段已添加\n";
            $success++;
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> posts.topic_type: " . $e->getMessage() . "\n";
    }

    $specialTables = [
        "CREATE TABLE IF NOT EXISTS `{$prefix}topic_pay` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `post_id` BIGINT NOT NULL,
          `price` INT NOT NULL DEFAULT 0 COMMENT '解锁全文所需积分',
          `currency` VARCHAR(16) NOT NULL DEFAULT 'token' COMMENT 'token/byte/gold',
          `preview_ratio` TINYINT NOT NULL DEFAULT 20 COMMENT '免费预览比例 %',
          `buyer_count` INT NOT NULL DEFAULT 0,
          `paid_attachments` TEXT COMMENT '付费附件 JSON [{name,url,size}]',
          `paid_images` TEXT COMMENT '付费图片 JSON [url]',
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_post` (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `{$prefix}topic_event` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `post_id` BIGINT NOT NULL,
          `event_time` DATETIME DEFAULT NULL COMMENT '活动时间',
          `location` VARCHAR(255) DEFAULT NULL,
          `capacity` INT NOT NULL DEFAULT 0 COMMENT '0=不限制',
          `signup_count` INT NOT NULL DEFAULT 0,
          `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1报名中 2满员 3已结束',
          `custom_fields` TEXT COMMENT '自定义表单字段 JSON [{label,type,required,options}]',
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_post` (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `{$prefix}topic_bounty` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `post_id` BIGINT NOT NULL,
          `per_person_tokens` INT NOT NULL DEFAULT 0 COMMENT '每人悬赏积分数（仅论坛积分）',
          `people_count` INT NOT NULL DEFAULT 0 COMMENT '悬赏人数 0=不限',
          `total_tokens` INT NOT NULL DEFAULT 0 COMMENT '质押总额 = per_person_tokens * people_count（发布时从发布者账户扣除）',
          `accepted_count` INT NOT NULL DEFAULT 0 COMMENT '已采纳回答数',
          `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1悬赏中 2已解决 3已关闭(提前结束)',
          `ended_early` TINYINT NOT NULL DEFAULT 0 COMMENT '是否提前结束',
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_post` (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `{$prefix}topic_bounty_accepted` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `bounty_id` BIGINT NOT NULL,
          `post_id` BIGINT NOT NULL,
          `comment_id` BIGINT NOT NULL COMMENT '被采纳的回答 comments.id',
          `user_id` BIGINT NOT NULL COMMENT '回答者 user_id',
          `tokens` INT NOT NULL DEFAULT 0 COMMENT '本次发放积分 = per_person_tokens',
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_bounty` (`bounty_id`),
          KEY `idx_post` (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `{$prefix}topic_poll` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `post_id` BIGINT NOT NULL,
          `multi` TINYINT NOT NULL DEFAULT 0 COMMENT '0单选 1多选',
          `anonymous` TINYINT NOT NULL DEFAULT 0,
          `deadline` DATETIME DEFAULT NULL,
          `total_votes` INT NOT NULL DEFAULT 0,
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_post` (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `{$prefix}topic_poll_options` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `poll_id` BIGINT NOT NULL,
          `text` VARCHAR(255) NOT NULL,
          `votes` INT NOT NULL DEFAULT 0,
          `sort` INT NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          KEY `idx_poll` (`poll_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `{$prefix}topic_debate` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `post_id` BIGINT NOT NULL,
          `pro_text` TEXT COMMENT '正方观点',
          `con_text` TEXT COMMENT '反方观点',
          `pro_votes` INT NOT NULL DEFAULT 0,
          `con_votes` INT NOT NULL DEFAULT 0,
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_post` (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `{$prefix}topic_debate_statements` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `post_id` BIGINT NOT NULL,
          `user_id` BIGINT NOT NULL,
          `side` TINYINT NOT NULL DEFAULT 1 COMMENT '1正方 2反方',
          `content` TEXT,
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_post` (`post_id`),
          UNIQUE KEY `uk_user_post` (`post_id`,`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `{$prefix}topic_interview` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `post_id` BIGINT NOT NULL,
          `interview_topic` VARCHAR(60) DEFAULT NULL COMMENT '采访主题（独立于帖子标题，显示在帖头「采访」字标右侧）',
          `interviewee` TEXT DEFAULT NULL COMMENT '受访者名（向后兼容老数据）',
          `interviewee_title` TEXT DEFAULT NULL COMMENT '受访者头衔',
          `open_question` TINYINT NOT NULL DEFAULT 0 COMMENT '是否开放读者提问',
          `allow_interviewee_ask` TINYINT NOT NULL DEFAULT 0 COMMENT '是否允许受访者向记者提问（开启后受访者可见提问入口，并由记者回答）',
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_post` (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `{$prefix}topic_interview_qa` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `interview_id` BIGINT NOT NULL,
          `question` TEXT,
          `answer` TEXT,
          `asker_id` BIGINT DEFAULT NULL COMMENT '提问者（采访者/读者）',
          `answerer_id` BIGINT DEFAULT NULL COMMENT '回答者（受访者）',
          `asker_role` VARCHAR(20) DEFAULT NULL COMMENT '记者/观众/受访者',
          `sort` INT NOT NULL DEFAULT 0,
          `created_at` DATETIME DEFAULT NULL,
          `updated_at` DATETIME DEFAULT NULL COMMENT '最后更新时间（如被回答）',
          PRIMARY KEY (`id`),
          KEY `idx_interview` (`interview_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `{$prefix}topic_lottery` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `post_id` BIGINT NOT NULL,
          `prize_name` VARCHAR(255) NOT NULL COMMENT '奖品名称',
          `prize_count` INT NOT NULL DEFAULT 0 COMMENT '奖品份数',
          `winner_count` INT NOT NULL DEFAULT 0 COMMENT '中奖人数',
          `join_cost` INT NOT NULL DEFAULT 0 COMMENT '参与消耗积分（0=免费）',
          `cost_currency` VARCHAR(16) NOT NULL DEFAULT 'token',
          `draw_at` DATETIME DEFAULT NULL COMMENT '开奖时间',
          `status` TINYINT NOT NULL DEFAULT 0 COMMENT '0未开奖 1已开奖',
          `joined_count` INT NOT NULL DEFAULT 0,
          `winners` TEXT COMMENT '中奖用户ID JSON',
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_post` (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($specialTables as $sql) {
        // 匹配 PHP 插值后实际渲染的首张表名（CREATE TABLE 之后第一个反引号包起来的标识符）
        $tbl = preg_match('/CREATE TABLE[^(]*?`(\w+)`/i', $sql, $m) ? $m[1] : '?';
        try {
            $pdo->exec($sql);
            echo "<span class='ok'>[OK]</span> 表 `{$tbl}` 已就绪\n";
            $success++;
        } catch (PDOException $e) {
            echo "<span class='fail'>[跳过]</span> 表 `{$tbl}`: " . $e->getMessage() . "\n";
        }
    }

    // 特殊主题交互所需的附属表（购买记录 / 报名记录 / 投票记录）
    $specialExtraTables = [
        "CREATE TABLE IF NOT EXISTS `{$prefix}topic_pay_orders` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `post_id` BIGINT NOT NULL,
          `user_id` BIGINT NOT NULL,
          `currency` VARCHAR(16) NOT NULL DEFAULT 'token',
          `amount` INT NOT NULL DEFAULT 0,
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_post_user` (`post_id`,`user_id`),
          KEY `idx_post` (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `{$prefix}topic_event_signups` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `post_id` BIGINT NOT NULL,
          `user_id` BIGINT NOT NULL,
          `data` TEXT COMMENT '自定义字段填写内容 JSON [{label,value}]',
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_post_user` (`post_id`,`user_id`),
          KEY `idx_post` (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `{$prefix}topic_poll_votes` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `poll_id` BIGINT NOT NULL,
          `option_id` BIGINT NOT NULL,
          `user_id` BIGINT NOT NULL,
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_poll_user` (`poll_id`,`user_id`),
          KEY `idx_poll` (`poll_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `{$prefix}topic_lottery_joins` (
          `id` BIGINT NOT NULL AUTO_INCREMENT,
          `post_id` BIGINT NOT NULL,
          `user_id` BIGINT NOT NULL,
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_post_user` (`post_id`,`user_id`),
          KEY `idx_post` (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($specialExtraTables as $sql) {
        // 匹配 PHP 插值后实际渲染的首张表名（CREATE TABLE 之后第一个反引号包起来的标识符）
        $tbl = preg_match('/CREATE TABLE[^(]*?`(\w+)`/i', $sql, $m) ? $m[1] : '?';
        try {
            $pdo->exec($sql);
            echo "<span class='ok'>[OK]</span> 表 `{$tbl}` 已就绪\n";
            $success++;
        } catch (PDOException $e) {
            echo "<span class='fail'>[跳过]</span> 表 `{$tbl}`: " . $e->getMessage() . "\n";
        }
    }

    // ============================================================
    // 付费主题：拆分正文/附件/图片为三档独立价格（与已有 price 字段共存，便于兼容老数据回退）
    // ============================================================
    // 1) topic_pay：新增 content_price / attachment_price / image_price 三列（0=免费公开）
    $payCols = [
        'content_price'    => "INT NOT NULL DEFAULT 0 COMMENT '正文解锁价，0=免费'",
        'attachment_price' => "INT NOT NULL DEFAULT 0 COMMENT '附件解锁价，0=免费'",
        'image_price'      => "INT NOT NULL DEFAULT 0 COMMENT '图片解锁价，0=免费'",
    ];
    foreach ($payCols as $colName => $colType) {
        $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $colStmt->execute([$prefix . 'topic_pay', $colName]);
        if ($colStmt->fetchColumn()) {
            echo "<span style='color:#bbb;'>[跳过]</span> topic_pay.{$colName} 字段已存在\n";
            $skipped++;
            continue;
        }
        try {
            // 第一次加 content_price 时一并发三列（用 first 标记）
            if ($colName === 'content_price') {
                $pdo->exec("ALTER TABLE `{$prefix}topic_pay` ADD COLUMN `content_price` {$colType} AFTER `price`");
                echo "<span class='ok'>[OK]</span> topic_pay.content_price 字段已添加\n";
                $success++;
            } elseif ($colName === 'attachment_price') {
                $pdo->exec("ALTER TABLE `{$prefix}topic_pay` ADD COLUMN `attachment_price` {$colType} AFTER `content_price`");
                echo "<span class='ok'>[OK]</span> topic_pay.attachment_price 字段已添加\n";
                $success++;
            } elseif ($colName === 'image_price') {
                $pdo->exec("ALTER TABLE `{$prefix}topic_pay` ADD COLUMN `image_price` {$colType} AFTER `attachment_price`");
                echo "<span class='ok'>[OK]</span> topic_pay.image_price 字段已添加\n";
                $success++;
            }
        } catch (PDOException $e) {
            echo "<span class='fail'>[失败]</span> topic_pay.{$colName}: " . $e->getMessage() . "\n";
        }
    }
    // 老数据回退：如果某帖只有 price（>0）但三档新价格都为 0，把 price 视作「联合解锁价」并写入 content_price（让旧帖默认正文付费），其他两档保持 0
    try {
        $pdo->exec("UPDATE `{$prefix}topic_pay` SET content_price = price WHERE price > 0 AND content_price = 0 AND attachment_price = 0 AND image_price = 0");
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> topic_pay 老数据回填: " . $e->getMessage() . "\n";
    }

    // 2) topic_pay_orders：新增 unlock_scope + 三价格快照（避免作者改价后历史价格错乱）
    $orderCols = [
        'unlock_scope'     => "VARCHAR(32) NOT NULL DEFAULT 'all' COMMENT 'all/content/attachment/image'",
        'content_price'    => "INT NOT NULL DEFAULT 0 COMMENT '购买时正文价格快照'",
        'attachment_price' => "INT NOT NULL DEFAULT 0",
        'image_price'      => "INT NOT NULL DEFAULT 0",
    ];
    foreach ($orderCols as $colName => $colType) {
        $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $colStmt->execute([$prefix . 'topic_pay_orders', $colName]);
        if ($colStmt->fetchColumn()) {
            echo "<span style='color:#bbb;'>[跳过]</span> topic_pay_orders.{$colName} 字段已存在\n";
            $skipped++;
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE `{$prefix}topic_pay_orders` ADD COLUMN `{$colName}` {$colType}");
            echo "<span class='ok'>[OK]</span> topic_pay_orders.{$colName} 字段已添加\n";
            $success++;
        } catch (PDOException $e) {
            echo "<span class='fail'>[失败]</span> topic_pay_orders.{$colName}: " . $e->getMessage() . "\n";
        }
    }

    // 3) topic_event：新增「活动主题」+「活动形式」+「活动费用」
    //    subject=活动主题（用户自行填写，区别于帖子标题）
    //    mode=活动形式 online/offline；老数据一律回落到 offline（线下），避免展示层出现空值
    //    fee=活动费用（默认 0 = 免费；DECIMAL(10,2) 防浮点；老数据 fallback 0）
    $eventCols = [
        'subject' => "VARCHAR(255) DEFAULT NULL COMMENT '活动主题'",
        'mode'    => "VARCHAR(20) NOT NULL DEFAULT 'offline' COMMENT '活动形式 online=线上 offline=线下'",
        'fee'     => "DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '活动费用，0=免费'",
    ];
    foreach ($eventCols as $colName => $colType) {
        $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $colStmt->execute([$prefix . 'topic_event', $colName]);
        if ($colStmt->fetchColumn()) {
            echo "<span style='color:#bbb;'>[跳过]</span> topic_event.{$colName} 字段已存在\n";
            $skipped++;
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE `{$prefix}topic_event` ADD COLUMN `{$colName}` {$colType}");
            echo "<span class='ok'>[OK]</span> topic_event.{$colName} 字段已添加\n";
            $success++;
        } catch (PDOException $e) {
            echo "<span class='fail'>[失败]</span> topic_event.{$colName}: " . $e->getMessage() . "\n";
        }
    }
    // 老数据兜底：mode 为空字符串的存量记录统一置为 offline
    try {
        $pdo->exec("UPDATE `{$prefix}topic_event` SET mode = 'offline' WHERE mode IS NULL OR mode = ''");
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> topic_event 老数据回填: " . $e->getMessage() . "\n";
    }

    // 4) comments：悬赏主题「回答」分层字段
    //    is_bounty_answer=悬赏期发的顶层评论（强制标 1）；非悬赏期或悬赏结束后发的=0
    //    accept_rank=采纳顺序（NULL=未采纳；1,2,3...=采纳次序号，给「采纳置顶」排序用）
    //    设计要点：复用 comments 表而不是新增 topic_bounty_answers，避免再起一张表带来的
    //      数据双写、外键跨表、用户态/界面映射的复杂度。改动只在 comments 加 2 列零侵入。
    $bountyCols = [
        'is_bounty_answer' => "TINYINT NOT NULL DEFAULT 0 COMMENT '0=普通评论 1=悬赏期发的回答'",
        'accept_rank'      => "INT DEFAULT NULL COMMENT '采纳次序，NULL=未采纳；越小越靠前'",
    ];
    foreach ($bountyCols as $colName => $colType) {
        $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $colStmt->execute([$prefix . 'comments', $colName]);
        if ($colStmt->fetchColumn()) {
            echo "<span style='color:#bbb;'>[跳过]</span> comments.{$colName} 字段已存在\n";
            $skipped++;
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE `{$prefix}comments` ADD COLUMN `{$colName}` {$colType}");
            echo "<span class='ok'>[OK]</span> comments.{$colName} 字段已添加\n";
            $success++;
        } catch (PDOException $e) {
            echo "<span class='fail'>[失败]</span> comments.{$colName}: " . $e->getMessage() . "\n";
        }
    }
    // 索引：accept_rank 常用于「按采纳次序排序 + 悬赏帖批量查找」，建一个轻量索引
    try {
        $idxStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
        $idxStmt->execute([$prefix . 'comments', 'idx_bounty_accept']);
        if (!$idxStmt->fetchColumn()) {
            $pdo->exec("ALTER TABLE `{$prefix}comments` ADD INDEX `idx_bounty_accept` (`is_bounty_answer`, `accept_rank`)");
            echo "<span class='ok'>[OK]</span> comments.idx_bounty_accept 索引已添加\n";
            $success++;
        } else {
            echo "<span style='color:#bbb;'>[跳过]</span> comments.idx_bounty_accept 索引已存在\n";
            $skipped++;
        }
    } catch (PDOException $e) {
        echo "<span style='color:#bbb;'>[提示]</span> comments.idx_bounty_accept 索引添加失败（非阻塞）: " . $e->getMessage() . "\n";
    }
    // 老 bounty 帖的现存「回答型评论」批量回填：所有 bounty 帖的顶层评论先标 is_bounty_answer=1
    //   （非完美，过渡期让现有功能不崩；后续新评论按业务逻辑精准写入）
    try {
        $pdo->exec("UPDATE `{$prefix}comments` c JOIN `{$prefix}topic_bounty` b ON c.post_id = b.post_id SET c.is_bounty_answer = 1 WHERE (c.parent_id IS NULL OR c.parent_id = 0)");
        echo "<span class='ok'>[OK]</span> 老 bounty 帖顶层评论已批量标记为 is_bounty_answer=1\n";
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 老数据回填: " . $e->getMessage() . "\n";
    }
    // 已被采纳的老回答回填 accept_rank：按 topic_bounty_accepted.id ASC 依次编 1,2,3...
    try {
        $rows = $pdo->query("SELECT a.comment_id FROM `{$prefix}topic_bounty_accepted` a ORDER BY a.id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $rank = 0;
        foreach ($rows as $r) {
            $rank++;
            $pdo->prepare("UPDATE `{$prefix}comments` SET accept_rank = ? WHERE id = ?")->execute([$rank, $r['comment_id']]);
        }
        echo "<span class='ok'>[OK]</span> 老 bounty 采纳评论已回填 accept_rank（{$rank} 条）\n";
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> accept_rank 回填: " . $e->getMessage() . "\n";
    }

    // 51. 积分余额列 INT → BIGINT（修复「积分数字上亿后不再增长」）
    //     根因：users.token/byte、points_log.balance_after/amount、user_balances.balance、reward_log.token
    //     原均为有符号 INT（上限 2147483647 ≈ 21.4 亿），余额触顶后 MySQL 写入被截断回 2147483647，
    //     表现为「合法获取/扣除但数字不变」。改为 BIGINT（上限 9.2×10^18），规则与逻辑不动。
    //     幂等：每个列先查 INFORMATION_SCHEMA 确认存在且当前确实是 INT 才 MODIFY，可安全重复运行。
    try {
        echo "<strong>[升级 51] 积分余额列 INT → BIGINT...</strong>\n";
        $bigintCols = [
            ['users',          'token',         'BIGINT NOT NULL DEFAULT 0'],
            ['users',          'byte',          'BIGINT NOT NULL DEFAULT 0'],
            ['points_log',     'amount',        'BIGINT NOT NULL DEFAULT 0'],
            ['points_log',     'balance_after', 'BIGINT NOT NULL DEFAULT 0'],
            ['user_balances',  'balance',       'BIGINT NOT NULL DEFAULT 0'],
            ['reward_log',     'token',         'BIGINT NOT NULL DEFAULT 0'],
        ];
        $colStmt = $pdo->prepare(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND DATA_TYPE = 'int'"
        );
        $modOk = 0;
        foreach ($bigintCols as $c) {
            list($t, $col, $def) = $c;
            $tbl = $prefix . $t;
            $colStmt->execute([$tbl, $col]);
            if ($colStmt->fetchColumn()) {
                $pdo->exec("ALTER TABLE `{$tbl}` MODIFY COLUMN `{$col}` {$def} COMMENT '积分余额列已升级为 BIGINT（原 INT 溢出导致数字卡死）'");
                echo "<span class='ok'>[OK]</span> {$tbl}.{$col} 已升级 BIGINT\n";
                $modOk++;
            } else {
                // 列不存在（极旧库未建该表）或已是 BIGINT → 跳过，不报错
                echo "<span style='color:#bbb;'>[跳过]</span> {$tbl}.{$col} 不存在或非 INT\n";
                $skipped++;
            }
        }
        if ($modOk > 0) {
            $success++;
            echo "<span class='ok'>[OK]</span> 共 {$modOk} 个积分余额列升级为 BIGINT\n";
        } else {
            echo "<span style='color:#bbb;'>[跳过]</span> 无 INT 余额列需要升级（已是 BIGINT 或列不存在）\n";
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 51（积分余额列 BIGINT）: " . $e->getMessage() . "\n";
    }

    // 52. 投票帖不允许匿名 + 辩论主题改为「评论区站队发言」
    //     a) topic_debate 加 deadline（截止时间，同特殊主题 ≥ now+15min 校验）
    //     b) topic_debate_statements 删 UNIQUE KEY uk_user_post(post_id,user_id) — 同用户需多次站队发言
    //     c) topic_debate_statements 改为 KEY idx_post_user（保留原 KEY idx_post，新加索引）
    //     幂等：列加/索引加都查 INFORMATION_SCHEMA 防重；唯一键删是先查 STATISTICS，索引名不存在就跳过。
    try {
        echo "<strong>[升级 52] 投票去匿名 + 辩论 deadline + 多条站队发言索引...</strong>\n";
        // a) topic_debate 加 deadline
        $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $colStmt->execute([$prefix . 'topic_debate', 'deadline']);
        if (!$colStmt->fetchColumn()) {
            $pdo->exec("ALTER TABLE `{$prefix}topic_debate` ADD COLUMN `deadline` DATETIME DEFAULT NULL COMMENT '辩论截止时间（之后不能再发言）' AFTER `con_votes`");
            echo "<span class='ok'>[OK]</span> topic_debate.deadline 字段已添加\n";
            $success++;
        } else {
            echo "<span style='color:#bbb;'>[跳过]</span> topic_debate.deadline 字段已存在\n";
            $skipped++;
        }
        // b) 删 UNIQUE KEY uk_user_post（弃用「同 post 同一用户只能发言一次」）
        $idxStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
        $idxStmt->execute([$prefix . 'topic_debate_statements', 'uk_user_post']);
        if ($idxStmt->fetchColumn()) {
            // 先确认非复合唯一键（本表唯一键就是这一条）；存在则删除
            $pdo->exec("ALTER TABLE `{$prefix}topic_debate_statements` DROP INDEX `uk_user_post`");
            echo "<span class='ok'>[OK]</span> topic_debate_statements.uk_user_post 唯一键已删除\n";
            $success++;
        } else {
            echo "<span style='color:#bbb;'>[跳过]</span> topic_debate_statements.uk_user_post 已不存在\n";
            $skipped++;
        }
        // c) 加 KEY idx_user_post（用于「查询某用户某主题是否已站队」「是否同一方」）
        $idxStmt->execute([$prefix . 'topic_debate_statements', 'idx_user_post']);
        if (!$idxStmt->fetchColumn()) {
            $pdo->exec("ALTER TABLE `{$prefix}topic_debate_statements` ADD INDEX `idx_user_post` (`post_id`, `user_id`)");
            echo "<span class='ok'>[OK]</span> topic_debate_statements.idx_user_post 索引已添加\n";
            $success++;
        } else {
            echo "<span style='color:#bbb;'>[跳过]</span> topic_debate_statements.idx_user_post 索引已存在\n";
            $skipped++;
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 52（投票/辩论字段）: " . $e->getMessage() . "\n";
    }

    // 53. 评论引用 + 回复可挂到「站队发言」之下
    //     a) comments 加 parent_type（'comment' 默认 / 'statement'，区分 parent_id 指向评论还是辩论站队发言）
    //     b) comments 加 quote_id / quote_type / quote_author / quote_snippet（引用别人观点：被引用内容小字展示、点击定位到被引用锚点）
    //     幂等：逐列查 INFORMATION_SCHEMA 防重。
    try {
        echo "<strong>[升级 53] 评论引用块 + 站队发言楼中楼字段...</strong>\n";
        $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $commentCols = [
            'parent_type'  => "VARCHAR(16) NOT NULL DEFAULT 'comment' COMMENT 'parent_id 指向类型：comment=普通评论 / statement=辩论站队发言'",
            'quote_id'     => "BIGINT DEFAULT NULL COMMENT '被引用内容 id（引用的评论或站队发言）'",
            'quote_type'   => "VARCHAR(16) DEFAULT NULL COMMENT '被引用类型：comment / statement'",
            'quote_author' => "VARCHAR(64) DEFAULT NULL COMMENT '被引用内容作者展示名（落库避免改名后错位）'",
            'quote_snippet'=> "TEXT DEFAULT NULL COMMENT '被引用内容摘要（截断后的小字引用块）'",
        ];
        foreach ($commentCols as $cName => $cDef) {
            $colStmt->execute([$prefix . 'comments', $cName]);
            if (!$colStmt->fetchColumn()) {
                $pdo->exec("ALTER TABLE `{$prefix}comments` ADD COLUMN `{$cName}` {$cDef}");
                echo "<span class='ok'>[OK]</span> comments.{$cName} 字段已添加\n";
                $success++;
            } else {
                echo "<span style='color:#bbb;'>[跳过]</span> comments.{$cName} 字段已存在\n";
                $skipped++;
            }
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 53（评论引用/父类型字段）: " . $e->getMessage() . "\n";
    }

    // 升级 54：topic_debate_statements 加引用字段（引用别人站队发言：被引用内容小字展示、点击定位到被引用锚点）。
    //   辩论未截止时，引用站队发言会作为「一条新的站队发言」发表（走 post/debateJoin），故引用块需落到 statements 表。
    //   幂等：逐列查 INFORMATION_SCHEMA 防重。
    try {
        echo "<strong>[升级 54] 站队发言引用字段...</strong>\n";
        $stmtColStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmtCols = [
            'quote_id'     => "BIGINT DEFAULT NULL COMMENT '被引用内容 id（引用的评论或站队发言）'",
            'quote_type'   => "VARCHAR(16) DEFAULT NULL COMMENT '被引用类型：comment / statement'",
            'quote_author' => "VARCHAR(64) DEFAULT NULL COMMENT '被引用内容作者展示名'",
            'quote_snippet'=> "TEXT DEFAULT NULL COMMENT '被引用内容摘要（截断后的小字引用块）'",
        ];
        foreach ($stmtCols as $cName => $cDef) {
            $stmtColStmt->execute([$prefix . 'topic_debate_statements', $cName]);
            if (!$stmtColStmt->fetchColumn()) {
                $pdo->exec("ALTER TABLE `{$prefix}topic_debate_statements` ADD COLUMN `{$cName}` {$cDef}");
                echo "<span class='ok'>[OK]</span> topic_debate_statements.{$cName} 字段已添加\n";
                $success++;
            } else {
                echo "<span style='color:#bbb;'>[跳过]</span> topic_debate_statements.{$cName} 字段已存在\n";
                $skipped++;
            }
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 54（站队发言引用字段）: " . $e->getMessage() . "\n";
    }

    // 升级 55：topic_lottery 奖品类型/价值/质押/规则/中奖明细列。
    //   抽奖主题新规则：
    //     · prize_type：physical(实物) / virtual(虚拟) / point(积分)；
    //     · prize_value：实物/虚拟 = 价值文本（如 "¥99"），积分 = 每份积分数（数字）；
    //     · stake_points / stake_currency：积分类奖品由发起者预先质押的总积分（= point_unit * winner_count），开奖时按实际中奖发放；
    //     · point_unit：每份中奖积分数（granted 给每名中奖者），剩余部分 50% 返还作者；
    //     · rules_json：4 个下拉规则 JSON（follow/follow_mode/like/like_mode/favorite/favorite_mode/reply/reply_mode/mode 取值 no/optional/must）；
    //     · stake_returned：标记剩余积分是否已返还作者（防重复返）；
    //     · winners_detail：JSON，中奖用户明细 [{user_id, username, nickname, avatar, granted(积分), rank}]，供帖子页直接渲染头像列表。
    try {
        echo "<strong>[升级 55] 抽奖奖品类型/质押/规则/中奖明细字段...</strong>\n";
        $lotColStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $lotCols = [
            'prize_type'      => "VARCHAR(16) NOT NULL DEFAULT 'physical' COMMENT '奖品类型：physical/virtual/point'",
            'prize_value'     => "TEXT DEFAULT NULL COMMENT '奖品价值（实物/虚拟=文本；积分=每份积分数）'",
            'point_unit'      => "BIGINT NOT NULL DEFAULT 0 COMMENT '每份中奖积分数（积分类奖品专用）'",
            'stake_points'    => "BIGINT NOT NULL DEFAULT 0 COMMENT '发起者质押总积分=point_unit*winner_count（积分类专用）'",
            'stake_currency'  => "VARCHAR(16) NOT NULL DEFAULT 'token' COMMENT '质押币种'",
            'rules_json'      => "TEXT DEFAULT NULL COMMENT '抽奖规则 JSON（4 个下拉项）'",
            'stake_returned'  => "TINYINT NOT NULL DEFAULT 0 COMMENT '剩余积分是否已 50% 返还作者（0/1）'",
            'winners_detail'  => "TEXT DEFAULT NULL COMMENT '中奖明细 JSON（含头像/名次/积分）'",
        ];
        foreach ($lotCols as $cName => $cDef) {
            $lotColStmt->execute([$prefix . 'topic_lottery', $cName]);
            if (!$lotColStmt->fetchColumn()) {
                $pdo->exec("ALTER TABLE `{$prefix}topic_lottery` ADD COLUMN `{$cName}` {$cDef}");
                echo "<span class='ok'>[OK]</span> topic_lottery.{$cName} 字段已添加\n";
                $success++;
            } else {
                echo "<span style='color:#bbb;'>[跳过]</span> topic_lottery.{$cName} 字段已存在\n";
                $skipped++;
            }
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 55（抽奖奖品类型/质押/规则/中奖明细）: " . $e->getMessage() . "\n";
    }

    // 升级 56：topic_lottery 补 point_mode 列（随机/自定义模式标记）。
    //   根因：storeSpecial() 漏写 point_mode 字段 → drawLottery() 里 $row['point_mode'] 永远为空 → 永远走 custom 分支 →
    //         "随机资产" 发布后开奖仍按"每人相同"分配，与发布设置不符。加列 + 后续控制器补写入即可修复。
    //   幂等：先查 INFORMATION_SCHEMA 防重。
    try {
        echo "<strong>[升级 56] 抽奖 point_mode 列...</strong>\n";
        $pmStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $pmStmt->execute([$prefix . 'topic_lottery', 'point_mode']);
        if (!$pmStmt->fetchColumn()) {
            $pdo->exec("ALTER TABLE `{$prefix}topic_lottery` ADD COLUMN `point_mode` VARCHAR(16) NOT NULL DEFAULT 'custom' COMMENT '积分奖品分配模式：custom=每人相同 / random=随机分配总额' AFTER `point_unit`");
            echo "<span class='ok'>[OK]</span> topic_lottery.point_mode 字段已添加\n";
            $success++;
        } else {
            echo "<span style='color:#bbb;'>[跳过]</span> topic_lottery.point_mode 字段已存在\n";
            $skipped++;
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 56（抽奖 point_mode 列）: " . $e->getMessage() . "\n";
    }

    // 升级 57：采访主题参与者升级为「网站用户」 + 结束采访字段。
    //   根因：旧 topic_interview 只存受访者「文本名」（interviewee VARCHAR(100)），
    //         没有用户关联，无法做"必须是网站用户"的校验、推送通知、显示头像/主页。
    //   改造：加 reporter_id（记者用户 id，默认 = 发帖人）/ reporter_title（记者头衔，可选）
    //         / interviewee_user_id（受访者用户 id，必填，发布时校验 status=1）
    //         / ended_at（结束采访时间，未结束 = NULL）。旧 interviewee / interviewee_title 文本列保留
    //         做向后兼容 fallback：loadSpecial() 优先用新 user_id 联结 users 表回填昵称/头像，
    //         老数据（user_id 为 NULL）继续回退到原文本显示。
    //   幂等：每列独立查 INFORMATION_SCHEMA 防重。
    try {
        echo "<strong>[升级 57] 采访主题加用户关联 + 结束字段...</strong>\n";
        $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $idxStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
        $ivTbl = $prefix . 'topic_interview';

        $ivCols = [
            'reporter_id'         => "ALTER TABLE `{$ivTbl}` ADD COLUMN `reporter_id` BIGINT DEFAULT NULL COMMENT '记者用户id（默认=发帖人）' AFTER `post_id`",
            'reporter_title'      => "ALTER TABLE `{$ivTbl}` ADD COLUMN `reporter_title` TEXT DEFAULT NULL COMMENT '记者头衔' AFTER `reporter_id`",
            'interviewee_user_id' => "ALTER TABLE `{$ivTbl}` ADD COLUMN `interviewee_user_id` BIGINT DEFAULT NULL COMMENT '受访者用户id（必填=网站用户）' AFTER `reporter_title`",
            'ended_at'            => "ALTER TABLE `{$ivTbl}` ADD COLUMN `ended_at` DATETIME DEFAULT NULL COMMENT '结束采访时间，NULL=未结束' AFTER `open_question`",
        ];
        foreach ($ivCols as $col => $ddl) {
            $colStmt->execute([$ivTbl, $col]);
            if (!$colStmt->fetchColumn()) {
                $pdo->exec($ddl);
                echo "<span class='ok'>[OK]</span> topic_interview.{$col} 字段已添加\n";
                $success++;
            } else {
                echo "<span style='color:#bbb;'>[跳过]</span> topic_interview.{$col} 字段已存在\n";
                $skipped++;
            }
        }
        // 索引：interviewee_user_id（按用户查"我被采访的帖子"）/ reporter_id
        foreach (['idx_interviewee' => 'interviewee_user_id', 'idx_reporter' => 'reporter_id'] as $idxName => $colName) {
            $idxStmt->execute([$ivTbl, $idxName]);
            if (!$idxStmt->fetchColumn()) {
                $pdo->exec("ALTER TABLE `{$ivTbl}` ADD INDEX `{$idxName}` (`{$colName}`)");
                echo "<span class='ok'>[OK]</span> topic_interview.{$idxName} 索引已添加\n";
                $success++;
            } else {
                echo "<span style='color:#bbb;'>[跳过]</span> topic_interview.{$idxName} 索引已存在\n";
                $skipped++;
            }
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 57（采访用户关联）: " . $e->getMessage() . "\n";
    }

    // 升级 58：采访头衔字段扩为 TEXT（解决 VARCHAR(100) 太短被 1406 截断）。
    //   根因：采访头衔支持多行（前端 textarea maxlength=200），但 DB 仍是 VARCHAR(100)，
    //         用户输入 >100 字符触发 'Data too long for column interviewee_title at row 1'。
    //   改造：interviewee / interviewee_title / reporter_title 三列由 VARCHAR(100) → TEXT。
    //         TEXT 容量 64KB，足够任意头衔。
    //   幂等：读 INFORMATION_SCHEMA.COLUMNS 的 DATA_TYPE，已是 text/mediumtext/longtext 跳过。
    try {
        echo "<strong>[升级 58] 采访头衔字段扩为 TEXT...</strong>\n";
        $colTypeStmt = $pdo->prepare("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $ivTbl = $prefix . 'topic_interview';
        $ivTextCols = [
            'interviewee'       => "`interviewee` TEXT DEFAULT NULL COMMENT '受访者名（向后兼容老数据）'",
            'interviewee_title' => "`interviewee_title` TEXT DEFAULT NULL COMMENT '受访者头衔'",
            'reporter_title'    => "`reporter_title` TEXT DEFAULT NULL COMMENT '记者头衔'",
        ];
        foreach ($ivTextCols as $col => $def) {
            $colTypeStmt->execute([$ivTbl, $col]);
            $currentType = (string)$colTypeStmt->fetchColumn();
            if (in_array(strtolower($currentType), ['text', 'mediumtext', 'longtext'], true)) {
                echo "<span style='color:#bbb;'>[跳过]</span> topic_interview.{$col} 已是 {$currentType} 类型\n";
                $skipped++;
                continue;
            }
            $pdo->exec("ALTER TABLE `{$ivTbl}` MODIFY COLUMN {$def}");
            echo "<span class='ok'>[OK]</span> topic_interview.{$col} 已由 {$currentType} 改为 TEXT\n";
            $success++;
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 58（采访头衔扩 TEXT）: " . $e->getMessage() . "\n";
    }

    // 升级 59：topic_interview_qa 表加 updated_at 字段。
    //   根因：interviewAnswer() 写回答时会 update ... 'updated_at' = now()，但老表里只有 created_at，
    //         DB 抛 'Unknown column updated_at in field list'，受访者无法提交回答。
    //   改造：updated_at DATETIME DEFAULT NULL（NULL 表示尚未被回答）。
    //   幂等：查 INFORMATION_SCHEMA.COLUMNS 是否已存在，存在则跳过。
    try {
        echo "<strong>[升级 59] 采访问答表加 updated_at...</strong>\n";
        $qaTbl = $prefix . 'topic_interview_qa';
        $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $colStmt->execute([$qaTbl, 'updated_at']);
        if (!$colStmt->fetchColumn()) {
            $pdo->exec("ALTER TABLE `{$qaTbl}` ADD COLUMN `updated_at` DATETIME DEFAULT NULL COMMENT '最后更新时间（如被回答）' AFTER `created_at`");
            echo "<span class='ok'>[OK]</span> {$qaTbl}.updated_at 字段已添加\n";
            $success++;
        } else {
            echo "<span style='color:#bbb;'>[跳过]</span> {$qaTbl}.updated_at 字段已存在\n";
            $skipped++;
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 59（采访问答 updated_at）: " . $e->getMessage() . "\n";
    }

    // 升级 60：topic_interview 表加 allow_interviewee_ask 开关。
    //   根因：新增「允许受访者提问」发帖勾选项，由该列控制受访者端是否显示提问入口 + 记者回答权限。
    //   改造：allow_interviewee_ask TINYINT DEFAULT 0（0=不开启，仅记者/读者可问；1=受访者可向记者提问，由记者回答）。
    //   幂等：查 INFORMATION_SCHEMA.COLUMNS 是否已存在，存在则跳过。
    try {
        echo "<strong>[升级 60] 采访表加 allow_interviewee_ask 开关...</strong>\n";
        $ivTbl = $prefix . 'topic_interview';
        $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $colStmt->execute([$ivTbl, 'allow_interviewee_ask']);
        if (!$colStmt->fetchColumn()) {
            $pdo->exec("ALTER TABLE `{$ivTbl}` ADD COLUMN `allow_interviewee_ask` TINYINT NOT NULL DEFAULT 0 COMMENT '是否允许受访者向记者提问' AFTER `open_question`");
            echo "<span class='ok'>[OK]</span> {$ivTbl}.allow_interviewee_ask 字段已添加\n";
            $success++;
        } else {
            echo "<span style='color:#bbb;'>[跳过]</span> {$ivTbl}.allow_interviewee_ask 字段已存在\n";
            $skipped++;
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 60（采访 allow_interviewee_ask）: " . $e->getMessage() . "\n";
    }

    // 升级 61：采访主题独立字段（2026-09-02）
    //   需求：采访主题不再从帖子标题继承，由发布人在「采访主题」框填写后显示在帖头「采访」字标右侧。
    //   老数据允许为 NULL（前端 fallback 到帖子标题，避免历史采访帖出现「采访」字标后空白）。
    try {
        $ivTbl61 = $prefix . 'topic_interview';
        $colStmt61 = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $colStmt61->execute([$ivTbl61, 'interview_topic']);
        if (!$colStmt61->fetchColumn()) {
            $pdo->exec("ALTER TABLE `{$ivTbl61}` ADD COLUMN `interview_topic` VARCHAR(60) DEFAULT NULL COMMENT '采访主题（独立于帖子标题）' AFTER `post_id`");
            echo "<span class='ok'>[OK]</span> {$ivTbl61}.interview_topic 字段已添加\n";
            $success++;
        } else {
            echo "<span style='color:#bbb;'>[跳过]</span> {$ivTbl61}.interview_topic 字段已存在\n";
            $skipped++;
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 61（采访 interview_topic）: " . $e->getMessage() . "\n";
    }

    // 升级 62：表情系统（emoji_packs / emoji_items 表 + 默认 unicode 表情包）
    //   默认一套：unicode（原字符 emoji 65 个）。
    //   历史备注：曾默认有 owo（Unicode 颜文字）与 twemoji（Twitter 开源 CDN），分别因
    //     (1) 站长已停用颜文字包，整体不需要；
    //     (2) emoji_items.code 全库 UNIQUE + unicode/twemoji 共享 code 导致 twemoji items 全部 IGNORE 跳过；
    //   已移除。如需 owo 可在后台手工添加 unicode 表情包。
    try {
        echo "<strong>[升级 62] 表情系统（emoji_packs / emoji_items + unicode 默认包）...</strong>\n";
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}emoji_packs` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `name` VARCHAR(64) NOT NULL COMMENT '表情包名',
          `slug` VARCHAR(32) NOT NULL COMMENT '唯一短标识',
          `type` VARCHAR(16) NOT NULL DEFAULT 'unicode' COMMENT 'unicode=原字符 / image=图片渲染',
          `cover` VARCHAR(255) DEFAULT NULL,
          `cd` VARCHAR(255) DEFAULT NULL COMMENT 'CDN/路径模板（image 类型用，{code} 被 items.image 替换）',
          `sort_order` INT NOT NULL DEFAULT 0,
          `enabled` TINYINT NOT NULL DEFAULT 1,
          `is_system` TINYINT NOT NULL DEFAULT 0 COMMENT '1=系统内置不可删（仅禁用/启停）',
          `created_at` DATETIME DEFAULT NULL,
          `updated_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}emoji_items` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `pack_id` INT NOT NULL,
          `code` VARCHAR(32) NOT NULL COMMENT '短代码（不含冒号，全库唯一）',
          `name` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '中文名',
          `keywords` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '搜索关键词（逗号分隔）',
          `char` VARCHAR(16) DEFAULT NULL COMMENT 'unicode 类型用：实际字符',
          `image` VARCHAR(64) DEFAULT NULL COMMENT 'image 类型用：codepoint(hex) 或相对路径',
          `category` VARCHAR(16) NOT NULL DEFAULT 'other' COMMENT '分类标签',
          `sort_order` INT NOT NULL DEFAULT 0,
          `enabled` TINYINT NOT NULL DEFAULT 1,
          `created_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_code` (`code`),
          KEY `idx_pack` (`pack_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "<span class='ok'>[OK]</span> emoji_packs / emoji_items 表已创建\n";
        $success++;

        $now = date('Y-m-d H:i:s');

        // 默认 1 套 pack（is_system=1 内置不可删；仅可启停）
        // 注意：升级 62 之前还默认安装了「颜文字」包（slug=owo），
        // 但站长已决定删除（见升级 66），所以这里不再装它。未来如再默认装须升级 62 同时改升级 66。
        $packs = [
            ['unicode', '原生',     'unicode', null, 0],
        ];
        $insPack = $pdo->prepare("INSERT IGNORE INTO `{$prefix}emoji_packs` (slug,name,type,cd,is_system,sort_order,enabled,created_at,updated_at) VALUES (?,?,?,?,1,?,1,?,?)");
        foreach ($packs as $p) {
            $insPack->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $now, $now]);
        }

        $packId = [];
        foreach ($pdo->query("SELECT id, slug FROM `{$prefix}emoji_packs` WHERE slug='unicode'")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $packId[$row['slug']] = (int)$row['id'];
        }

        // unicode 精选 65 个表情
        $emojiData = [
            ['grinning',         '大笑',   '笑 grin 开心 大笑',          '😀', '1f600', 'smile'],
            ['grin',             '露齿笑', '笑 grin 露齿',               '😁', '1f601', 'smile'],
            ['joy',              '笑哭',   '笑 joy 笑哭',                '😂', '1f602', 'smile'],
            ['rofl',             '笑翻',   'rofl 笑翻 666',              '🤣', '1f923', 'smile'],
            ['laughing',         '哈哈',   'laugh 哈哈',                 '😆', '1f606', 'smile'],
            ['sweat_smile',      '苦笑',   '苦笑 sweat',                 '😅', '1f605', 'smile'],
            ['wink',             '眨眼',   'wink 眨眼 俏皮',             '😉', '1f609', 'smile'],
            ['blush',            '害羞',   'blush 害羞 红脸',            '😊', '1f60a', 'smile'],
            ['yum',              '好吃',   'yum 好吃 馋',                '😋', '1f60b', 'smile'],
            ['sunglasses',       '墨镜',   'cool 墨镜 帅',               '😎', '1f60e', 'smile'],
            ['heart_eyes',       '花心',   'love 喜欢 心动',             '😍', '1f60d', 'smile'],
            ['kissing_heart',    '亲亲',   'kiss 亲',                    '😘', '1f618', 'smile'],
            ['thinking',         '思考',   'think 思考 想',              '🤔', '1f914', 'smile'],
            ['neutral',          '中性',   'neutral 中性 无感',          '😐', '1f610', 'smile'],
            ['expressionless',   '面无表情', 'expressionless 无感',     '😑', '1f611', 'smile'],
            ['unamused',         '不爽',   'unamused 不爽',              '😒', '1f612', 'smile'],
            ['roll_eyes',        '翻白眼', 'roll_eyes 翻白眼',           '🙄', '1f644', 'smile'],
            ['grimacing',        '苦笑脸', 'grimacing 尴尬',             '😬', '1f62c', 'smile'],
            ['lying',            '说谎',   'lying 撒谎 长鼻子',          '🤥', '1f925', 'smile'],
            ['relieved',         '松口气', 'relieved 松口气',            '😌', '1f60c', 'smile'],
            ['sleepy',           '困',     'sleepy 困',                  '😪', '1f62a', 'smile'],
            ['sleeping',         '睡觉',   'sleep 睡觉',                 '😴', '1f634', 'smile'],
            ['mask',             '口罩',   'mask 口罩',                  '😷', '1f637', 'smile'],
            ['nerd',             '书呆',   'nerd 眼镜 呆',               '🤓', '1f913', 'smile'],
            ['clown',            '小丑',   'clown 小丑',                 '🤡', '1f921', 'smile'],
            ['shrug',            '耸肩',   'shrug 耸肩',                 '🤷', '1f937', 'smile'],
            ['wave',             '招手',   'wave 招手 你好',             '👋', '1f44b', 'hand'],
            ['ok_hand',          'OK',     'ok 好的',                    '👌', '1f44c', 'hand'],
            ['v',                '胜利',   'v 胜利 peace',               '✌', '270c', 'hand'],
            ['crossed_fingers',  '祈福',   'crossed_fingers 祈福',       '🤞', '1f91e', 'hand'],
            ['love_you',         '比心',   'love_you 比心',              '🤟', '1f91f', 'hand'],
            ['rock',             '摇滚',   'rock 摇滚 666',              '🤘', '1f918', 'hand'],
            ['call_me',          '打电话', 'call_me 打电话',             '🤙', '1f919', 'hand'],
            ['point_up',         '指上',   'point_up 上面',              '☝', '261d', 'hand'],
            ['point_left',       '指左',   'point_left 左',              '👈', '1f448', 'hand'],
            ['point_right',      '指右',   'point_right 右',             '👉', '1f449', 'hand'],
            ['point_down',       '指下',   'point_down 下',              '👇', '1f447', 'hand'],
            ['thumbsup',         '点赞',   'thumbsup 赞 好评',           '👍', '1f44d', 'hand'],
            ['thumbsdown',       '踩',     'thumbsdown 踩 差评',         '👎', '1f44e', 'hand'],
            ['fist',             '拳头',   'fist 拳头 加油',             '✊', '270a', 'hand'],
            ['punch',            '出拳',   'punch 出拳',                 '👊', '1f44a', 'hand'],
            ['clap',             '鼓掌',   'clap 鼓掌 666',              '👏', '1f44f', 'hand'],
            ['raised_hands',     '举手',   'raised_hands 举手 万岁',     '🙌', '1f64c', 'hand'],
            ['pray',             '祈祷',   'pray 祈祷',                  '🙏', '1f64f', 'hand'],
            ['muscle',           '肌肉',   'muscle 肌肉 强壮',           '💪', '1f4aa', 'hand'],
            ['dog',              '狗',     'dog 狗 汪',                  '🐶', '1f436', 'animal'],
            ['cat',              '猫',     'cat 猫 喵',                  '🐱', '1f431', 'animal'],
            ['mouse',            '老鼠',   'mouse 老鼠 鼠标',            '🐭', '1f42d', 'animal'],
            ['rabbit',           '兔子',   'rabbit 兔子',                '🐰', '1f430', 'animal'],
            ['fox',              '狐狸',   'fox 狐狸',                   '🦊', '1f98a', 'animal'],
            ['bear',             '熊',     'bear 熊',                    '🐻', '1f43b', 'animal'],
            ['panda',            '熊猫',   'panda 熊猫',                 '🐼', '1f43c', 'animal'],
            ['frog',             '青蛙',   'frog 青蛙 呱',               '🐸', '1f438', 'animal'],
            ['penguin',          '企鹅',   'penguin 企鹅',               '🐧', '1f427', 'animal'],
            ['apple',            '苹果',   'apple 苹果',                 '🍎', '1f34e', 'food'],
            ['lemon',            '柠檬',   'lemon 柠檬 酸',              '🍋', '1f34b', 'food'],
            ['banana',           '香蕉',   'banana 香蕉',                '🍌', '1f34c', 'food'],
            ['watermelon',       '西瓜',   'watermelon 西瓜 夏天',       '🍉', '1f349', 'food'],
            ['coffee',            '咖啡',   'coffee 咖啡',                '☕', '2615', 'food'],
            ['cake',             '蛋糕',   'cake 蛋糕 生日',             '🎂', '1f382', 'food'],
            ['beer',             '啤酒',   'beer 啤酒 干杯',             '🍺', '1f37a', 'food'],
            ['fire',             '火',     'fire 火 666',                '🔥', '1f525', 'object'],
            ['star',             '星',     'star 星',                    '⭐', '2b50', 'object'],
            ['sparkles',         '闪光',   'sparkles 闪光',              '✨', '2728', 'object'],
            ['100',              '100分',  '100 满分',                   '💯', '1f4af', 'object'],
            ['zzz',              'Zzz',    'zzz 睡觉',                   '💤', '1f4a4', 'object'],
            ['heart',            '心',     'heart 心 喜欢',              '❤', '2764', 'symbol'],
            ['yellow_heart',     '黄心',   'yellow_heart 黄心',          '💛', '1f49b', 'symbol'],
            ['blue_heart',       '蓝心',   'blue_heart 蓝心',            '💙', '1f499', 'symbol'],
            ['purple_heart',     '紫心',   'purple_heart 紫心',          '💜', '1f49c', 'symbol'],
            ['black_heart',      '黑心',   'black_heart 黑心',           '🖤', '1f5a4', 'symbol'],
            ['broken_heart',     '心碎',   'broken_heart 心碎',          '💔', '1f494', 'symbol'],
            ['two_hearts',       '双心',   'two_hearts 双心',            '💕', '1f495', 'symbol'],
            ['sparkling_heart',  '闪心',   'sparkling_heart 闪心',       '💖', '1f496', 'symbol'],
            ['check',            '对勾',   'check 对勾',                 '✅', '2705', 'symbol'],
            ['cross',            '叉',     'cross 叉 no',                '❌', '274c', 'symbol'],
            ['question',         '问号',   'question 问号',              '❓', '2753', 'symbol'],
            ['exclamation',      '感叹号', 'exclamation 感叹号',         '❗', '2757', 'symbol'],
            ['warning',          '警告',   'warning 警告',               '⚠', '26a0', 'symbol'],
            ['speech',           '对话泡', 'speech 对话',                '💬', '1f4ac', 'symbol'],
            ['eyes',             '眼睛',   'eyes 眼睛',                  '👀', '1f440', 'symbol'],
        ];

        $insItem = $pdo->prepare("INSERT IGNORE INTO `{$prefix}emoji_items` (pack_id,code,name,keywords,`char`,`image`,category,sort_order,enabled,created_at) VALUES (?,?,?,?,?,?,?,?,1,?)");

        // unicode 包：只写 char（image=null）
        foreach ($emojiData as $i => $e) {
            $insItem->execute([$packId['unicode'], $e[0], $e[1], $e[2], $e[3], null, $e[5], $i, $now]);
        }

        echo "<span class='ok'>[OK]</span> 已初始化 " . count($packs) . " 个默认包 / " . count($emojiData) . " 个表情\n";
        $success++;
    } catch (PDOException $e) {
        echo "<span class='fail'>[失败]</span> 升级 62（表情系统）: " . $e->getMessage() . "\n";
    }

    /* ========== 升级 63：替换 owo 颜文字包为 2010HCY/OwO 项目 ========== */
    // 用户要的是 GitHub 2010HCY/OwO 项目的真实图片资源（BiliBili 小黄脸 + BiliBiliTV 小电视 GIF + 热词系列），
    // 替换之前的 unicode 颜文字包。数据从 owo.json 解析，文件从 raw.githubusercontent.com 下载到本地 uploads/emoji/owo/。
    try {
        echo "升级 63：替换 owo 表情包为 2010HCY/OwO 图片资源...\n";
        @set_time_limit(600);

        // owo.json 仅从本地 install/owo.json 读取（服务器通常无法访问外网 / 被防火墙拦截，
        // 在升级脚本里做远程下载会卡死并导致整页空白）。请在你本地有网的机器下载后 SFTP 上传到该路径。
        $localOwoJson = ROOT_PATH . '/install/owo.json';
        $owoJson = false;
        if (file_exists($localOwoJson) && filesize($localOwoJson) > 100) {
            $owoJson = file_get_contents($localOwoJson);
            echo "  - 使用本地 owo.json（" . strlen($owoJson) . " bytes）\n";
        }
        if (!$owoJson) {
            echo "  <span class='fail'>[跳过]</span> 未找到 install/owo.json，升级 63 跳过 owo 包（不影响其它升级）。\n";
            echo "     → 在能联网的机器执行：curl -L https://raw.githubusercontent.com/2010HCY/OwO/main/owo.json -o owo.json\n";
            echo "       再上传到 install/owo.json 后重跑，即可补上 owo 包。\n";
            $skipped++;
            throw new Exception('SKIP_OWO'); // 仅用于跳出本升级；下方 catch 识别后按跳过处理
        }
        $owoAll = json_decode($owoJson, true);
        if (!is_array($owoAll)) {
            throw new Exception('owo.json 格式错误');
        }

        // 本地子目录 / owo.json key / GitHub 远程子目录映射
        $subMap = [
            'bilibili' => ['key' => '小黄脸', 'remote' => 'BiliBili', 'name' => 'BiliBili 小黄脸', 'sort' => 3],
            'bili_tv'  => ['key' => 'BiliBili小电视', 'remote' => 'BiliBiliTV', 'name' => 'BiliBiliTV 小电视', 'sort' => 4],
            'hotwords' => ['key' => '热词', 'remote' => '热词系列', 'name' => 'OwO 热词', 'sort' => 5],
        ];

        $owoDir = PUBLIC_PATH . '/uploads/emoji/owo';
        @mkdir($owoDir, 0755, true);

        // 1. 删除旧 owo（unicode 颜文字）pack 及其 items
        $oldOwoId = $pdo->query("SELECT id FROM `{$prefix}emoji_packs` WHERE slug='owo'")->fetchColumn();
        if ($oldOwoId) {
            $pdo->prepare("DELETE FROM `{$prefix}emoji_items` WHERE pack_id=?")->execute([$oldOwoId]);
            $pdo->prepare("DELETE FROM `{$prefix}emoji_packs` WHERE id=?")->execute([$oldOwoId]);
            echo "  - 已清理旧 owo 颜文字包（id={$oldOwoId}）\n";
        }

        // 2. 创建/更新三个 2010HCY 子 pack，cd 直接是 jsdelivr CDN 直链模板（{code} 占位符会被 emoji_items.image 替换）
        //    注：is_system 故意 = 0。虽然数据来自 owo.json，但应允许站长改 name/sort/cd/cover/启用状态，
        //        否则会让编辑页面字段全灰。真正"不可删"的保护对象只有升级 62 的 unicode/owo 两套。
        $packIds = [];
        foreach ($subMap as $slug => $meta) {
            $cd = 'https://cdn.jsdelivr.net/gh/2010HCY/OwO@main/' . $meta['remote'] . '/{code}';
            $stmt = $pdo->prepare("SELECT id FROM `{$prefix}emoji_packs` WHERE slug=?");
            $stmt->execute([$slug]);
            $existingId = $stmt->fetchColumn();
            if ($existingId) {
                $pdo->prepare("UPDATE `{$prefix}emoji_packs` SET name=?,type='image',cd=?,sort_order=?,enabled=1 WHERE id=?")
                    ->execute([$meta['name'], $cd, $meta['sort'], $existingId]);
                $pdo->prepare("DELETE FROM `{$prefix}emoji_items` WHERE pack_id=?")->execute([$existingId]);
                $packIds[$slug] = $existingId;
            } else {
                $pdo->prepare("INSERT INTO `{$prefix}emoji_packs` (name,slug,type,cd,sort_order,enabled,is_system,created_at,updated_at) VALUES (?,?,'image',?,?,1,0,NOW(),NOW())")
                    ->execute([$meta['name'], $slug, $cd, $meta['sort']]);
                $packIds[$slug] = $pdo->lastInsertId();
            }
        }

        // 3. 写入 items（image 存文件名，渲染时由 render_emoji 拼成 CDN 直链；不在服务端下载任何文件）
        $insItem = $pdo->prepare("INSERT INTO `{$prefix}emoji_items` (pack_id,code,name,keywords,`char`,image,category,sort_order,enabled,created_at) VALUES (?,?,?,?,?,?,?,?,1,NOW())");
        $totalItems = 0;

        foreach ($subMap as $slug => $meta) {
            $container = isset($owoAll[$meta['key']]['container']) ? $owoAll[$meta['key']]['container'] : [];
            $sort = 1;
            foreach ($container as $row) {
                // 从 icon 的 URL 末尾提取文件名（icon 形如 <img src="https://owo.hcyhub.com/BiliBili/微笑.png">）
                $file = '';
                if (preg_match('/\/([^"\/?]+\.[a-z]+)"/i', $row['icon'] ?? '', $m)) {
                    $file = $m[1];
                }
                if (!$file) continue;
                $name = $row['text'] ?: $file;
                $code = $slug . '_' . str_pad((string)$sort, 3, '0', STR_PAD_LEFT);
                $cat = $slug === 'hotwords' ? 'hot' : 'face';

                // 写入 item（image 存文件名；渲染时由 render_emoji 拼成 CDN 直链）
                $insItem->execute([$packIds[$slug], $code, $name, $name, '', $file, $cat, $sort]);
                $totalItems++;
                $sort++;
            }
        }

        echo "  - 新增 3 个子 pack（bilibili / bili_tv / hotwords），共 {$totalItems} 个表情（CDN 直链，未下载）\n";
        echo "<span class='ok'>[OK]</span> 升级 63 完成\n";
        $success++;
    } catch (Exception $e) {
        if ($e->getMessage() !== 'SKIP_OWO') {
            echo "<span class='fail'>[失败]</span> 升级 63: " . $e->getMessage() . "\n";
            $skipped++;
        }
    }

    /* ========== 升级 64：清理已移除的 Twemoji 表情包 ==========
     * 背景：旧版默认三套包含 twemoji（Twitter 开源）。因 emoji_items.code UNIQUE 约束 +
     * unicode/twemoji 共享同一套 code，导致 twemoji 的 items 全部被 INSERT IGNORE 跳过，
     * 出现「Twemoji tab 显示但暂无表情」的 bug。现已从升级 62 默认包中移除 twemoji，
     * 本升级负责把历史部署残留的 twemoji pack 及其 items 清掉，保持数据干净。
     */
    try {
        echo "升级 64：清理 Twemoji 残留数据...\n";

        $twId = $pdo->query("SELECT id FROM `{$prefix}emoji_packs` WHERE slug='twemoji'")->fetchColumn();
        if ($twId) {
            // 注意：emoji_items.pack_id 有 INDEX，不是 FK（无 ON DELETE CASCADE），需先删 items 再删 pack
            $pdo->prepare("DELETE FROM `{$prefix}emoji_items` WHERE pack_id=?")->execute([$twId]);
            $pdo->prepare("DELETE FROM `{$prefix}emoji_packs` WHERE id=?")->execute([$twId]);
            echo "  - 已清理 slug='twemoji' pack（id={$twId}）及其 items\n";
        } else {
            echo "  - 未发现 Twemoji 残留，跳过清理\n";
        }

        echo "<span class='ok'>[OK]</span> 升级 64 完成\n";
        $success++;
    } catch (Exception $e) {
        echo "<span class='fail'>[失败]</span> 升级 64: " . $e->getMessage() . "\n";
        $skipped++;
    }

    /* ========== 升级 65：BiliBili/BiliBiliTV/OwO热词 三个子包从系统标志降级 ==========
     * 背景：早期升级 63 把这三个由 owo.json 数据驱动的子包错标为 is_system=1，
     * 导致后台「编辑」页所有字段 disabled、按钮文案「保存（仅更新启用状态）」，
     * 站长无法修改 name / cd / cover / sort_order。本升级把它们降级为 is_system=0，
     * 让站长恢复对这三条数据子包的正常编辑能力（unicode/owo 颜文字两套仍是 is_system=1）。
     */
    try {
        echo "升级 65：BiliBili 子包降级（is_system 1→0）...\n";

        $subSlugs = ['bilibili', 'bili_tv', 'hotwords'];
        $stmt = $pdo->prepare("UPDATE `{$prefix}emoji_packs` SET is_system=0, updated_at=NOW() WHERE slug IN (?, ?, ?) AND is_system=1");
        $stmt->execute($subSlugs);
        $aff = $stmt->rowCount();
        if ($aff > 0) {
            echo "  - 已将 {$aff} 个子包降级为 is_system=0（slug: " . implode(', ', $subSlugs) . "）\n";
        } else {
            echo "  - 未发现需要降级的子包（可能已是最新的 0），跳过\n";
        }

        echo "<span class='ok'>[OK]</span> 升级 65 完成\n";
        $success++;
    } catch (Exception $e) {
        echo "<span class='fail'>[失败]</span> 升级 65: " . $e->getMessage() . "\n";
        $skipped++;
    }

    /* ========== 升级 66：删除「颜文字」表情包（slug='owo'） ==========
     * 背景：升级 62 默认安装的「OwO 颜文字」Unicode 文字表情包共 32 个，
     * 用户决定停用并整包删除。emoji_items 同步清除，emoji_packs 该 slug 行移除。
     * 幂等：先按 slug 查 id，没找到即跳过；多次运行安全。
     */
    try {
        echo "升级 66：删除「颜文字」表情包（slug=owo）...\n";

        $id = $pdo->prepare("SELECT id FROM `{$prefix}emoji_packs` WHERE slug=?");
        $id->execute(['owo']);
        $owoId = $id->fetchColumn();

        if (!$owoId) {
            echo "  - 未找到 slug='owo' 的表情包（可能已删除或本就没装），跳过\n";
        } else {
            // 先 items，再 pack
            $delItems = $pdo->prepare("DELETE FROM `{$prefix}emoji_items` WHERE pack_id=?");
            $delItems->execute([$owoId]);
            $itemAff = $delItems->rowCount();

            $delPack = $pdo->prepare("DELETE FROM `{$prefix}emoji_packs` WHERE id=?");
            $delPack->execute([$owoId]);
            $packAff = $delPack->rowCount();

            echo "  - 关联表情 {$itemAff} 个已删，pack 行 {$packAff} 条已删\n";
        }

        echo "<span class='ok'>[OK]</span> 升级 66 完成\n";
        $success++;
    } catch (Exception $e) {
        echo "<span class='fail'>[失败]</span> 升级 66: " . $e->getMessage() . "\n";
        $skipped++;
    }

    /* ========== 升级 67：缩短默认表情包名，避免窄屏 tab 行被遮挡 ==========
     * 背景：升级 62/63 默认装了 4 个包，名字偏长（Unicode 原生 / BiliBili 小黄脸 /
     *        BiliBiliTV 小电视 / OwO 热词），合计约 480px，在 340px 的桌面表情面板里
     *        一行放不下。窄屏（手机/平板）底部抽屉更窄，必然遮挡。
     * 方案：把默认长名改短（原生 / 小黄脸 / 小电视 / 热词），从源头消除遮挡。
     * 幂等：仅当当前 name 等于出厂默认长名时才改，站长已自定义的名字不受影响。
     *        多次运行安全（第二次 WHERE name=旧名 已不匹配，不会重复改）。
     */
    try {
        echo "升级 67：缩短默认表情包名...\n";
        $renameMap = [
            'unicode'  => ['Unicode 原生'      => '原生'],
            'bilibili' => ['BiliBili 小黄脸'   => '小黄脸'],
            'bili_tv'  => ['BiliBiliTV 小电视' => '小电视'],
            'hotwords' => ['OwO 热词'          => '热词'],
        ];
        $cnt = 0;
        foreach ($renameMap as $slug => $pair) {
            foreach ($pair as $old => $new) {
                $stmt = $pdo->prepare("UPDATE `{$prefix}emoji_packs` SET name=?, updated_at=NOW() WHERE slug=? AND name=?");
                $stmt->execute([$new, $slug, $old]);
                $cnt += $stmt->rowCount();
            }
        }
        echo "  - 已重命名 {$cnt} 个默认包\n";
        echo "<span class='ok'>[OK]</span> 升级 67 完成\n";
        $success++;
    } catch (Exception $e) {
        echo "<span class='fail'>[失败]</span> 升级 67: " . $e->getMessage() . "\n";
        $skipped++;
    }

    /* ========== 升级 68：posts 表加 cover_image 列（卡片式版式封面图） ==========
     * 背景：版式设置新增「卡片式」，每张卡片需要一张封面图作为主视觉。
     *   用户发布时可显式上传封面图；未上传时回退到 images_arr[0]（帖子第一张内联图）。
     * 幂等：先查 INFORMATION_SCHEMA 确认列不存在再 ADD COLUMN。
     */
    try {
        $postsTbl = $prefix . 'posts';
        $colStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'cover_image'");
        $colStmt->execute([$postsTbl]);
        if ((int)$colStmt->fetchColumn() > 0) {
            echo "<span style='color:#bbb;'>[跳过]</span> 字段已存在: ALTER TABLE `{$postsTbl}` ADD COLUMN `cover_image` ...\n";
        } else {
            $pdo->exec("ALTER TABLE `{$postsTbl}` ADD COLUMN `cover_image` VARCHAR(500) DEFAULT NULL COMMENT '卡片封面图 URL（卡片式版式作为卡片主视觉；空时回退到 images_arr[0]）' AFTER `deleted_by`");
            echo "<span class='ok'>[OK]</span> 已添加 posts.cover_image 列\n";
        }
        $success++;
    } catch (Exception $e) {
        echo "<span class='fail'>[失败]</span> 升级 68（cover_image 列）: " . $e->getMessage() . "\n";
        $skipped++;
    }

    echo "\n升级完成：成功 {$success} 项，跳过 {$skipped} 项\n";
    echo "</div><a class='btn' href='../index.php'>返回首页</a>";
} catch (Exception $e) {
    echo "<span class='fail'>升级失败：" . $e->getMessage() . "</span></div>";
}
echo '</body></html>';
