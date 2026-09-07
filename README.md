# 简约大气论坛+聚焦式内容创作平台

基于 PHP + MySQL 的社区论坛系统，采用原生 MVC 架构（无框架依赖、无 Composer），支持多种业务主题文章、积分体系、充值、抽奖、实名认证与表情系统等能力。

## 技术栈

- 后端：PHP 7.2+（原生，无框架依赖，无需 Composer）
- 数据库：MySQL 5.7+
- 前端：原生 HTML + CSS + JavaScript（无构建步骤）
- 认证：Session + CSRF 令牌
- 敏感数据：身份证号 AES-256-CBC 加密存储
- 部署：单入口 `index.php`，支持查询参数与 URL 重写（Pretty URL）两种路由模式

## 核心特性

- **多主题帖子**：普通帖、拍卖帖、付费帖、活动帖、悬赏帖、投票帖、辩论帖、采访帖、抽奖主题，一套帖子模型承载多种业务形态。
- **帖子列表双版式**：列表 / 卡片两种版式后台一键切换（`post_layout`，改完即时生效）；卡片版式为封面网格布局，支持发帖时上传封面图（`posts.cover_image`，未上传时自动回退首图），首页顶部配轮播图。
- **置顶体系**：全局置顶 / 本版置顶 / 自助置顶（限时）三级，排序与高亮样式按页面场景自动区分。
- **积分与货币体系**：支持自定义币种、用户等级、打赏配置与积分规则（含单日获取上限），统一入账与消费流水。
- **充值中心**：微信 / 支付宝支付、卡密兑换、充值活动（赠送 / 折扣 / 首充），配套订单管理。
- **抽奖主题**：倒计时、实物 / 虚拟 / 积分三类奖品、抽奖规则、中奖者九宫格展示、押金与退款流程。
- **实名认证**：提交真实信息 + 证件照上传，状态查看与驳回重申；认证徽章图标可在后台管理（系统图标 + 一键还原）。
- **表情系统**：`:code:` 短代码，发送后渲染为表情图标 / 图片；后台支持表情包管理。
- **通知中心**：评论 / 回复 / 点赞 / 关注 / 认证结果 / 收藏 / 帖子被购买 / 悬赏被接受 / 抽奖中奖等多类型通知。
- **私信**：会话列表、实时聊天、消息通知。
- **可配置运营**：自定义角色与权限、敏感词库（支持导入 / 导出）、导航菜单、页脚、邮件、固定连接（5 种 URL 风格）、认证项目等均可后台配置。

## 目录结构

```
forum/
├── index.php              单入口文件（路由分发）
├── .htaccess             Apache 配置（安全防护 + URL 重写入口）
├── install/              安装 / 升级程序
│   ├── index.php         安装向导（4 步）
│   ├── schema.sql        数据库建表脚本
│   ├── upgrade.php       版本升级脚本（新增表 / 字段，访问一次即可）
│   ├── style.css         安装页样式
│   └── install.lock      安装锁文件（安装后生成）
├── config/               配置目录（安装后生成，禁止 web 访问）
│   ├── database.php      数据库配置
│   └── site.php          站点配置
├── core/                 核心框架与服务（禁止 web 访问）
│   ├── Database.php      PDO 数据库连接
│   ├── Config.php        配置管理
│   ├── Model.php         查询构造器（简易 ORM，含分页 / 事务）
│   ├── Auth.php          认证与权限
│   ├── Controller.php    控制器基类（中间件）
│   ├── View.php          视图渲染
│   ├── Response.php      JSON 响应
│   ├── Router.php        路由分发
│   ├── Middleware.php    中间件
│   ├── Mailer.php        SMTP 邮件发送
│   ├── PointService.php  积分体系（等级 / 币种 / 打赏配置 / 积分规则）
│   ├── RechargeService.php 充值（微信 / 支付宝 / 卡密 / 活动）
│   ├── LotteryService.php  抽奖概率分配
│   └── helpers.php       全局辅助函数
├── app/
│   ├── Controllers/      控制器（14 个，全部由 spl_autoload 加载）
│   ├── Models/           模型目录（预留，当前以 Model 基类 + 查询构造器为主）
│   └── Middleware/       中间件目录（预留）
├── templates/            视图模板（原生 PHP，无模板引擎）
│   ├── layouts/          布局（main / auth / admin）
│   ├── home/             首页
│   ├── post/             帖子（详情 / 发布 / 编辑 / 评论项 / 拍卖 / 付费 / 活动 / 悬赏 / 采访 等）
│   ├── user/             用户（主页 / 编辑 / 关注 / 我的帖子 / 收藏 / 积分）
│   ├── auth/             登录 / 注册 / 找回密码
│   ├── certification/    认证中心
│   ├── message/          私信
│   ├── notification/     通知
│   ├── recharge/         充值中心
│   ├── search/           搜索
│   ├── admin/            后台（仪表盘 / 用户 / 板块 / 帖子 / 评论 / 认证审核 / 举报 / 回收站 / 角色 / 敏感词 / 认证项目 / 充值 / 表情 / 设置）
│   └── errors/           404
├── public/               静态资源（可公开访问）
│   ├── css/style.css     全站样式（品牌红 #ea6f5a，卡片化现代布局）
│   ├── js/               前端脚本
│   │   ├── app.js        全局交互（ajax / 弹窗 / 工具函数）
│   │   ├── captcha.js    图形验证码
│   │   ├── emoji-picker.js 表情选择器
│   │   ├── mention.js    @提及
│   │   └── usercard.js   用户悬浮卡
│   └── uploads/          上传目录（avatar / post / certification / emoji）
├── storage/              日志与缓存（禁止 web 访问）
│   ├── logs/
│   └── cache/
└── docs/                文档
    └── permalink_nginx.md 固定连接（Pretty URL）配置
```

