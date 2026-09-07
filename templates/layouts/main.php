<?php
/**
 * 数据净化：$__hideFooter 来自 controller 基类注入（$hideFooter 属性 → data key=__hideFooter）。
 * 默认 true（隐藏页脚）；HomeController 重写 $hideFooter = false → 首页显示页脚。
 * 同时兼容模板端用 isset($showFooter) 强制覆盖。
 */
$__hideFooter = (bool)($__hideFooter ?? true);
$site         = Config::get('site', []);
$currentUser  = Auth::user();
$currentRoute = $_GET['r'] ?? 'home/index';
$logo         = $site['logo'] ?? '';

// SEO 字段：从站点配置（config/site.php，由 AdminController::saveSettings 写入）读取
$siteTitle    = $site['title']       ?? '论坛';
$siteSubtitle = $site['subtitle']    ?? '';
$siteDesc     = $site['description'] ?? '';
$siteKeywords = $site['keywords']    ?? '';
$siteFavicon  = $site['favicon']     ?? '';

// 读取后台配置（导航菜单等）
$siteSettings = [];
try {
    foreach (Model::query("SELECT key_name, value FROM settings") as $_r) {
        $siteSettings[$_r['key_name']] = $_r['value'];
    }
} catch (Exception $e) {}
$navMenus = json_decode($siteSettings['nav_menus'] ?? '', true);
if (!is_array($navMenus)) $navMenus = ['top' => [], 'side' => []];
$topNav  = is_array($navMenus['top']  ?? null) ? $navMenus['top']  : [];
$sideNav = is_array($navMenus['side'] ?? null) ? $navMenus['side'] : [];

// 卡片导航（扁平分组式分组二级导航，仅卡片版式生效；后台「导航设置 → 卡片导航」配置）
$__cardNavRaw = json_decode($siteSettings['card_nav'] ?? '', true);
$__cardNavGroups = is_array($__cardNavRaw['groups'] ?? null) ? $__cardNavRaw['groups'] : [];
$__cardNavGroups = array_values(array_filter($__cardNavGroups, function ($g) {
    return is_array($g) && trim((string)($g['title'] ?? '')) !== '';
}));

// 主入口（首页/发布）：后台「导航设置 → 聚焦导航」可修改；未配置或配置不完整时用默认预留
$__cardPrimary = [];
if (is_array($__cardNavRaw['primary'] ?? null)) {
    foreach ($__cardNavRaw['primary'] as $__cp) {
        if (!is_array($__cp)) continue;
        $__cpn = trim((string)($__cp['name'] ?? ''));
        $__cpu = trim((string)($__cp['url'] ?? ''));
        if ($__cpn === '' || $__cpu === '') continue;
        $__cardPrimary[] = ['name' => $__cpn, 'icon' => trim((string)($__cp['icon'] ?? '')), 'url' => $__cpu];
    }
}
if (count($__cardPrimary) < 2) {
    $__cardPrimary = [
        ['name' => '首页',  'url' => url('home/index')],
        ['name' => '发布',  'url' => url('post/create')],
    ];
}

// 2026-09-06 卡片式版式：home/index 注入 __hideSidebar=true，或后台版式设置为「卡片」时，
// 全站进入卡片模式：顶部导航不渲染、左栏渲染扁平分组式分组导航（$__cardNavGroups）、右栏整体隐藏。
$__cardLayout = !empty($__hideSidebar) || setting('post_layout', 'default') === 'card';

// 站点统计（侧栏底部）
$siteStats = ['users' => 0, 'posts' => 0, 'replies' => 0, 'latest_users' => []];
try {
    $siteStats['users'] = (int)Model::scalar('SELECT COUNT(*) FROM users WHERE status = 1');
    $siteStats['posts'] = (int)Model::scalar('SELECT COUNT(*) FROM posts WHERE status = 1');
    // 回复数：全部评论（含楼中楼 / 站队发言回复），状态正常的
    $siteStats['replies'] = (int)Model::scalar('SELECT COUNT(*) FROM comments WHERE status = 1');
    // 最新注册用户：取 6 个（按 id 倒序，等同注册先后顺序），用于侧栏展示
    $siteStats['latest_users'] = Model::query(
        'SELECT id, username, nickname, avatar FROM users WHERE status = 1 ORDER BY id DESC LIMIT 6'
    );
} catch (Exception $e) {}

