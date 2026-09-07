<?php /** 403 页面 */ $title = '无访问权限'; $hideSidebar = true; ?>
<div class="card">
    <div class="card-body text-center" style="padding:60px 20px;">
        <h1 style="font-size:64px;color:#ea6f5a;margin-bottom:16px;">403</h1>
        <p style="color:#888;font-size:16px;margin-bottom:24px;"><?= e($message ?? '抱歉，您没有权限访问该内容') ?></p>
        <a href="<?= url('home/index') ?>" class="btn btn-primary">返回首页</a>
    </div>
</div>