## 控制器一览

所有控制器位于 `app/Controllers/`，由 `index.php` 的 `spl_autoload_register` 唯一加载（请勿在 `app/` 根目录放置同名控制器文件，不会被加载）。

| 控制器 | 职责 |
|--------|------|
| `HomeController` | 首页（帖子列表 / 板块筛选 / 最新·热门·精华排序）、举报、关于页 |
| `AuthController` | 登录、注册、登出、找回密码、当前用户 |
| `CaptchaController` | 图形验证码生成与校验 |
| `CertificationController` | 实名认证申请、重新申请、状态查看 |
| `EmailController` | 邮件验证码发送（找回密码 / 认证） |
| `EmojiController` | 表情列表接口 |
| `MessageController` | 私信：未读统计、会话列表、聊天、历史、发送 |
| `NotificationController` | 通知列表、单条 / 全部已读 |
| `PostController` | 帖子 CRUD、评论、点赞、收藏、打赏、置顶 / 加精 / 移动、自助置顶，以及各主题类型：拍卖出价、付费购买、活动报名、悬赏接受 / 提前结束、投票、辩论、采访 Q&A、结束采访 |
| `RechargeController` | 充值中心：卡密兑换、创建订单、订单列表、模拟支付 |
| `SearchController` | 搜索（帖子 / 用户，含敏感词过滤） |
| `UploadController` | 图片、头像、附件上传 |
| `UserController` | 用户主页、悬浮卡、资料编辑、头像、密码、关注 / 粉丝、@提及候选、我的帖子 / 收藏、邀请码 |
| `AdminController` | 后台管理：仪表盘、用户、板块、帖子、评论、认证审核、举报、回收站、角色权限、敏感词（含导入 / 导出）、认证项目与徽章图标、系统通知、充值（卡密 / 活动 / 配置 / 订单）、表情包、版式设置（列表 / 卡片）、站点 / 导航 / 页脚 / 邮件 / 固定连接设置等 |

## 帖子主题类型

同一套帖子模型通过 `type` / 扩展字段承载多种业务形态：

| 主题 | 关键能力 | 主要入口 |
|------|----------|----------|
| 普通帖 | 基础发帖、富文本、多图、板块选择 | `PostController::create/store/show` |
| 拍卖帖 | 出价竞拍、竞拍榜单（按出价降序分页）、收尾延时 | `PostController::bid` |
| 付费帖 | 付费查看 / 购买 | `PostController::payBuy` |
| 活动帖 | 报名、导出报名名单 | `PostController::eventSignup/eventExport` |
| 悬赏帖 | 接受悬赏、提前结束 | `PostController::bountyAccept/bountyEndEarly` |
| 投票帖 | 投票 | `PostController::pollVote` |
| 辩论帖 | 加入辩论 | `PostController::debateJoin` |
| 采访帖 | 记者 / 受访者 Q&A 互动、结束采访控制 | `PostController::interviewAddQa/interviewAnswer/interviewEnd` |
| 抽奖主题 | 倒计时、实物 / 虚拟 / 积分奖品、中奖九宫格、押金 / 退款 | `LotteryService` + 主题模板 |

## 部署步骤

### 1. 环境要求
- PHP >= 7.2（需 PDO MySQL、mbstring、GD、OpenSSL 扩展）
- MySQL >= 5.7
- Web 服务器：Apache（推荐）或 Nginx

### 2. 上传程序
将 `forum/` 目录整体上传到 Web 服务器站点根目录（或子目录）。

### 3. 设置目录权限
确保以下目录可写：
- `config/`（安装时写入配置）
- `public/uploads/` 及子目录（上传图片）
- `storage/` 及子目录（日志）
- `install/`（生成锁文件）