// 当前用户统计
$userStats = ['posts' => 0, 'replies' => 0, 'likes' => 0, 'followers' => 0, 'collects' => 0, 'following' => 0];
if ($currentUser) {
    try {
        $userStats['posts']     = Model::table('posts')->where('user_id', $currentUser['id'])->where('status', 1)->count();
        // 回帖数：评论作者为当前用户且帖子仍可见的回复数量
        $userStats['replies']   = Model::scalar('SELECT COUNT(*) FROM comments c JOIN posts p ON c.post_id = p.id WHERE c.user_id = ? AND c.status = 1 AND p.status = 1', [$currentUser['id']]);
        $userStats['likes']     = Model::scalar('SELECT COUNT(*) FROM interactions i JOIN posts p ON i.target_id = p.id WHERE i.target_type = ? AND i.action_type = ? AND p.user_id = ? AND p.status = 1', ['post', 'like', $currentUser['id']]);
        $userStats['followers'] = Model::table('interactions')->where('target_type', 'user')->where('target_id', $currentUser['id'])->where('action_type', 'follow')->count();
        $userStats['following'] = Model::table('interactions')->where('user_id', $currentUser['id'])->where('action_type', 'follow')->count();
        $userStats['collects']  = Model::table('interactions')->where('user_id', $currentUser['id'])->where('target_type', 'post')->where('action_type', 'collect')->count();
    } catch (Exception $e) {}
}
$unreadCount = 0;
$unreadNotif = 0; // 通知未读（不含私信）
$unreadPm    = 0; // 私信未读
if ($currentUser) {
    // ✦ 私信与通知分离后：navbar 角标聚合只统计 notifications 表（非私信）。
    //   私信由 message/unreadSummary().pm 独立聚合；message/unreadCount 也已排除 type='message'。
    try {
        $unreadNotif = (int)Model::table('notifications')->where('user_id', $currentUser['id'])->where('is_read', 0)->where('type', '!=', 'message')->count();
        $unreadPm    = (int)Model::table('messages')->where('to_user_id', $currentUser['id'])->where('is_read', 0)->where('status', 1)->count();
        $unreadCount = $unreadNotif + $unreadPm;
    } catch (Exception $e) {}
}

// 把导航项 type 解析为 (url, label, icon)
// 把导航项解析为 (url, name, icon) — 现在每项固定为 {name, icon, url} 自定义结构
function nav_resolve($item) {
    $name = trim($item['name'] ?? '');
    $icon = trim($item['icon'] ?? '');
    $url  = trim($item['url']  ?? '');
    return ['url' => $url ?: '#', 'name' => $name ?: '未命名', 'icon' => $icon, 'target' => '_self'];
}

// SEO <title> 拼接规则：
//   1) 首页（pageTitle 为空或等于默认「首页」）：「$siteTitle - $siteSubtitle」
//      直接呈现后台「网站标题 - 网站副标题」格式；副标题为空时退回「$pageTitle - $siteTitle」
//   2) 其它内页有 pageTitle：pageTitle != siteTitle 时「$pageTitle - $siteTitle」
//   3) 仅显示站名
//   最终多字节安全截断到 60 字符（中文按字符计，Google 搜索结果显示上限）
$pageTitle = (string)($title ?? '');
$isHomePage = ($pageTitle === '' || $pageTitle === '首页');
if ($isHomePage && $siteSubtitle !== '') {
    $fullTitle = $siteTitle . ' - ' . $siteSubtitle;
} elseif ($pageTitle !== '' && $pageTitle !== $siteTitle) {
    $fullTitle = $pageTitle . ' - ' . $siteTitle;
} elseif ($siteSubtitle !== '') {
    $fullTitle = $siteTitle . ' - ' . $siteSubtitle;
} else {
    $fullTitle = $siteTitle;
}
if (mb_strlen($fullTitle, 'UTF-8') > 60) {
    $fullTitle = mb_substr($fullTitle, 0, 60, 'UTF-8') . '…';
}

