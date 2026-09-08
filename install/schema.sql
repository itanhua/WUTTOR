-- 论坛数据库结构
-- 表前缀 {prefix}（安装时自动替换）

SET FOREIGN_KEY_CHECKS=0;
SET NAMES utf8mb4;

-- 用户表
CREATE TABLE IF NOT EXISTS `{prefix}users` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `email_verified` TINYINT NOT NULL DEFAULT 0,
  `phone` VARCHAR(20) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `nickname` VARCHAR(50) DEFAULT NULL,
  `bio` TEXT,
  `role` VARCHAR(20) NOT NULL DEFAULT 'user' COMMENT 'user/certified_user/moderator/admin/super_admin/webmaster',
  `invited_by` BIGINT DEFAULT NULL COMMENT '邀请人 users.id（邀请注册绑定）',
  `invite_code` VARCHAR(32) DEFAULT NULL COMMENT '注册时使用的邀请码',
  `is_certified` TINYINT NOT NULL DEFAULT 0,
  `certified_at` DATETIME DEFAULT NULL,
  `status` TINYINT NOT NULL DEFAULT 1,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `token` BIGINT NOT NULL DEFAULT 0 COMMENT '主流通积分 Token（可获取/消耗/兑换，永久不清零，最低0）',
  `byte` BIGINT NOT NULL DEFAULT 0 COMMENT '成长值 Byte（仅获取不可消耗，决定9级阶梯）',
  `level` TINYINT NOT NULL DEFAULT 1 COMMENT '当前等级（1-9）',
  `points_banned_until` DATETIME DEFAULT NULL COMMENT '积分获取封禁到期时间（风控频控：高频触发后封禁至当日 23:59:59）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 积分流水表（Token/Byte 获取与消耗记录；唯一键防重复加分/扣减）
