<?php /** 表情管理 */ $title = '表情管理'; ?>
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <span>表情包管理</span>
        <a href="<?= url('admin/emojiPackEdit') ?>" class="btn btn-primary btn-sm">+ 新建表情包</a>
    </div>
    <div class="card-body">
        <p class="form-hint" style="margin-top:0;">
            前台表情选择器实时读取启用中的包，后台改动后前台刷新页面即生效。
        </p>
        <table class="table">
            <thead>
                <tr>
                    <th>名称</th>
                    <th>slug</th>
                    <th>类型</th>
                    <th>表情数</th>
                    <th>排序</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($packs as $p): ?>
                <tr>
                    <td><?= e($p['name']) ?><?= !empty($p['is_system']) ? ' <span class="badge" style="background:#eee;color:#999;">内置</span>' : '' ?></td>
                    <td><code><?= e($p['slug']) ?></code></td>
                    <td><?= $p['type'] === 'image' ? '图片(CDN)' : '字符(Unicode)' ?></td>
                    <td><?= (int)$p['enabled_item_count'] ?> / <?= (int)$p['item_count'] ?></td>
                    <td><?= (int)$p['sort_order'] ?></td>
                    <td><?= $p['enabled'] ? '<span style="color:#52c41a;font-weight:600;">启用</span>' : '<span style="color:#999;">停用</span>' ?></td>
                    <td style="white-space:nowrap;">
                        <a href="<?= url('admin/emojiPackEdit', ['id' => $p['id']]) ?>" class="btn btn-ghost btn-sm">管理</a>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="togglePack(<?= (int)$p['id'] ?>, <?= (int)$p['enabled'] ?>)"><?= $p['enabled'] ? '停用' : '启用' ?></button>
                        <?php if (empty($p['is_system'])): ?>
                        <button type="button" class="btn btn-ghost btn-sm" style="color:#f5222d;" onclick="delPack(<?= (int)$p['id'] ?>)">删除</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function togglePack(id, cur) {
    postJSON(url('admin/emojiPackToggle'), { id: id, _token: getCsrfToken() }, function (res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) setTimeout(function () { location.reload(); }, 600);
    }, function (res) { toast(res.message || '操作失败', 'error'); });
}
function delPack(id) {
    confirmModal('删除表情包', '确定删除该表情包及其下所有表情？此操作不可恢复。', function () {
        postJSON(url('admin/emojiPackDelete'), { id: id, _token: getCsrfToken() }, function (res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) setTimeout(function () { location.reload(); }, 600);
        });
    });
}
</script>
