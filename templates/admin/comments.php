<?php /** 评论管理 */ $title = '评论管理'; ?>
<div class="card">
    <div class="card-header">
        评论列表（共 <?= $total ?> 条）
        <?php if (can('comment.delete_section')): ?>
        <button class="btn btn-danger btn-sm" onclick="batchDeleteComments()">批量删除</button>
        <?php endif; ?>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <?php if (can('comment.delete_section')): ?><th width="30"><input type="checkbox" id="selectAll"></th><?php endif; ?>
                <th>ID</th><th>内容</th><th>评论者</th><th>所属帖子</th><th>状态</th><th>时间</th><th>操作</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($comments as $c): ?>
        <tr>
            <?php if (can('comment.delete_section')): ?><td><input type="checkbox" class="comment-checkbox" value="<?= $c['id'] ?>"></td><?php endif; ?>
            <td><?= $c['id'] ?></td>
            <td style="max-width:300px;"><?= e(truncate($c['content'], 50)) ?></td>
            <td><?= e($c['nickname'] ?? '-') ?></td>
            <td><a href="<?= url('post/show', ['id' => $c['post_id']]) ?>" target="_blank"><?= e(truncate($c['post_title'] ?? '', 20)) ?></a></td>
            <td><?= $c['is_hidden'] ? '<span class="status-tag warning">屏蔽</span>' : '<span class="status-tag success">显示</span>' ?></td>
            <td><?= $c['created_at'] ?></td>
            <td class="table-actions">
                <?php if (can('comment.delete_section')): ?>
                <a href="javascript:adminAction('删除评论','确定删除？','<?= url('post/deleteComment', ['id' => $c['id']]) ?>')" style="color:#f5222d;">删除</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
var selectAllComments = document.getElementById('selectAll');
if (selectAllComments) selectAllComments.addEventListener('change', function() {
    document.querySelectorAll('.comment-checkbox').forEach(function(cb) { cb.checked = this.checked; }.bind(this));
});
function batchDeleteComments() {
    var ids = [];
    document.querySelectorAll('.comment-checkbox:checked').forEach(function(cb) { ids.push(cb.value); });
    if (!ids.length) { toast('请先选择', 'warning'); return; }
    confirmModal('批量删除', '确定删除选中的 ' + ids.length + ' 条评论？', function() {
        postJSON(url('admin/batchDeleteComments'), {ids: ids, _token: getCsrfToken()}, function(res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) setTimeout(function() { location.reload(); }, 800);
        });
    });
}
function adminAction(title, body, urlStr) {
    confirmModal(title, body, function() {
        postJSON(urlStr, {_token: getCsrfToken()}, function(res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) setTimeout(function() { location.reload(); }, 800);
        });
    });
}
</script>
