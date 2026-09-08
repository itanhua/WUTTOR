<?php /** 404 页面 */ $title = '页面不存在'; $hideSidebar = true; ?>
<div class="card">
    <div class="card-body text-center" style="padding:60px 20px;">
        <h1 style="font-size:64px;color:#ea6f5a;margin-bottom:16px;">404</h1>
        <p style="color:#888;font-size:16px;margin-bottom:24px;">抱歉，您访问的页面不存在或已被删除</p>
        <a href="<?= url('home/index') ?>" class="btn btn-primary">返回首页</a>
    </div>
</div>
