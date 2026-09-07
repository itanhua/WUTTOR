<?php /** 后台布局 */ ?>
<?php $site = Config::get('site', []); $currentUser = Auth::user(); $currentRoute = $_GET['r'] ?? 'admin/index'; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? '管理后台') . ' - ' . ($site['title'] ?? '论坛')) ?></title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>?v=<?= filemtime(PUBLIC_PATH . '/css/style.css') ?>">
    <!-- Font Awesome 6 free（含 solid/regular/brands，统一样式前缀 fa-solid/fa-regular/fa-brands） -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/all.min.css" crossorigin="anonymous">
    <style>
        /* 后台图标统一基线：保证 <i class="fa-solid ..."> 正常显示并垂直对齐 */
        .admin-content i,
        .admin-sidebar i,
        .navbar-nav i {
            display: inline-block; width: 1.25em; text-align: center; line-height: 1;
            /* FA6 字体默认不是 solid 系列时切到 solid 字体 */
            font-style: normal;
        }
        /* 导航设置：图标实时预览 */
        .nav-icon-preview {
            display: inline-flex; align-items: center; justify-content: center;
            width: 38px; min-width: 38px; height: 34px;
            border: 1px solid #e0e0e0; border-radius: 6px; background: #fafafa;
            font-size: 16px; color: #555;
        }
        .nav-icon-preview i { width: auto; font-size: 16px; }
        .nav-row-grid { display: grid; grid-template-columns: 1fr 38px 1.4fr 1.6fr auto; gap: 8px; align-items: center; margin-bottom: 8px; }
        /* FA 图标库（独立菜单页） */
        .fa-icon-toolbar {
            position: sticky; top: 0; z-index: 5;
            background: #fff; padding: 12px 16px; border: 1px solid #eee; border-radius: 8px;
            margin-bottom: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .fa-icon-search { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .fa-icon-search:focus { outline: none; border-color: #ea6f5a; box-shadow: 0 0 0 3px rgba(234,111,90,0.15); }
        .fa-cat-bar { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
        .fa-cat-bar a {
            padding: 4px 10px; border-radius: 14px; background: #f4f4f4; color: #555;
            text-decoration: none; font-size: 12px; transition: all .15s ease;
        }
        .fa-cat-bar a:hover { background: #ea6f5a; color: #fff; }
        .fa-cat-bar a.active { background: #ea6f5a; color: #fff; }
        .fa-category-block { margin-bottom: 24px; scroll-margin-top: 90px; }
        .fa-category-title { font-size: 15px; font-weight: 600; color: #333; margin-bottom: 10px; padding-left: 8px; border-left: 3px solid #ea6f5a; }
        .fa-icon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 10px; }
        .fa-icon-cell {
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px;
            padding: 12px 6px; border: 1px solid #eee; border-radius: 8px; background: #fff;
            cursor: pointer; transition: all .15s ease; font: inherit; color: #444;
            text-align: center;
        }
        .fa-icon-cell:hover { border-color: #ea6f5a; background: #fff7f5; color: #ea6f5a; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(234,111,90,0.12); }
        .fa-icon-cell i { font-size: 22px; width: auto; }
        .fa-icon-name { font-size: 12px; color: #888; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: SFMono-Regular, Consolas, monospace; }
        .fa-icon-cell:hover .fa-icon-name { color: #ea6f5a; }
        .fa-no-result { padding: 40px; text-align: center; color: #999; font-size: 14px; }
        .fa-icon-count { color: #999; font-weight: normal; margin-left: 6px; font-size: 12px; }
    </style>
</head>
<body>
<?= csrf_field() ?>
<nav class="navbar">
    <div class="navbar-inner">
        <a href="<?= url('admin/index') ?>" class="navbar-brand" style="display:flex;align-items:center;gap:8px;">
            <?php if (!empty($site['logo'])): ?>
            <img src="<?= upload_url($site['logo']) ?>" style="height:28px;vertical-align:middle;" alt="">
            <?php else: ?>
            <?= e($site['title'] ?? '论坛') ?> 管理后台
            <?php endif; ?>
        </a>
        <div class="navbar-nav">
            <a href="<?= url('home/index') ?>">返回前台</a>
        </div>
        <div class="navbar-user">
            <div class="navbar-msg-wrap" id="navMsgWrap">
                <a href="javascript:;" class="navbar-icon-btn" id="navMsgBtn" aria-label="消息" onclick="toggleMsgDropdown(event)">
                    <i class="fa-solid fa-bell"></i>
                    <span id="navMsgBadge"></span>
                </a>
                <div class="navbar-msg-dropdown" id="navbarMsgDropdown">
                    <a href="<?= url('notification/index') ?>" class="navbar-msg-item" data-type="notif">
                        <i class="fa-solid fa-bell"></i>
                        <span>通知</span>
                        <span class="navbar-msg-item-badge" id="navNotifBadge" style="display:none;"></span>
                    </a>
                    <a href="<?= url('message/index') ?>" class="navbar-msg-item" data-type="pm">
                        <i class="fa-solid fa-comment-dots"></i>
                        <span>私信</span>
                        <span class="navbar-msg-item-badge" id="navPmBadge" style="display:none;"></span>
                    </a>
                </div>
            </div>
            <div class="avatar-menu-wrap" id="avatarMenuWrap">
                <a href="javascript:;" onclick="toggleAdminAvatarMenu(event)" class="navbar-avatar-link" aria-label="账号菜单">
                    <?php if (!empty($currentUser['avatar'])): ?>
                    <img src="<?= upload_url($currentUser['avatar']) ?>" alt="">
                    <?php else: ?>
                    <?= e(mb_substr($currentUser['nickname'] ?: $currentUser['username'], 0, 1)) ?>
                    <?php endif; ?>
                </a>
                <div class="avatar-dropdown" id="avatarDropdown">
                    <div class="admin-current-label">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                        <span><?= e($title ?? '管理后台') ?></span>
                    </div>
                    <a href="<?= url('user/profile', ['id' => $currentUser['id']]) ?>"><i class="fa-solid fa-user"></i> 我的主页</a>
                    <a href="<?= url('user/edit') ?>"><i class="fa-solid fa-gear"></i> 设置</a>
                    <div class="divider"></div>
                    <a href="<?= url('auth/logout') ?>" class="dropdown-logout"><i class="fa-solid fa-right-from-bracket"></i> 退出</a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- 后台管理菜单抽屉：从左侧滑入（替代原 sidebar 在所有设备上都展示） -->
<div class="admin-drawer-overlay" id="adminDrawerOverlay"></div>
<aside class="admin-drawer" id="adminDrawer">
    <div class="admin-drawer-header">
        <span><i class="fa-solid fa-bars"></i> 管理菜单</span>
        <button type="button" class="admin-drawer-close" id="adminDrawerClose" aria-label="关闭">&times;</button>
    </div>
    <div class="admin-drawer-body">
        <div class="admin-drawer-group">
            <h4>概览</h4>
            <a href="<?= url('admin/index') ?>" class="<?= $currentRoute === 'admin/index' ? 'active' : '' ?>"><i class="fa-solid fa-gauge"></i> 仪表盘</a>
        </div>

        <div class="admin-drawer-group">
            <h4>内容管理</h4>
            <a href="<?= url('admin/posts') ?>" class="<?= strpos($currentRoute, 'admin/posts') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-note-sticky"></i> 帖子管理</a>
            <a href="<?= url('admin/comments') ?>" class="<?= strpos($currentRoute, 'admin/comments') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-comments"></i> 评论管理</a>
            <?php if (is_webmaster()): ?>
            <a href="<?= url('admin/categories') ?>" class="<?= strpos($currentRoute, 'admin/categories') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-folder-tree"></i> 板块管理</a>
            <?php endif; ?>
            <a href="<?= url('admin/reports') ?>" class="<?= strpos($currentRoute, 'admin/reports') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-flag"></i> 举报处理</a>
            <a href="<?= url('admin/recycleBin') ?>" class="<?= strpos($currentRoute, 'admin/recycleBin') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-trash-can"></i> 回收站</a>
            <?php if (is_webmaster()): ?>
            <a href="<?= url('admin/emojiPacks') ?>" class="<?= (strpos($currentRoute, 'admin/emojiPacks') === 0 || strpos($currentRoute, 'admin/emojiPackEdit') === 0) ? 'active' : '' ?>"><i class="fa-solid fa-face-smile"></i> 表情管理</a>
            <?php endif; ?>
        </div>

        <div class="admin-drawer-group">
            <h4>用户管理</h4>
            <a href="<?= url('admin/users') ?>" class="<?= strpos($currentRoute, 'admin/users') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-users"></i> 用户列表</a>
            <a href="<?= url('admin/certifications') ?>" class="<?= strpos($currentRoute, 'admin/certifications') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-certificate"></i> 认证审核</a>
            <?php if (is_webmaster()): ?>
            <a href="<?= url('admin/certItems') ?>" class="<?= strpos($currentRoute, 'admin/certItems') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-id-card"></i> 认证项设置</a>
            <?php endif; ?>
        </div>

        <?php if (is_webmaster()): ?>
        <div class="admin-drawer-group">
            <h4>系统管理 <span style="color:#999;font-size:11px;font-weight:normal;">（仅站长）</span></h4>
            <a href="<?= url('admin/roles') ?>" class="<?= strpos($currentRoute, 'admin/roles') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-user-shield"></i> 角色权限</a>
            <a href="<?= url('admin/settings') ?>" class="<?= strpos($currentRoute, 'admin/settings') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-sliders"></i> 系统设置</a>
            <a href="<?= url('admin/icons') ?>" class="<?= strpos($currentRoute, 'admin/icons') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-icons"></i> 图标库</a>
            <a href="<?= url('admin/mailSettings') ?>" class="<?= strpos($currentRoute, 'admin/mailSettings') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-envelope"></i> 邮箱设置</a>
            <a href="<?= url('admin/navSettings') ?>" class="<?= strpos($currentRoute, 'admin/navSettings') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-compass"></i> 导航设置</a>
            <a href="<?= url('admin/footerSettings') ?>" class="<?= strpos($currentRoute, 'admin/footerSettings') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-shoe-prints"></i> 页脚设置</a>
            <a href="<?= url('admin/specialThemes') ?>" class="<?= strpos($currentRoute, 'admin/specialThemes') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-wand-magic-sparkles"></i> 特殊主题</a>
            <a href="<?= url('admin/permalinkSettings') ?>" class="<?= strpos($currentRoute, 'admin/permalinkSettings') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-link"></i> 固定连接</a>
            <a href="<?= url('admin/systemNotification') ?>" class="<?= strpos($currentRoute, 'admin/systemNotification') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-bell"></i> 系统通知</a>
            <a href="<?= url('admin/pointsSystem') ?>" class="<?= strpos($currentRoute, 'admin/pointsSystem') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-coins"></i> 积分系统</a>
        </div>
        <?php endif; ?>

        <div class="admin-drawer-group">
            <h4>安全管理</h4>
            <a href="<?= url('admin/sensitiveWords') ?>" class="<?= strpos($currentRoute, 'admin/sensitiveWords') === 0 ? 'active' : '' ?>"><i class="fa-solid fa-ban"></i> 敏感词库</a>
        </div>

        <div class="admin-drawer-group">
            <a href="<?= url('home/index') ?>" style="color:#ea6f5a;"><i class="fa-solid fa-house"></i> 返回前台</a>
        </div>
    </div>
</aside>

<nav class="admin-subnav">
    <button type="button" class="admin-drawer-toggle" id="adminDrawerToggle" aria-label="管理菜单">
        <i class="fa-solid fa-bars"></i>
        <span>管理菜单</span>
    </button>
    <span class="admin-current-title"><i class="fa-solid fa-location-dot"></i> <?= e($title ?? '管理后台') ?></span>
    <div class="admin-subnav-spacer"></div>
</nav>

<div class="admin-wrap">
    <main class="admin-content">
        <?php if ($flash = flash()): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <?= $content ?? '' ?>
    </main>
</div>

<!-- 后台回顶部按钮（与前端 .back-to-top 共用样式，scrollTo 走 window，无冲突） -->
<a id="adminBackToTop" class="back-to-top" href="javascript:void(0);" aria-label="返回顶部" title="返回顶部">
    <i class="fa-solid fa-arrow-up"></i>
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
<script>
/* ===== 后台管理菜单抽屉切换 + 头像下拉 + 回顶部按钮 ===== */
(function () {
    /* ----- 1) 后台管理菜单抽屉 ----- */
    var drawer       = document.getElementById('adminDrawer');
    var drawerToggle = document.getElementById('adminDrawerToggle');
    var drawerClose  = document.getElementById('adminDrawerClose');
    var drawerMask   = document.getElementById('adminDrawerOverlay');

    function openDrawer() {
        if (!drawer) return;
        drawer.classList.add('open');
        if (drawerMask) drawerMask.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeDrawer() {
        if (!drawer) return;
        drawer.classList.remove('open');
        if (drawerMask) drawerMask.classList.remove('open');
        document.body.style.overflow = '';
    }
    if (drawerToggle) drawerToggle.addEventListener('click', function (e) { e.preventDefault(); openDrawer(); });
    if (drawerClose)  drawerClose.addEventListener('click', closeDrawer);
    if (drawerMask)   drawerMask.addEventListener('click', closeDrawer);
    // ESC 关闭
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer && drawer.classList.contains('open')) closeDrawer();
    });

    /* ----- 2) 后台头像菜单 ----- */
    document.addEventListener('click', function (e) {
        var wrap = document.getElementById('avatarMenuWrap');
        var dd   = document.getElementById('avatarDropdown');
        if (!dd) return;
        if (wrap && !wrap.contains(e.target)) {
            dd.classList.remove('active');
        }
    });
    /* 消息 badge 同步（不阻塞旧页面，没有 badge 元素则跳过） */
    var navBadge = document.getElementById('navMsgBadge');
    if (navBadge) {
        try {
            var storageKey = 'admin_unread_count_v1';
            var cached = sessionStorage.getItem(storageKey);
            if (cached) navBadge.innerHTML = '<span class="navbar-msg-badge">' + cached + '</span>';
        } catch (err) {}
    }

    /* ----- 3) 后台回顶部按钮 ----- */
    var backBtn = document.getElementById('adminBackToTop');
    if (backBtn) {
        var showAfter = 300;
        var visible = false;
        var check = function () {
            var y = window.pageYOffset || document.documentElement.scrollTop;
            if (y > showAfter && !visible) { backBtn.classList.add('show'); visible = true; }
            else if (y <= showAfter && visible) { backBtn.classList.remove('show'); visible = false; }
        };
        check();
        window.addEventListener('scroll', check, { passive: true });
        backBtn.addEventListener('click', function (e) {
            e.preventDefault();
            try { window.scrollTo({ top: 0, behavior: 'smooth' }); }
            catch (err) { window.scrollTo(0, 0); }
        });
    }
})();

/* 后台布局内的头像菜单切换（与前台 toggleAvatarMenu 同名 id 不同页面，避免冲突） */
function toggleAdminAvatarMenu(e) {
    e.stopPropagation();
    var dd = document.getElementById('avatarDropdown');
    if (dd) dd.classList.toggle('active');
}

/* 后台 confirmModal 的 close/open（admin 内页大量用到 closeModal()，原代码缺失定义） */
function closeModal() {
    var m = document.getElementById('confirmModal');
    if (m) m.style.display = 'none';
}
function openModal(title, body, onConfirm) {
    var m = document.getElementById('confirmModal');
    if (!m) return;
    document.getElementById('modalTitle').textContent = title || '确认操作';
    document.getElementById('modalBody').textContent = body || '';
    m.style.display = 'flex';
    var btn = document.getElementById('modalConfirmBtn');
    if (btn) {
        btn.onclick = function () {
            if (typeof onConfirm === 'function') onConfirm();
            closeModal();
        };
    }
}
</script>
</body>
</html>
