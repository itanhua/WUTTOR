<?php /** 举报管理 */ $title = '举报处理'; ?>
<div class="card" style="margin-bottom:16px;">
    <div class="tabs" style="border-radius:6px;">
        <a href="<?= url('admin/reports') ?>" class="<?= $status === '' ? 'active' : '' ?>">全部</a>
        <a href="<?= url('admin/reports', ['status' => '0']) ?>" class="<?= $status === '0' ? 'active' : '' ?>">待处理</a>
        <a href="<?= url('admin/reports', ['status' => '1']) ?>" class="<?= $status === '1' ? 'active' : '' ?>">已处理</a>
        <a href="<?= url('admin/reports', ['status' => '2']) ?>" class="<?= $status === '2' ? 'active' : '' ?>">已驳回</a>
        <span style="flex:1;"></span>
        <span class="text-muted" style="font-size:13px;align-self:center;">共 <?= $total ?> 条</span>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr><th>ID</th><th>举报人</th><th>类型</th><th>目标ID</th><th>原因</th><th>状态</th><th>时间</th><th>处理人</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach ($reports as $r): ?>
        <tr>
            <td><?= $r['id'] ?></td>
            <td><?= e($r['reporter_name'] ?? '-') ?></td>
            <td><?= $r['target_type'] === 'post' ? '帖子' : '评论' ?></td>
            <td>
                <?php if (!empty($r['view_url'])): ?>
                <a href="<?= $r['view_url'] ?><?= $r['anchor'] ? '#' . $r['anchor'] : '' ?>" target="_blank" style="color:#ea6f5a;font-weight:600;">查看 <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                <?php else: ?>
                <span class="text-muted">内容已删除</span>
                <?php endif; ?>
            </td>
            <td style="max-width:250px;"><?= e($r['reason']) ?></td>
            <td>
                <?php $sm = [0=>['待处理','warning'],1=>['已处理','success'],2=>['已驳回','default']]; $s = $sm[$r['status']]; ?>
                <span class="status-tag <?= $s[1] ?>"><?= $s[0] ?></span>
            </td>
            <td><?= $r['created_at'] ?></td>
            <td><?= e($r['handler_name'] ?? '-') ?></td>
            <td class="table-actions">
                <?php if ($r['status'] == 0): ?>
                <a href="javascript:handleReport(<?= $r['id'] ?>, 'resolved')">处理并删除</a>
                <a href="javascript:handleReport(<?= $r['id'] ?>, 'dismissed')">驳回举报</a>
                <?php else: ?>
                <span class="text-muted"><?= e(truncate($r['handle_result'] ?? '', 15)) ?: '-' ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
function handleReport(id, action) {
    var result = prompt(action === 'resolved' ? '处理说明（可留空，将删除被举报内容）：' : '驳回说明：', action === 'resolved' ? '违规内容已删除' : '举报不成立');
    if (result === null) return;
    postJSON(url('admin/handleReport', {id: id}), {action: action, handle_result: result, _token: getCsrfToken()}, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) setTimeout(function() { location.reload(); }, 800);
    });
}
</script>
