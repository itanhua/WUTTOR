<?php /** 帖子管理 */ $title = '帖子管理'; ?>
<div class="card" style="margin-bottom:16px;">
    <div class="card-body">
        <form method="get" action="<?= url('admin/posts') ?>" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="r" value="admin/posts">
            <input type="text" name="q" class="form-control" placeholder="搜索标题" value="<?= e($keyword) ?>" style="width:200px;">
            <select name="cat" class="form-control" style="width:150px;">
                <option value="0">全部板块</option>
                <?php foreach ($cats as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $cat == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">搜索</button>
            <span class="text-muted" style="font-size:13px;">共 <?= $pagination['total'] ?> 篇</span>
            <?php if (is_high_admin()): ?>
            <button type="button" class="btn btn-danger" onclick="batchDelete()" style="margin-left:auto;">批量删除</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <?php if (can('post.delete_section')): ?><th width="30"><input type="checkbox" id="selectAll"></th><?php endif; ?>
                <th>ID</th><th>标题</th><th>作者</th><th>板块</th><th>状态</th><th>浏览/评论/赞</th><th>发布时间</th><th>操作</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($posts as $p): ?>
        <tr>
            <?php if (can('post.delete_section')): ?><td><input type="checkbox" class="post-checkbox" value="<?= $p['id'] ?>"></td><?php endif; ?>
            <td><?= $p['id'] ?></td>
            <td>
                <?php if ($p['is_pinned']): ?><span class="badge badge-pin">置顶</span><?php endif; ?>
                <?php if ($p['is_essence']): ?><span class="badge badge-essence">精</span><?php endif; ?>
                <a href="<?= url('post/show', ['id' => $p['id']]) ?>" target="_blank"><?= e(truncate($p['title'], 30)) ?></a>
            </td>
            <td><?= e($p['author']['nickname'] ?? $p['author']['username'] ?? '-') ?></td>
            <td><?= e($p['category']['name'] ?? '-') ?></td>
            <td>
                <?= $p['status'] ? '<span class="status-tag success">正常</span>' : '<span class="status-tag danger">已删</span>' ?>
                <?php if (!empty($p['is_closed'])): ?><span class="status-tag default">已关闭</span><?php endif; ?>
            </td>
            <td><?= $p['view_count'] ?>/<?= $p['comment_count'] ?>/<?= $p['like_count'] ?></td>
            <td><?= $p['created_at'] ?></td>
            <td class="table-actions">
                <a href="<?= url('post/show', ['id' => $p['id']]) ?>" target="_blank">查看</a>
                <?php if (can('post.delete_section')): ?>
                <?php if (!empty($p['is_closed'])): ?>
                <a href="javascript:adminAction('开启帖子','确定重新开启该帖子？','<?= url('post/openPost', ['id' => $p['id']]) ?>')">开启</a>
                <?php else: ?>
                <a href="javascript:adminAction('关闭帖子','关闭后该帖子将不能回复，确定关闭？','<?= url('post/closePost', ['id' => $p['id']]) ?>')">关闭</a>
                <?php endif; ?>
                <a href="javascript:adminAction('删除帖子','确定删除该帖子？','<?= url('post/delete', ['id' => $p['id']]) ?>')">删除</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if ($pagination['last_page'] > 1): ?>
<?= pagination($pagination['total'], $page, 20, 'admin/posts', ['q' => $keyword, 'cat' => $cat]) ?>
<?php endif; ?>
<script>
var selectAllPosts = document.getElementById('selectAll');
if (selectAllPosts) selectAllPosts.addEventListener('change', function() {
    document.querySelectorAll('.post-checkbox').forEach(function(cb) { cb.checked = this.checked; }.bind(this));
});
function batchDelete() {
    var ids = [];
    document.querySelectorAll('.post-checkbox:checked').forEach(function(cb) { ids.push(cb.value); });
    if (ids.length === 0) { toast('请先选择帖子', 'warning'); return; }
    confirmModal('批量删除', '确定删除选中的 ' + ids.length + ' 篇帖子？', function() {
        postJSON(url('admin/batchDeletePosts'), {ids: ids, _token: getCsrfToken()}, function(res) {
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