CREATE TABLE IF NOT EXISTS `{prefix}points_log` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT NOT NULL COMMENT 'users.id',
  `currency` VARCHAR(10) NOT NULL COMMENT 'token / byte',
  `type` VARCHAR(10) NOT NULL COMMENT 'earn / spend',
  `source` VARCHAR(40) NOT NULL COMMENT '规则 code（如 post_create / daily_sign）',
  `source_id` BIGINT DEFAULT NULL COMMENT '关联对象 id（帖子/评论/签到记录等）',
  `amount` BIGINT NOT NULL COMMENT '变动数量（正=增加，负=扣减）',
  `balance_after` BIGINT NOT NULL DEFAULT 0 COMMENT '变动后该币种余额',
  `remark` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_unique` (`user_id`, `currency`, `source`, `source_id`, `type`),
  KEY `idx_user` (`user_id`),
  KEY `idx_source` (`source`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 积分规则表（获取/消耗动作的数值配置，后台可改，代码零硬编码）
CREATE TABLE IF NOT EXISTS `{prefix}point_rules` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(40) NOT NULL COMMENT '规则 code（如 post_create），全局唯一',
  `name` VARCHAR(100) NOT NULL COMMENT '规则显示名',
  `type` VARCHAR(10) NOT NULL COMMENT 'earn / spend',
  `currency` VARCHAR(10) NOT NULL COMMENT 'token / byte',
  `amount` INT NOT NULL DEFAULT 0 COMMENT '变动数值（earn 为正，spend 为正值代表扣减额）',
  `enabled` TINYINT NOT NULL DEFAULT 1 COMMENT '1 启用 0 停用',
  `daily_cap` INT NOT NULL DEFAULT 0 COMMENT '单日获取上限（仅 earn 生效，0=不限；风控频控用）',
  `amount_min` INT NOT NULL DEFAULT 0 COMMENT '随机区间下限（仅 earn 生效；0 或 max<min 表示用固定 amount）',
  `amount_max` INT NOT NULL DEFAULT 0 COMMENT '随机区间上限（需>=amount_min>0 才启用随机发放）',
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 每日签到记录表（user_id + sign_date 唯一，幂等防重复签到）
CREATE TABLE IF NOT EXISTS `{prefix}sign_logs` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT NOT NULL COMMENT 'users.id',
  `sign_date` DATE NOT NULL COMMENT '签到日期（Y-m-d）',
  `streak` INT NOT NULL DEFAULT 1 COMMENT '截至当日连续签到天数',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_date` (`user_id`, `sign_date`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户等级表（可自定义名称、所需 Byte、加成系数、特权描述、动态追加等级）
CREATE TABLE IF NOT EXISTS `{prefix}user_levels` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 币种登记表（内置 token/byte + 自定义币种；后台可增删改、启停、排序、配图标）
CREATE TABLE IF NOT EXISTS `{prefix}currencies` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(20) NOT NULL COMMENT '币种代码，全局唯一，如 token/byte/diamond',
  `name` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '显示名',
  `symbol` VARCHAR(8) NOT NULL DEFAULT '' COMMENT '符号 / 字符',
  `icon` VARCHAR(16) NOT NULL DEFAULT '' COMMENT '图标字符或类名',
  `icon_svg` MEDIUMTEXT DEFAULT NULL COMMENT '后台自上传的 SVG 图标（原始 XML）；优先于 icon 字符显示',
  `type` VARCHAR(16) NOT NULL DEFAULT 'consumable' COMMENT 'consumable 可消耗 / growth 成长值 / normal 普通',
  `enabled` TINYINT NOT NULL DEFAULT 1,
  `is_system` TINYINT NOT NULL DEFAULT 0 COMMENT '1=系统内置(token/byte)不可删',
  `sort_order` INT NOT NULL DEFAULT 0,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户自定义币种余额表（EAV：仅存自定义币种；token/byte 仍在 users 表列）
CREATE TABLE IF NOT EXISTS `{prefix}user_balances` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT NOT NULL,
  `currency` VARCHAR(20) NOT NULL COMMENT 'currencies.code',
  `balance` BIGINT NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_currency` (`user_id`, `currency`),
  KEY `idx_currency` (`currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 自助置顶记录（用户花 Token 按时长购买；排序时叠加管理员 pin_scope）
CREATE TABLE IF NOT EXISTS `{prefix}self_pin` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 打赏流水（双向：打赏者 spend + 受赏者 earn，同一事务内完成）
CREATE TABLE IF NOT EXISTS `{prefix}reward_log` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `post_id` BIGINT NOT NULL COMMENT '被打赏的帖子',
  `comment_id` BIGINT DEFAULT NULL COMMENT '若为评论打赏则填评论 id',
  `from_uid` BIGINT NOT NULL COMMENT '打赏者',
  `to_uid` BIGINT NOT NULL COMMENT '受赏者',
  `token` BIGINT NOT NULL DEFAULT 0 COMMENT '打赏 Token 数量',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_post` (`post_id`),
  KEY `idx_from` (`from_uid`),
  KEY `idx_to` (`to_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 「记住我」持久化登录 token 表（cookie 仅存随机 token，此处存 SHA-256 哈希）
CREATE TABLE IF NOT EXISTS `{prefix}remember_tokens` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT NOT NULL COMMENT 'users.id',
  `token_hash` VARCHAR(64) NOT NULL COMMENT '随机 token 的 SHA-256 哈希（不存明文）',
  `expires_at` DATETIME NOT NULL COMMENT '过期时间',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 板块表
CREATE TABLE IF NOT EXISTS `{prefix}categories` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `icon` VARCHAR(255) DEFAULT NULL,
  `color` VARCHAR(7) DEFAULT NULL COMMENT '板块标签颜色，如 #ea6f5a',
  `moderator_id` BIGINT DEFAULT NULL,
  `is_certification_required` TINYINT NOT NULL DEFAULT 0,
  `browse_roles` VARCHAR(255) DEFAULT NULL COMMENT '允许浏览的角色，逗号分隔；为空表示所有登录/游客',
  `publish_roles` VARCHAR(255) DEFAULT NULL COMMENT '允许发表的角色，逗号分隔；为空表示所有登录用户',
  `sort_order` INT NOT NULL DEFAULT 0,
  `post_count` INT NOT NULL DEFAULT 0,
  `status` TINYINT NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 帖子表
CREATE TABLE IF NOT EXISTS `{prefix}posts` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT NOT NULL,
  `category_id` BIGINT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `content` TEXT,
  `images` TEXT,
  `attachments` TEXT COMMENT '附件JSON数组：[{name,url,size}]',
  `is_pinned` TINYINT NOT NULL DEFAULT 0,
  `pin_scope` TINYINT NOT NULL DEFAULT 0 COMMENT '0 不置顶 1 本版置顶 2 全局置顶',
  `self_pin_until` DATETIME DEFAULT NULL COMMENT '自助置顶到期时间（用户花 Token 购买，独立于管理员 pin_scope）',
  `is_essence` TINYINT NOT NULL DEFAULT 0,
  `view_count` INT NOT NULL DEFAULT 0,
  `like_count` INT NOT NULL DEFAULT 0,
  `comment_count` INT NOT NULL DEFAULT 0,
  `collect_count` INT NOT NULL DEFAULT 0,
  `is_closed` TINYINT NOT NULL DEFAULT 0 COMMENT '0 正常 1 已关闭（仅可浏览，禁止回复）',
  `is_auction` TINYINT NOT NULL DEFAULT 0 COMMENT '0 普通帖 1 拍卖帖',
  `start_price` DECIMAL(12,2) DEFAULT NULL COMMENT '起拍价',
  `step_price` DECIMAL(12,2) NOT NULL DEFAULT 10.00 COMMENT '最小加价幅度',
  `current_price` DECIMAL(12,2) DEFAULT NULL COMMENT '当前最高出价',
  `bid_count` INT NOT NULL DEFAULT 0 COMMENT '出价人数',
  `end_time` DATETIME DEFAULT NULL COMMENT '拍卖结拍时间',
  `extended_minutes` INT NOT NULL DEFAULT 0 COMMENT '累计延时分钟数（结拍前3分钟出价则延5分钟）',
  `last_reply_at` DATETIME DEFAULT NULL COMMENT '最近一次被回复（含楼中楼）的时间，用于首页/板块页置顶贴之下的自动顶起排序',
  `url_slug` VARCHAR(200) DEFAULT NULL COMMENT 'URL slug（标题转 ASCII 生成；标题修改时不更新，保持外链稳定）',
  `status` TINYINT NOT NULL DEFAULT 1,
  `deleted_at` DATETIME DEFAULT NULL COMMENT '回收站：进入删除态的时间戳，满7天自动清空',
  `deleted_by` BIGINT DEFAULT NULL COMMENT '回收站：操作人 user_id（系统自动清理时为 NULL）',
  `cover_image` VARCHAR(500) DEFAULT NULL COMMENT '卡片封面图 URL（卡片式版式作为卡片主视觉；空时回退到 images_arr[0]）',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_pinned` (`is_pinned`),
  KEY `idx_last_reply` (`last_reply_at`),
  UNIQUE KEY `uk_url_slug` (`url_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 拍卖出价记录表
CREATE TABLE IF NOT EXISTS `{prefix}bids` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `post_id` BIGINT NOT NULL,
  `user_id` BIGINT NOT NULL,
  `price` DECIMAL(12,2) NOT NULL COMMENT '出价金额',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1有效 0被超越/撤销',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_post` (`post_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 评论表
CREATE TABLE IF NOT EXISTS `{prefix}comments` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `post_id` BIGINT NOT NULL,
  `user_id` BIGINT NOT NULL,
  `parent_id` BIGINT DEFAULT NULL,
  `content` TEXT NOT NULL,
  `image` TEXT DEFAULT NULL COMMENT '评论图片（仅顶层回复支持），JSON 数组 [相对路径, ...]，最多 9 张',
  `like_count` INT NOT NULL DEFAULT 0,
  `is_hidden` TINYINT NOT NULL DEFAULT 0,
  `status` TINYINT NOT NULL DEFAULT 1,
  `deleted_at` DATETIME DEFAULT NULL COMMENT '回收站：进入删除态的时间戳，满7天自动清空',
  `deleted_by` BIGINT DEFAULT NULL COMMENT '回收站：操作人 user_id（系统自动清理时为 NULL）',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_post` (`post_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 认证申请表
CREATE TABLE IF NOT EXISTS `{prefix}certifications` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT NOT NULL,
  `real_name` VARCHAR(50) NULL COMMENT '真实姓名（兼容老实名认证；新增认证项目组无此字段时可空）',
  `id_card` VARCHAR(255) NULL COMMENT '身份证号（加密后存储）；非实名认证组可空',
  `phone` VARCHAR(20) NULL COMMENT '手机号；非实名认证组可空',
  `id_card_front` VARCHAR(255) DEFAULT NULL,
  `id_card_back` VARCHAR(255) DEFAULT NULL,
  `hand_photo` VARCHAR(255) DEFAULT NULL,
  `extra_note` TEXT,
  `form_data` TEXT,
  `group_id` BIGINT NOT NULL DEFAULT 1 COMMENT '所属认证项目组，1=实名认证',
  `status` TINYINT NOT NULL DEFAULT 0,
  `reject_reason` VARCHAR(255) DEFAULT NULL,
  `reviewer_id` BIGINT DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_group` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 认证项目组（多套认证：实名认证/工作认证/技能认证...）
CREATE TABLE IF NOT EXISTS `{prefix}certification_groups` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL COMMENT '认证项目名称（前台 tab 标题）',
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` TINYINT NOT NULL DEFAULT 1,
  `icon_svg` TEXT COMMENT '该认证项目独立图标SVG',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 认证项配置表
CREATE TABLE IF NOT EXISTS `{prefix}certification_items` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `group_id` BIGINT NOT NULL DEFAULT 1 COMMENT '所属认证项目组',
  `name` VARCHAR(50) NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  `type` VARCHAR(20) NOT NULL DEFAULT 'text',
  `options` TEXT,
  `required` TINYINT NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` TINYINT NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_group` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 邮箱验证码表
CREATE TABLE IF NOT EXISTS `{prefix}email_codes` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(100) NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `purpose` VARCHAR(20) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`, `purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 互动关系表
CREATE TABLE IF NOT EXISTS `{prefix}interactions` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT NOT NULL,
  `target_type` VARCHAR(20) NOT NULL,
  `target_id` BIGINT NOT NULL,
  `action_type` VARCHAR(20) NOT NULL,
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_target` (`target_type`, `target_id`),
  UNIQUE KEY `uk_interaction` (`user_id`, `target_type`, `target_id`, `action_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 私信表
CREATE TABLE IF NOT EXISTS `{prefix}messages` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `from_user_id` BIGINT NOT NULL,
  `to_user_id` BIGINT NOT NULL,
  `content` TEXT NOT NULL,
  `is_read` TINYINT NOT NULL DEFAULT 0,
  `parent_id` BIGINT DEFAULT NULL,
  `status` TINYINT NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_to_user` (`to_user_id`, `is_read`),
  KEY `idx_from_user` (`from_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 通知表
CREATE TABLE IF NOT EXISTS `{prefix}notifications` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT NOT NULL,
  `type` VARCHAR(30) NOT NULL,
  `content` VARCHAR(255) DEFAULT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT NOT NULL DEFAULT 0,
  `is_system_notification` TINYINT NOT NULL DEFAULT 0 COMMENT '站长通过系统通知菜单广播的官方通知=1；评论/回复/点赞/私信等用户行为触发的=0',
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`, `is_read`),
  KEY `idx_system` (`is_system_notification`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 系统通知发送历史表（站长→用户广播记录，可追溯）
CREATE TABLE IF NOT EXISTS `{prefix}system_notification_logs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 举报表
CREATE TABLE IF NOT EXISTS `{prefix}reports` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `reporter_id` BIGINT NOT NULL,
  `target_type` VARCHAR(20) NOT NULL,
  `target_id` BIGINT NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `status` TINYINT NOT NULL DEFAULT 0,
  `handler_id` BIGINT DEFAULT NULL,
  `handle_result` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 敏感词表
CREATE TABLE IF NOT EXISTS `{prefix}sensitive_words` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `word` VARCHAR(50) NOT NULL,
  `level` TINYINT NOT NULL DEFAULT 1,
  `scope` VARCHAR(20) NOT NULL DEFAULT 'all',
  `status` TINYINT NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_word` (`word`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 角色表
CREATE TABLE IF NOT EXISTS `{prefix}roles` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 权限表
CREATE TABLE IF NOT EXISTS `{prefix}permissions` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `module` VARCHAR(50) DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 角色权限关联表
CREATE TABLE IF NOT EXISTS `{prefix}role_permissions` (
  `role_id` BIGINT NOT NULL,
  `permission_id` BIGINT NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 系统设置表
CREATE TABLE IF NOT EXISTS `{prefix}settings` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `key_name` VARCHAR(50) NOT NULL,
  `value` TEXT,
  `description` VARCHAR(255) DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 邀请注册表（邀请码 + 使用状态）
CREATE TABLE IF NOT EXISTS `{prefix}invites` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(32) NOT NULL COMMENT '邀请码（唯一）',
  `inviter_id` BIGINT NOT NULL COMMENT '邀请人 users.id',
  `inviter_role` VARCHAR(20) DEFAULT NULL COMMENT '邀请人角色快照（创建时写入，便于展示）',
  `max_uses` INT NOT NULL DEFAULT 1 COMMENT '最大可使用次数',
  `used_count` INT NOT NULL DEFAULT 0 COMMENT '已使用次数',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1有效 0已停用',
  `expires_at` DATETIME DEFAULT NULL COMMENT '过期时间，NULL 表示永久有效',
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_inviter` (`inviter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;