// Meta Description：优先级 完整站点描述 > 副标题拼接 > 空（搜索结果会自己抓）
$descMeta = (string)$siteDesc;
if ($descMeta === '' && $siteSubtitle !== '') {
    $descMeta = $siteSubtitle;
}
if ($descMeta !== '' && mb_strlen($descMeta, 'UTF-8') > 160) {
    $descMeta = mb_substr($descMeta, 0, 160, 'UTF-8') . '…';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($fullTitle) ?></title>
    <?php if ($descMeta !== ''): ?>
    <meta name="description" content="<?= e($descMeta) ?>">
    <?php endif; ?>
    <?php if ($siteKeywords !== ''): ?>
    <meta name="keywords" content="<?= e($siteKeywords) ?>">
    <?php endif; ?>
    <meta name="robots" content="index,follow">
    <?php if ($siteFavicon !== ''): ?>
    <?php $favUrl = upload_url($siteFavicon); ?>
    <link rel="icon" href="<?= e($favUrl) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= e($favUrl) ?>" sizes="180x180">
    <?php else: ?>
    <link rel="icon" href="<?= e(asset('favicon.ico')) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>?v=<?= filemtime(PUBLIC_PATH . '/css/style.css') ?>">
    <!-- Font Awesome 6 free（页脚社交媒体、顶部导航、侧栏图标，统一样式前缀 fa-solid/fa-regular/fa-brands） -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/all.min.css" crossorigin="anonymous">
    <?php if (isset($extraCss)) foreach ((array)$extraCss as $css): ?>
    <link rel="stylesheet" href="<?= asset($css) ?>">
    <?php endforeach; ?>
</head>
<?php
// 平板/PC 竖屏精简：仅首页(home/index 含板块页 cat 参数)、帖子内页(post/show) 收起右栏与页脚四栏。
// 横屏与其它设备保持原规则不变。具体隐藏逻辑见 style.css 的 @media (orientation:portrait) 段。
$__condenseRoutes  = ['home/index', 'post/show'];
$__portraitCondense = in_array($currentRoute, $__condenseRoutes, true);
// 私信任何路由都加 .pm-page（用于隐藏回顶按钮 + 私信内 SPA 切换时由 JS 维护）
// message/index 带 id 时再加 .pm-chat-page（移动端隐藏 sidebar）
$__extraBodyClass = '';
if ($currentRoute === 'message/index' || $currentRoute === 'message/chat' || $currentRoute === 'notification/index') {
    $__extraBodyClass = 'pm-page';
    if ($currentRoute === 'message/index' && !empty($_GET['id'])) {
        $__extraBodyClass .= ' pm-chat-page';
    } elseif ($currentRoute === 'message/chat') {
        $__extraBodyClass .= ' pm-chat-page';
    }
}
?>
<body class="<?= (!empty($sideNav) && empty($__cardLayout) ? 'has-sidenav ' : '') . ($__cardLayout ? 'card-mode ' : '') . ($__cardLayout && !empty($__cardNavGroups) ? 'card-nav-on ' : '') . ($__portraitCondense ? 'portrait-condense' : '') . (!empty($__extraBodyClass) ? ' ' . $__extraBodyClass : '') ?>">
<?= csrf_field() ?>
<nav class="navbar">
    <div class="navbar-inner">
        <!-- 移动端菜单按钮：放在站名/logo 前面 -->
        <button type="button" class="nav-toggle" id="navToggle" aria-label="菜单">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                <path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M3 6h18M3 12h18M3 18h18"/>
            </svg>
        </button>

        <a href="<?= url('home/index') ?>" class="navbar-brand">
            <?php if ($logo): ?><img src="<?= upload_url($logo) ?>" style="height:28px;vertical-align:middle;" alt=""><?php else: ?><?= e($site['title'] ?? '论坛') ?><?php endif; ?>
        </a>

        <!-- PC/平板顶部导航（手机端由汉堡菜单抽屉承载）；卡片版式下顶部导航整体不渲染（由扁平分组式左侧导航承载） -->
        <?php if (!$__cardLayout): ?>
        <div class="navbar-nav">
            <?php if (!empty($topNav)): ?>
                <?php foreach ($topNav as $it):
                    $n = nav_resolve($it); ?>
                    <a href="<?= e($n['url']) ?>" target="<?= e($n['target']) ?>">
                        <?php if (!empty($n['icon'])): ?><i class="<?= e(fa_icon_class($n['icon'])) ?>"></i><?php endif; ?>
                        <?= e($n['name']) ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <a href="<?= url('home/index') ?>" class="<?= $currentRoute === 'home/index' ? 'active' : '' ?>">首页</a>
                <a href="<?= url('home/index', ['sort' => 'hot']) ?>">热门</a>
                <a href="<?= url('home/index', ['sort' => 'essence']) ?>">精选</a>
                <?php if (is_logged_in() && !is_banned()): ?>
                <a href="<?= url('post/create') ?>">发布</a>
                <?php elseif (is_banned()): ?>
                <span style="color:#999;cursor:not-allowed;padding:8px 12px;">发布（已禁言）</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <form class="navbar-search" action="<?= url('search/index') ?>" method="get">
            <input type="hidden" name="r" value="search/index">
            <input type="text" name="q" placeholder="搜索帖子或用户" value="<?= e($_GET['q'] ?? '') ?>">
            <button type="submit" class="navbar-search-btn">搜索</button>
        </form>

        <?php if ($__cardLayout): ?>
        <?php /* ===== 卡片版式：顶部导航重新渲染，右对齐在消息图标前面 ===== */ ?>
        <div class="navbar-nav navbar-nav-card">
            <?php if (!empty($topNav)): ?>
                <?php foreach ($topNav as $it):
                    $n = nav_resolve($it); ?>
                    <a href="<?= e($n['url']) ?>" target="<?= e($n['target']) ?>">
                        <?php if (!empty($n['icon'])): ?><i class="<?= e(fa_icon_class($n['icon'])) ?>"></i><?php endif; ?>
                        <?= e($n['name']) ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <a href="<?= url('home/index', ['sort' => 'hot']) ?>">热门</a>
                <a href="<?= url('home/index', ['sort' => 'essence']) ?>">精选</a>
                <?php if (is_logged_in() && !is_banned()): ?>
                <a href="<?= url('post/create') ?>">发布</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (is_logged_in()): ?>
        <div class="navbar-user">
            <div class="navbar-msg-wrap" id="navMsgWrap">
                <a href="javascript:;" class="navbar-icon-btn navbar-msg-link" id="navMsgBtn" aria-label="消息" onclick="toggleMsgDropdown(event)">
                    <i class="fa-solid fa-bell"></i>
                    <span id="navMsgBadge"><?= $unreadCount > 0 ? '<span class="navbar-msg-badge">' . $unreadCount . '</span>' : '' ?></span>
                </a>
                <div class="navbar-msg-dropdown" id="navbarMsgDropdown">
                    <a href="<?= url('notification/index') ?>" class="navbar-msg-item" data-type="notif">
                        <i class="fa-solid fa-bell"></i>
                        <span>通知</span>
                        <span class="navbar-msg-item-badge" id="navNotifBadge"<?= $unreadNotif > 0 ? '' : ' style="display:none;"' ?>><?= $unreadNotif > 99 ? '99+' : $unreadNotif ?></span>
                    </a>
                    <a href="<?= url('message/index') ?>" class="navbar-msg-item" data-type="pm">
                        <i class="fa-solid fa-comment-dots"></i>
                        <span>私信</span>
                        <span class="navbar-msg-item-badge" id="navPmBadge"<?= $unreadPm > 0 ? '' : ' style="display:none;"' ?>><?= $unreadPm > 99 ? '99+' : $unreadPm ?></span>
                    </a>
                </div>
            </div>
            <div class="avatar-menu-wrap" id="avatarMenuWrap">
                <a href="javascript:;" onclick="toggleAvatarMenu(event)" class="navbar-avatar-link" aria-label="账号菜单">
                    <?= avatar_html($currentUser, 36, false) ?>
                </a>
                <div class="avatar-dropdown" id="avatarDropdown">
                    <a href="<?= url('user/profile', ['id' => $currentUser['id']]) ?>"><i class="fa-solid fa-user"></i> 我的主页</a>
                    <a href="<?= url('user/points') ?>"><i class="fa-solid fa-coins"></i> 我的积分</a>
                    <a href="<?= url('recharge/index') ?>"><i class="fa-solid fa-money-bill-wave"></i> 充值中心</a>
                    <a href="<?= url('user/edit') ?>"><i class="fa-solid fa-gear"></i> 设置</a>
                    <?php if (is_admin()): ?>
                    <a href="<?= url('admin/index') ?>" class="dropdown-admin"><i class="fa-solid fa-screwdriver-wrench"></i> 后台</a>
                    <?php endif; ?>
                    <?php if (can_invite($currentUser)): ?>
                    <a href="<?= url('user/invite') ?>" class="btn-invite-menu">邀请</a>
                    <?php endif; ?>
                    <div class="divider"></div>
                    <a href="<?= url('auth/logout') ?>" class="dropdown-logout"><i class="fa-solid fa-right-from-bracket"></i> 退出</a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="navbar-user">
            <a href="<?= login_url() ?>" class="btn">登录</a>
            <a href="<?= url('auth/register') ?>" class="btn btn-primary">注册</a>
        </div>
        <?php endif; ?>
    </div>
</nav>

<!-- 移动端导航抽屉：聚合顶部导航 + 左侧导航栏内容 -->
<div class="mobile-nav-drawer" id="mobileNavDrawer">
    <div class="mobile-nav-mask" id="mobileNavMask"></div>
    <div class="mobile-nav-panel">
        <div class="mobile-nav-header">
            <span>菜单</span>
            <button type="button" class="mobile-nav-close" id="mobileNavClose" aria-label="关闭">&times;</button>
        </div>
        <div class="mobile-nav-body">
            <?php if ($__cardLayout): ?>
            <?php /* ===== 卡片版式：移动端抽屉跟随 PC，渲染卡片导航分组 ===== */ ?>
            <div class="mobile-nav-group">
                <?php foreach ($__cardPrimary as $__p):
                    $__pn = nav_resolve($__p);
                ?>
                <a href="<?= e($__pn['url']) ?>"><?php if ($__pn['icon'] !== ''): ?><i class="<?= e(fa_icon_class($__pn['icon'])) ?>"></i><?php endif; ?> <?= e($__pn['name']) ?></a>
                <?php endforeach; ?>
                <?php foreach ($__cardNavGroups as $__g): ?>
                <?php if (!empty($__g['items'])): ?>
                <?php foreach ($__g['items'] as $__it):
                    $__n = nav_resolve($__it); ?>
                <a href="<?= e($__n['url']) ?>" class="nav-sub">
                    <?php if ($__n['icon'] !== ''): ?><i class="<?= e(fa_icon_class($__n['icon'])) ?>"></i><?php endif; ?>
                    <?= e($__n['name']) ?>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="mobile-nav-group">
                <?php if (!empty($topNav)): ?>
                    <?php foreach ($topNav as $it):
                        $n = nav_resolve($it); ?>
                        <a href="<?= e($n['url']) ?>" target="<?= e($n['target']) ?>">
                            <?php if (!empty($n['icon'])): ?><i class="<?= e(fa_icon_class($n['icon'])) ?>"></i><?php endif; ?>
                            <?= e($n['name']) ?>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <a href="<?= url('home/index') ?>" class="<?= $currentRoute === 'home/index' ? 'active' : '' ?>">首页</a>
                    <a href="<?= url('home/index', ['sort' => 'hot']) ?>">热门</a>
                    <a href="<?= url('home/index', ['sort' => 'essence']) ?>">精选</a>
                    <?php if (is_logged_in() && !is_banned()): ?>
                    <a href="<?= url('post/create') ?>">发布</a>
                    <?php elseif (is_banned()): ?>
                    <span style="color:#999;cursor:not-allowed;padding:12px 16px;display:block;">发布（已禁言）</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($sideNav)): ?>
            <div class="mobile-nav-divider"></div>
            <div class="mobile-nav-group">
                <?php foreach ($sideNav as $it):
                    $n = nav_resolve($it); ?>
                    <a href="<?= e($n['url']) ?>" target="<?= e($n['target']) ?>">
                        <?php if ($n['icon'] !== ''): ?><i class="<?= e(fa_icon_class($n['icon'])) ?>"></i><?php endif; ?>
                        <?= e($n['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php endif; /* 卡片/常规抽屉分支结束 */ ?>
        </div>
    </div>
</div>

<div class="container">
    <?php if (!$__cardLayout && !empty($sideNav)): ?>
    <aside class="sidenav">
        <div class="sidenav-title"><?= e($siteSettings['side_nav_title'] ?? '导航') ?></div>
        <ul>
        <?php foreach ($sideNav as $it):
            $n = nav_resolve($it); ?>
            <li>
                <a href="<?= e($n['url']) ?>" target="<?= e($n['target']) ?>" title="<?= e($n['name']) ?>">
                    <?php if ($n['icon'] !== ''): ?>
                    <i class="<?= e(fa_icon_class($n['icon'])) ?>"></i>
                    <?php else: ?>
                    <span class="sidenav-dot"></span>
                    <?php endif; ?>
                    <span class="sidenav-label"><?= e($n['name']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
        </ul>
    </aside>
    <?php elseif ($__cardLayout && !empty($__cardNavGroups)): ?>
    <?php /* ===== 卡片版式：扁平分组式分组导航（无卡片壳/无滚动，分组标题 + 图标子项） ===== */ ?>
    <aside class="sidenav sidenav-card">
        <?php $__curPath = rtrim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/'); ?>
        <ul>
            <?php foreach ($__cardPrimary as $__pi => $__p):
                $__pn = nav_resolve($__p);
                $__pp = rtrim((string)parse_url($__pn['url'], PHP_URL_PATH), '/');
                $__pActive = ($__pp !== '' && $__pp === $__curPath);
            ?>
            <li>
                <a href="<?= e($__pn['url']) ?>" class="<?= $__pActive ? 'active' : '' ?>" title="<?= e($__pn['name']) ?>">
                    <?php if ($__pn['icon'] !== ''): ?>
                    <span class="sidenav-pri-ico <?= $__pi > 0 ? 'gray' : '' ?>"><i class="<?= e(fa_icon_class($__pn['icon'])) ?>"></i></span>
                    <?php endif; ?>
                    <span class="sidenav-label"><?= e($__pn['name']) ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php foreach ($__cardNavGroups as $__g): ?>
        <div class="sidenav-group-title">
            <?php if (!empty($__g['icon'])): ?><i class="<?= e(fa_icon_class($__g['icon'])) ?>"></i><?php endif; ?>
            <span><?= e($__g['title']) ?></span>
        </div>
        <?php if (!empty($__g['items'])): ?>
        <ul>
            <?php foreach ($__g['items'] as $__it):
                $__n = nav_resolve($__it); ?>
            <li>
                <a href="<?= e($__n['url']) ?>" title="<?= e($__n['name']) ?>">
                    <?php if ($__n['icon'] !== ''): ?>
                    <i class="<?= e(fa_icon_class($__n['icon'])) ?>"></i>
                    <?php else: ?>
                    <span class="sidenav-dot"></span>
                    <?php endif; ?>
                    <span class="sidenav-label"><?= e($__n['name']) ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php endforeach; ?>
    </aside>
    <?php endif; ?>
    <main class="content-main">
        <?php if ($flash = flash()): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <?= $content ?? '' ?>
    </main>
    <?php if ((!isset($hideSidebar) || !$hideSidebar) && empty($__cardLayout)): ?>
    <aside class="content-side">
        <?php if (is_logged_in()): ?>
        <div class="side-card">
            <h3>个人中心</h3>
            <div class="side-user-item">
                <?= avatar_html($currentUser, 80) ?>
                <div class="info">
                    <div class="name"><?= e($currentUser['nickname'] ?: $currentUser['username']) ?><?= cert_badge_html($currentUser, 15) ?><?= uid_badge($currentUser) ?><?php if (role_show_badge($currentUser['role'])): ?><span class="badge-role" style="margin-left:6px;vertical-align:middle;display:inline-block;"> <?= e(role_display_name($currentUser['role'])) ?></span><?php endif; ?></div>
                    <div class="desc"><?= render_mentions(truncate($currentUser['bio'] ?? '', 20)) ?: '这家伙很懒，什么也没留下' ?></div>
                </div>
            </div>
            <div class="side-user-stats">
                <div class="stat-item">
                    <div class="stat-num"><?= $userStats['likes'] ?></div>
                    <div class="stat-label">获赞</div>
                </div>
                <a href="<?= url('user/profile', ['id' => $currentUser['id'], 'tab' => 'posts']) ?>" class="stat-item" style="text-decoration:none;color:inherit;">
                    <div class="stat-num"><?= $userStats['posts'] ?></div>
                    <div class="stat-label">主题</div>
                </a>
                <a href="<?= url('user/profile', ['id' => $currentUser['id'], 'tab' => 'replies']) ?>" class="stat-item" style="text-decoration:none;color:inherit;">
                    <div class="stat-num"><?= $userStats['replies'] ?></div>
                    <div class="stat-label">回帖</div>
                </a>
                <a href="<?= url('user/profile', ['id' => $currentUser['id'], 'tab' => 'following']) ?>" class="stat-item" style="text-decoration:none;color:inherit;">
                    <div class="stat-num"><?= $userStats['following'] ?></div>
                    <div class="stat-label">关注</div>
                </a>
                <a href="<?= url('user/profile', ['id' => $currentUser['id'], 'tab' => 'followers']) ?>" class="stat-item" style="text-decoration:none;color:inherit;">
                    <div class="stat-num"><?= $userStats['followers'] ?></div>
                    <div class="stat-label">粉丝</div>
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="side-card text-center">
            <h3>加入社区</h3>
            <p style="color:#888;font-size:13px;margin-bottom:14px;">注册后即可发帖、评论、点赞</p>
            <a href="<?= url('auth/register') ?>" class="btn btn-primary btn-block">立即注册</a>
            <a href="<?= login_url() ?>" class="btn btn-block mt-8">已有账号，登录</a>
        </div>
        <?php endif; ?>

        <div class="side-card side-cat-nav">
            <h3>板块导航</h3>
            <div class="side-cat-list">
                <?php
                try {
                    $cats = Model::table('categories')->where('status', 1)->orderBy('sort_order', 'ASC')->get();
                } catch (Exception $e) { $cats = []; }
                foreach ($cats as $cat):
                    // 浏览角色权限：无权浏览的板块在导航中隐藏（管理员/超管始终可见）
                    if (!can_browse_category($cat)) continue;
                ?>
                <a href="<?= url('home/index', ['cat' => $cat['id']]) ?>" class="<?= (($_GET['cat'] ?? '') == $cat['id']) ? 'active' : '' ?>">
                    <span><?= e($cat['name']) ?><?= $cat['is_certification_required'] ? ' <span class="badge badge-cert-sm">认证</span>' : '' ?></span>
                    <span class="count"><?= $cat['post_count'] ?> 帖</span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php
        try {
            $hotPosts = Model::table('posts')->where('status', 1)->orderBy('view_count', 'DESC')->limit(5)->get();
        } catch (Exception $e) { $hotPosts = []; }
        if ($hotPosts):
        ?>
        <div class="side-card side-hot-posts">
            <h3 style="color:#ea6f5a;">热门推荐</h3>
            <?php foreach ($hotPosts as $i => $hp): ?>
            <div style="padding:6px 0;">
                <a href="<?= url('post/show', ['id' => $hp['id']]) ?>" style="color:#555;font-size:15px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= ($i+1) . '. ' . e($hp['title']) ?></a>
                <span style="color:#bbb;font-size:15px;"><?= $hp['view_count'] ?> 浏览 · <?= $hp['comment_count'] ?> 评论</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="side-card site-stats">
            <h3>站点统计</h3>
            <div class="site-stats-row">
                <div class="site-stats-num"><?= number_format($siteStats['users']) ?></div>
                <div class="site-stats-label">注册用户</div>
            </div>
            <div class="site-stats-row">
                <div class="site-stats-num"><?= number_format($siteStats['posts']) ?></div>
                <div class="site-stats-label">主题数</div>
            </div>
            <div class="site-stats-row">
                <div class="site-stats-num"><?= number_format($siteStats['replies']) ?></div>
                <div class="site-stats-label">回复数</div>
            </div>
            <?php if (!empty($siteStats['latest_users'])): ?>
            <div class="site-stats-latest">
                <div class="site-stats-latest-title">最新注册</div>
                <div class="site-stats-latest-list">
                    <?php foreach ($siteStats['latest_users'] as $lu):
                        $luName = trim((string)($lu['nickname'] ?? '')) ?: (trim((string)($lu['username'] ?? '')) ?: '用户');
                    ?>
                    <a href="<?= url('user/profile', ['id' => $lu['id']]) ?>" class="site-stats-latest-item" title="<?= e($luName) ?>">
                        <?php if (!empty($lu['avatar'])): ?>
                        <img src="<?= upload_url($lu['avatar']) ?>" alt="">
                        <?php else: ?>
                        <span class="site-stats-latest-avatar-text"><?= e(mb_substr($luName, 0, 1, 'UTF-8')) ?></span>
                        <?php endif; ?>
                        <span class="site-stats-latest-name"><?= e($luName) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </aside>
    <?php endif; ?>
</div>

<?php
// 仅首页（HomeController::$hideFooter=false）渲染完整页脚，其他内页跳过整块 footer
if ($__hideFooter === false):
// 读取页脚配置（用户配置 + 默认值合并，缺失字段自动 fallback）
$footerData = null;
try { $footerData = get_footer_data(); } catch (Throwable $e) { $footerData = default_footer_data(); }
$footerCols   = $footerData['columns'] ?? [];
$footerSocials= $footerData['socials'] ?? [];
?>
<footer class="site-footer">
    <div class="site-footer-main">
        <!-- 左：站名 / logo / 简介 / 社交媒体。
             站名留空时**不** fallback 到 $site['title']——只剩 logo 时就只显示 logo；
             站名 / logo / 简介 / 社交媒体全部空时整段不渲染（让右侧菜单栏占满主区，避免出现看不见的 420px 占位）。 -->
<?php
$__brandHasLogo   = !empty($footerData['site_logo']);
$__brandHasName   = $footerData['site_name']  !== '';
$__brandHasIntro  = $footerData['site_intro'] !== '';
$__brandHasSocial = !empty($footerSocials);
$__brandHasAny    = $__brandHasLogo || $__brandHasName || $__brandHasIntro || $__brandHasSocial;
?>
<?php if ($__brandHasAny): ?>
        <div class="site-footer-brand">
            <?php if ($__brandHasLogo || $__brandHasName): ?>
            <div class="site-footer-logo">
                <?php if ($__brandHasLogo): ?>
                <img src="<?= upload_url($footerData['site_logo']) ?>" alt="<?= e($__brandHasName ? $footerData['site_name'] : 'site-logo') ?>">
                <?php endif; ?>
                <?php if ($__brandHasName): ?>
                <span class="site-footer-name"><?= e($footerData['site_name']) ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($__brandHasIntro): ?>
            <p class="site-footer-intro"><?= e($footerData['site_intro']) ?></p>
            <?php endif; ?>

            <?php if ($__brandHasSocial): ?>
            <div class="site-footer-socials">
                <?php foreach ($footerSocials as $s):
                    $icon  = $s['icon'] ?? 'fa-link';
                    $type  = $s['type'] ?? 'link';
                    $value = $s['value'] ?? '';
                    $title = $s['title'] ?? $icon;
                ?>
                <div class="site-footer-social">
                    <?php if ($type === 'qrcode'): ?>
                    <span class="sf-icon" data-qr="<?= e(upload_url($value)) ?>" title="<?= e($title) ?>">
                        <i class="<?= e(fa_icon_class($icon)) ?>" aria-hidden="true"></i>
                        <span class="sf-qr-pop"><img src="<?= e(upload_url($value)) ?>" alt="<?= e($title) ?>"></span>
                    </span>
                    <?php else: ?>
                    <a class="sf-icon" href="<?= e($value) ?>" target="_blank" rel="noopener" title="<?= e($title) ?>">
                        <i class="<?= e(fa_icon_class($icon)) ?>" aria-hidden="true"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
<?php endif; ?>

        <!-- 右：菜单 4 栏 -->
        <div class="site-footer-cols">
            <?php foreach ($footerCols as $col): ?>
            <div class="site-footer-col">
                <h4 class="sf-col-title"><?= e($col['title'] ?? '') ?></h4>
                <ul>
                    <?php foreach (($col['links'] ?? []) as $lk):
                        $name = trim((string)($lk['name'] ?? ''));
                        $url  = trim((string)($lk['url'] ?? ''));
                        if ($name === '') continue;
                        // 站内链接检测：不含 http/https/javascript/ // 开头，且不含 : ，视为站内
                        $isInternal = !preg_match('#^(https?:|javascript:|//)#i', $url) && strpos($url, ':') === false;
                        $fullUrl = $isInternal && $url !== '' && $url !== '#' ? url(ltrim($url, '/')) : $url;
                    ?>
                    <li><a href="<?= e($fullUrl ?: '#') ?>"<?= !$isInternal ? ' target="_blank" rel="noopener"' : '' ?>><?= e($name) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 底：版权 / 备案 / 联系方式（居中） -->
    <div class="site-footer-bottom">
        <div class="sf-bottom-line"><?= e($footerData['copyright']) ?></div>
        <?php if (!empty($footerData['icp'])): ?>
        <div class="sf-bottom-line">
            <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener"><?= e($footerData['icp']) ?></a>
        </div>
        <?php endif; ?>
        <?php if (!empty($footerData['police_record'])): ?>
        <div class="sf-bottom-line">
            <a href="https://beian.mps.gov.cn/" target="_blank" rel="noopener"><?= e($footerData['police_record']) ?></a>
        </div>
        <?php endif; ?>
        <?php if (!empty($footerData['contact_extra'])): ?>
        <div class="sf-bottom-line"><?= e($footerData['contact_extra']) ?></div>
        <?php endif; ?>
    </div>
</footer>
<?php endif; /* end 首页渲染 footer */ ?>

<!-- Font Awesome 已通过 head 加载；此处不再重复 -->

<!-- 回到顶部按钮（首页、板块、帖内、个人主页等所有前端页面通用） -->
<a href="javascript:void(0)" id="backToTop" class="back-to-top" aria-label="回到顶部" title="回到顶部">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
</a>

<div class="modal-overlay" id="confirmModal">
    <div class="modal">
        <div class="modal-header"><span id="modalTitle">确认操作</span><button class="modal-close" onclick="closeModal()">&times;</button></div>
        <div id="modalBody" style="font-size:14px;color:#555;"></div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal()">取消</button>
            <button class="btn btn-primary" id="modalConfirmBtn">确定</button>
        </div>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>?v=<?= filemtime(PUBLIC_PATH . '/js/app.js') ?>"></script>
<!-- 表情选择器：全局扫描 [data-emoji-trigger] 自动绑定（发布/评论/私信三处共用） -->
<script src="<?= asset('js/emoji-picker.js') ?>?v=<?= filemtime(PUBLIC_PATH . '/js/emoji-picker.js') ?>"></script>
<!-- hover 用户名片：全局扫描 a[data-uid]，滑过弹出用户名片 -->
<script src="<?= asset('js/usercard.js') ?>?v=<?= filemtime(PUBLIC_PATH . '/js/usercard.js') ?>"></script>
<script>
function toggleAvatarMenu(e) {
    e.stopPropagation();
    document.getElementById('avatarDropdown').classList.toggle('active');
}
document.addEventListener('click', function(e) {
    var wrap = document.getElementById('avatarMenuWrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('avatarDropdown').classList.remove('active');
    }
});
<?php if (is_logged_in()): ?>
// 消息角标轮询（每60秒）
setInterval(function() {
    ajax({ method: 'GET', url: '<?= url("message/unreadCount") ?>', success: function(res) {
        if (res.code === 0) {
            var n = res.data.count;
            var badge = document.getElementById('navMsgBadge');
            if (badge) badge.innerHTML = n > 0 ? '<span style="background:#ea6f5a;color:#fff;font-size:10px;padding:1px 5px;border-radius:8px;margin-left:2px;">' + n + '</span>' : '';
        }
    }});
}, 60000);
<?php endif; ?>

// ===== 移动端导航抽屉切换 =====
(function() {
    var btn = document.getElementById('navToggle');
    var drawer = document.getElementById('mobileNavDrawer');
    var mask = document.getElementById('mobileNavMask');
    var close = document.getElementById('mobileNavClose');
    if (!btn || !drawer) return;
    function openDrawer() { drawer.classList.add('open'); document.body.style.overflow = 'hidden'; }
    function closeDrawer() { drawer.classList.remove('open'); document.body.style.overflow = ''; }
    btn.addEventListener('click', function(e) { e.stopPropagation(); openDrawer(); });
    if (mask) mask.addEventListener('click', closeDrawer);
    if (close) close.addEventListener('click', closeDrawer);
})();

// ===== 回到顶部按钮 =====
(function() {
    var btn = document.getElementById('backToTop');
    if (!btn) return;
    var threshold = 300; // 滚动超过 300px 才显示
    function onScroll() {
        if (window.pageYOffset > threshold) btn.classList.add('show');
        else btn.classList.remove('show');
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();
</script>
<?php
// 兼容两种写法：$extraJs（数组）/ $js（字符串，模板里用得更顺手）
$_extraJs = [];
if (isset($extraJs)) $_extraJs = array_merge($_extraJs, (array)$extraJs);
if (isset($js) && $js !== '') $_extraJs[] = $js;
$_extraJs = array_values(array_unique($_extraJs));
?>
<?php foreach ($_extraJs as $_js): ?>
<script src="<?= asset($_js) ?>"></script>
<?php endforeach; ?>
</body>
</html>