```bash
chmod -R 755 forum/
chmod -R 777 forum/config forum/public/uploads forum/storage forum/install
```

### 4. 访问安装向导
浏览器打开网站地址，未安装时会**自动跳转**到安装页面：
```
http://你的域名/forum/install/
```
按向导完成 4 步：
1. 环境检查（PHP 版本、扩展、目录权限）
2. 参数配置：数据库连接信息、网站标题 / 副标题、管理员账号 / 密码 / 邮箱
3. 执行安装（自动建库建表、创建管理员、初始化板块与角色权限、生成配置文件、加锁）
4. 安装完成

安装完成后 `install/install.lock` 生成，安装文件**自动加锁**不可再次运行。如需重装需手动删除该锁文件。

### 5. 版本升级（已安装站点）
代码更新后，访问一次升级脚本即可执行数据库升级项（新增表 / 字段）：
```
http://你的域名/forum/install/upgrade.php
```
升级脚本为独立入口（不走 `index.php`），会按版本号逐项执行；部分升级项涉及远端资源抓取，请保证执行环境可访问外网或已配置本地兜底。

### 6. 开始使用
访问首页，使用安装时设置的管理员账号登录，即可进入后台管理。

## 功能清单

### 角色体系
- 内置 6 个角色：游客（guest）、普通用户（user）、已认证用户（certified_user）、版主（moderator）、管理员（admin）、超级管理员（super_admin）。
- **支持后台自定义角色与权限**：管理员可在「角色权限」模块新增角色、分配权限，角色白名单从 `roles` 表实时读取，不硬编码。

### 前台功能
- 首页：帖子列表、板块筛选、最新 / 热门 / 精华排序、搜索
- 帖子详情：浏览、评论（嵌套回复）、点赞、收藏、举报、打赏、编辑 / 删除
- 发布 / 编辑：富文本、多图上传、板块选择、认证专区权限
- 业务主题帖：拍卖、付费、活动、悬赏、投票、辩论、采访、抽奖（见上表）
- 认证中心：提交实名认证（姓名、身份证号加密、证件照上传）、状态查看、驳回重申、认证徽章
- 个人中心：资料修改、头像上传、密码修改、我的帖子 / 收藏、关注 / 粉丝、积分资产
- 私信：会话列表、实时聊天、消息通知
- 通知中心：收藏 / 帖子被购买 / 悬赏被接受 / 抽奖中奖 / 评论 / 回复 / 点赞 / 关注 / 认证结果
- 充值中心：微信 / 支付宝、卡密兑换、订单查询
- 搜索：帖子、用户，敏感词过滤
- 邀请：生成 / 撤销邀请码

### 后台功能
- 仪表盘：用户 / 帖子 / 评论统计、注册趋势、热门排行、待办提醒
- 用户管理：列表、搜索、禁用 / 启用、删除、分配 / 撤销版主（超管）
- 板块管理：增删改、分配版主、认证专区设置
- 帖子管理：列表、搜索、删除、批量删除（超管）
- 评论管理：列表、删除、批量删除（超管）
- 认证审核：申请列表、详情（含证件照、解密身份证）、通过 / 驳回、批量审核（超管）、通知用户
- 认证项目管理：认证分组 / 认证项、图标管理（系统图标 + 一键还原默认）
- 举报处理：列表、处理（删除违规内容）/ 驳回
- 回收站：帖子 / 评论软删除恢复与彻底删除
- 角色权限：角色管理、权限分配（超管）
- 敏感词库：增删查、导入 / 导出（超管）
- 系统设置：站点标题 / 副标题、注册开关、认证频率限制、导航菜单、页脚、邮件、固定连接（5 种 URL 风格）、认证频率限制（超管）
- 充值管理：充值卡生成 / 导出 / 作废、充值活动（赠送 / 折扣 / 首充）、支付配置、订单
- 表情管理：表情包增删改、启停、表情项管理
- 系统通知：向全站发送系统通知

### 版主专属
本版帖子置顶 / 加精 / 删除 / 移动，本版评论删除 / 屏蔽。

## URL 路由

系统支持多种 URL 风格，由后台「系统管理 → 固定连接」切换（详见 `docs/permalink_nginx.md`）：

| 风格 | URL 形态 | 是否需要 `url_slug` |
|---|---|---|
| 默认（朴素） | `/index.php?r=post/show&id=123` | 否 |
| ID 型 | `/post/123` `/c/5` `/u/8` | 否 |
| 板块 + ID 型 | `/c/5/123` `/c/5` `/u/8` | 否 |
| 标题型 | `/post/123-my-slug` | 是 |
| 板块 + 标题型 | `/c/5/123-my-slug` | 是 |

