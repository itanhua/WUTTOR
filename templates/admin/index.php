<?php /** 后台仪表盘 */ $title = '仪表盘'; ?>
<h2 style="font-size:20px;margin-bottom:20px;">数据概览</h2>
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">总用户数</div>
        <div class="stat-value"><?= $stats['users'] ?></div>
        <div class="stat-trend">今日新增 <?= $stats['today_users'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">帖子总数</div>
        <div class="stat-value"><?= $stats['posts'] ?></div>
        <div class="stat-trend">今日新增 <?= $stats['today_posts'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">评论总数</div>
        <div class="stat-value"><?= $stats['comments'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">已认证用户</div>
        <div class="stat-value"><?= $stats['certified_users'] ?></div>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">待审核认证</div>
        <div class="stat-value" style="color:<?= $stats['pending_certs'] > 0 ? '#fa8c16' : '#2f2f2f' ?>"><?= $stats['pending_certs'] ?></div>
        <?php if ($stats['pending_certs'] > 0): ?>
        <div><a href="<?= url('admin/certifications', ['status' => '0']) ?>" style="font-size:12px;">立即处理</a></div>
        <?php endif; ?>
    </div>
    <div class="stat-card">
        <div class="stat-label">待处理举报</div>
        <div class="stat-value" style="color:<?= $stats['pending_reports'] > 0 ? '#f5222d' : '#2f2f2f' ?>"><?= $stats['pending_reports'] ?></div>
        <?php if ($stats['pending_reports'] > 0): ?>
        <div><a href="<?= url('admin/reports', ['status' => '0']) ?>" style="font-size:12px;">立即处理</a></div>
        <?php endif; ?>
    </div>
    <div class="stat-card">
        <div class="stat-label">总认证申请</div>
        <div class="stat-value"><?= Model::scalar('SELECT COUNT(*) FROM certifications') ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">板块数量</div>
        <div class="stat-value"><?= Model::scalar('SELECT COUNT(*) FROM categories') ?></div>
    </div>
</div>

<div class="flex-cards">
    <div class="card">
        <div class="card-header">近7天注册趋势</div>
        <div class="card-body">
            <?php if (empty($trend)): ?>
            <p class="text-muted">暂无数据</p>
            <?php else: ?>
            <?php
            $maxC = max(array_column($trend, 'c')) ?: 1;
            foreach ($trend as $t):
            ?>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                <span style="width:80px;font-size:12px;color:#999;"><?= substr($t['d'], 5) ?></span>
                <div style="flex:1;background:#f5f5f5;border-radius:4px;height:20px;overflow:hidden;">
                    <div style="width:<?= ($t['c'] / $maxC * 100) ?>%;height:100%;background:#ea6f5a;border-radius:4px;"></div>
                </div>
                <span style="width:30px;font-size:12px;color:#666;"><?= $t['c'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header">热门帖子 TOP 10</div>
        <table class="data-table">
            <thead><tr><th>标题</th><th>浏览</th><th>评论</th></tr></thead>
            <tbody>
            <?php foreach ($hotPosts as $i => $p): ?>
            <tr>
                <td><a href="<?= url('post/show', ['id' => $p['id']]) ?>" target="_blank"><?= e(truncate($p['title'], 30)) ?></a></td>
                <td><?= $p['view_count'] ?></td>
                <td><?= $p['comment_count'] ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
