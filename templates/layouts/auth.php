<?php /** 认证页布局（简洁，居中卡片） */ ?>
<?php $site = Config::get('site', []); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($title ?? '') ? $title . ' - ' : '') . ($site['title'] ?? '论坛') ?></title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>?v=<?= filemtime(PUBLIC_PATH . '/css/style.css') ?>">
</head>
<body>
<nav class="navbar">
    <div class="navbar-inner">
        <a href="<?= url('home/index') ?>" class="navbar-brand"><?= e($site['title'] ?? '论坛') ?></a>
        <div class="navbar-nav">
            <a href="<?= url('home/index') ?>">返回首页</a>
        </div>
    </div>
</nav>
<div style="min-height:calc(100vh - 56px - 80px);">
    <?= $content ?? '' ?>
</div>
<div class="footer">
    <p>&copy; <?= date('Y') ?> <?= e($site['title'] ?? '论坛') ?> · <?= e($site['subtitle'] ?? '') ?></p>
</div>
<script src="<?= asset('js/app.js') ?>?v=<?= filemtime(PUBLIC_PATH . '/js/app.js') ?>"></script>
</body>
</html>