内部路由格式统一为 `控制器/方法`：
- 首页：`home/index`
- 帖子详情：`post/show&id=5`
- 发布：`post/create`
- 后台：`admin/index`

> 选「标题型」或「板块 + 标题型」时，`posts` 表需有 `url_slug` 列；可访问 `install/upgrade.php` 执行升级项自动加列。

## Nginx 配置参考

```nginx
server {
    listen 80;
    server_name 你的域名;
    root /path/to/forum;
    index index.php;

    # 禁止访问敏感目录
    location ~ ^/(config|storage|core|app)/ { deny all; }
    location ~ /schema\.sql$ { deny all; }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;  # 或 unix:/var/run/php-fpm.sock
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?)$ {
        expires 30d;
    }
}
```

> 静态资源（uploads / css / js）由 `try_files` 优先直接返回，仅在路径不存在时回退到 `index.php`，避免粗粒度 `rewrite` 让静态请求也走 PHP。

## 安全说明

- 身份证号使用 AES-256-CBC 加密存储，仅管理员审核时解密可见
- API 返回时对身份证号、手机号、姓名统一脱敏
- 所有写操作通过 CSRF 令牌保护
- 敏感目录（config / storage / core / app）禁止 web 直接访问
- 用户密码使用 `password_hash`（bcrypt）存储
- 安装完成后安装文件自动加锁
- 图形验证码与邮件验证码用于注册、登录、认证等关键操作防刷

## 默认数据

安装后自动创建：
- 1 个超级管理员账号（安装时配置）
- 默认板块：综合讨论、技术分享、认证专区、站务公告
- 6 个内置角色、权限项

## 部署与缓存注意事项

改了代码线上「没生效」时，按下面顺序排查（多数情况是缓存或同步问题）：

| 现象 | 原因 | 处理 |
|---|---|---|
| 样式 / 脚本还是旧的 | 全站仅 `public/css/style.css` 一份样式表，通过 `?v=filemtime` 缓存戳刷新；但宝塔「网站加速 / 静态文件缓存」可能仍吐旧文件 | 同步文件后 `Ctrl+F5`；宝塔开了页面缓存的需手动清空或临时关闭 |
| 新增方法报「不存在」 | OPcache 缓存了旧字节码 | 入口 `index.php` 已对 `core/Auth.php` 与 `app/Controllers/*.php` 加 `opcache_invalidate` 兜底；仍报错则重启 PHP-FPM |
| 远端资源抓取失败 | 部分生产环境 `allow_url_fopen=Off` | 统一使用 `core/helpers.php::emojiHttpGet($urls, $timeout)`（curl 优先 + `file_get_contents` 兜底），HTTP 资源需多镜像 + 本地 fallback |
| 升级脚本直接 500 | `install/upgrade.php` 是独立入口（不走 `index.php`），`PUBLIC_PATH` 等常量需在文件顶部自行定义 | 升级脚本内已处理；自行改造该文件时注意保留 |

其它约定：

- 控制器**只能**放在 `app/Controllers/`，由 `spl_autoload_register` 唯一加载；`app/` 根目录的同名文件永远不会被加载。
- `core/` 下的服务类（`PointService` / `RechargeService` / `Mailer` 等）不被 autoloader 匹配，使用前必须显式 `require_once`。
- 所有 ajax POST 接口必须 try/catch 兜底（catch 内 `$this->error($e->getMessage())` 并写 `error_log`）。
- 帖子列表卡片版式依赖 `posts.cover_image` 列（升级 68），未跑升级的库会静默忽略封面、不影响发帖。

## 常见问题

**Q: 安装后访问首页白屏？**
检查 `config/database.php` 是否生成，`storage/logs/php_error.log` 是否有错误。

**Q: 图片上传失败？**
确认 `public/uploads/` 及子目录可写，PHP 配置 `upload_max_filesize` 足够。

**Q: 改了代码线上没生效？**
检查是否已同步到生产、是否清除了 OPcache / 宝塔静态缓存；新增控制器方法后通常需要重启 PHP-FPM。

**Q: 想重新安装？**
删除 `install/install.lock` 文件后重新访问 `install/`。

**Q: 如何修改站点标题？**
后台「系统设置」可修改，或直接编辑 `config/site.php`。

**Q: 切换到美化 URL 后访问 404？**
按 `docs/permalink_nginx.md` 配置服务器的 `try_files` 重写规则并 reload。

**Q: 后台切了卡片版式前台没变化？**
1) 确认已访问 `install/upgrade.php` 执行到最新升级项（卡片封面需要 `posts.cover_image` 列，升级 68）；2) 版式设置在后台「系统设置 → 版式设置」，`setting()` 每请求实时读表，改完刷新即生效，无需清缓存；3) 卡片版式目前作用于首页 / 板块页列表。
