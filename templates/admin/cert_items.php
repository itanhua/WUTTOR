<?php /** 认证项管理（含认证项目组） */ $title = '认证项设置'; ?>
<?php
// 选中 group 名（用于中部"添加认证项"标题与图标卡标题），需在卡片渲染前定义
$currentGroupName = '';
foreach ($groups as $g) if ((int)$g['id'] === (int)$currentGroupId) $currentGroupName = $g['name'];
?>
<!-- 认证项目切换菜单 -->
<div class="switch-tabs" style="margin-bottom:16px;">
    <?php foreach ($groups as $g):
        $isActive = (int)$g['id'] === (int)$currentGroupId; ?>
    <a href="<?= url('admin/certItems', ['group_id' => $g['id']]) ?>" class="<?= $isActive ? 'active' : '' ?>">
        <?= e($g['name']) ?>
        <span onclick="event.stopPropagation();event.preventDefault();renameGroup(<?= $g['id'] ?>,'<?= e(addslashes($g['name'])) ?>')" class="switch-tab-action" title="重命名">✎</span>
        <?php if ((int)$g['id'] !== 1): ?>
        <span onclick="event.stopPropagation();event.preventDefault();deleteGroup(<?= $g['id'] ?>,'<?= e(addslashes($g['name'])) ?>')" class="switch-tab-action del" title="删除该认证项目">✕</span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
    <span class="switch-tabs-spacer"></span>
    <span class="switch-tabs-meta">共 <?= count($groups) ?> 个项目</span>
    <a href="javascript:addGroup()" class="switch-tabs-add">
        <span class="plus">+</span>
        添加认证项目
    </a>
</div>

<!-- 当前认证项目独立图标：每组一个，区别于全局 -->
<div class="card" style="margin-bottom:16px;">
    <div class="card-header">「<?= e($currentGroupName ?: '当前认证项目') ?>」认证通过后图标（SVG 矢量图）</div>
    <div class="card-body">
        <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap;">
            <div style="width:64px;height:64px;border-radius:50%;background:#f5f5f5;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                <div id="badgePreview" style="width:48px;height:48px;display:flex;align-items:center;justify-content:center;color:#999;font-size:12px;"><?= $badge ? '' : '未设置' ?><?= $badge ?></div>
            </div>
            <div style="flex:1;min-width:300px;">
                <form id="badgeForm" enctype="multipart/form-data" onsubmit="return false;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="group_id" value="<?= (int)$currentGroupId ?>">
                    <div class="form-group">
                        <label class="form-label">上传 SVG 文件（将显示在「<?= e($currentGroupName ?: '当前认证项目') ?>」已认证用户头像右下角）</label>
                        <input type="file" name="cert_badge_svg" id="certBadgeFile" accept=".svg,image/svg+xml" class="form-control" style="padding:6px;">
                        <p class="form-hint">建议 24×24 viewBox 的 SVG，圆形背景效果最佳；文件不超过 50KB</p>
                    </div>
                    <button type="submit" class="btn btn-primary">保存图标</button>
                    <button type="button" class="btn btn-default" id="badgeRestoreBtn" style="margin-left:8px;">恢复默认图标</button>
                    <?php if (!empty($badge)): ?>
                        <button type="button" class="btn btn-default" id="badgeRemoveBtn" style="margin-left:8px;">清除图标</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-header">向「<?= e($currentGroupName ?: '当前认证项目') ?>」添加认证项</div>
    <div class="card-body">
        <form id="addItemForm" onsubmit="return false;" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <?= csrf_field() ?>
            <input type="hidden" name="group_id" value="<?= (int)$currentGroupId ?>">
            <div class="form-group"><label class="form-label">字段名(英文)</label><input type="text" name="name" class="form-control" required placeholder="如 company"></div>
            <div class="form-group"><label class="form-label">标签</label><input type="text" name="label" class="form-control" required placeholder="如 工作单位"></div>
            <div class="form-group"><label class="form-label">类型</label>
                <select name="type" class="form-control">
                    <option value="text">单行文本</option>
                    <option value="textarea">多行文本</option>
                    <option value="image">图片上传</option>
                    <option value="select">下拉选择</option>
                </select>
            </div>
            <div class="form-group"><label class="form-label">选项(select用,每行一个)</label><input type="text" name="options" class="form-control" placeholder="留空"></div>
            <div class="form-group"><label class="form-label">必填</label>
                <select name="required" class="form-control"><option value="1">是</option><option value="0">否</option></select>
            </div>
            <div class="form-group"><label class="form-label">排序</label><input type="number" name="sort_order" class="form-control" value="0" style="width:80px;"></div>
            <button type="submit" class="btn btn-primary">添加</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">「<?= e($currentGroupName ?: '当前认证项目') ?>」认证项列表</div>
    <table class="data-table">
        <thead><tr><th>ID</th><th>字段名</th><th>标签</th><th>类型</th><th>必填</th><th>排序</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
        <tr data-id="<?= $it['id'] ?>">
            <td><?= $it['id'] ?></td>
            <td><strong><?= e($it['name']) ?></strong></td>
            <td><?= e($it['label']) ?></td>
            <td><?= e($it['type']) ?></td>
            <td><?= $it['required'] ? '<span class="status-tag warning">必填</span>' : '选填' ?></td>
            <td><?= $it['sort_order'] ?></td>
            <td><?= $it['status'] ? '<span class="status-tag success">启用</span>' : '<span class="status-tag default">禁用</span>' ?></td>
            <td class="table-actions">
                <a href="javascript:toggleItemStatus(<?= $it['id'] ?>, <?= $it['status'] ?>)"><?= $it['status'] ? '禁用' : '启用' ?></a>
                <a href="javascript:moveItem(<?= $it['id'] ?>, 'up')">上移</a>
                <a href="javascript:moveItem(<?= $it['id'] ?>, 'down')">下移</a>
                <a href="javascript:deleteItem(<?= $it['id'] ?>)" style="color:#f5222d;">删除</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($items)): ?>
        <tr><td colspan="8" style="text-align:center;color:#999;padding:24px;">该项目暂无认证项，请在上方表单添加</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<script>
// 添加认证项目
function addGroup() {
    var name = prompt('请输入新认证项目的名称（如：工作认证、技能认证、商家认证）：');
    if (!name) return;
    name = name.trim();
    if (!name) { toast('名称不能为空', 'error'); return; }
    if (name.length > 50) { toast('名称不能超过 50 字', 'error'); return; }
    postJSON(url('admin/addCertGroup'), {name: name, _token: getCsrfToken()}, function(res) {
        if (res.code === 0) {
            toast(res.message, 'success');
            var newId = res.data && res.data.id ? res.data.id : 0;
            if (newId) location.href = url('admin/certItems', {group_id: newId});
            else location.reload();
        } else {
            toast(res.message, 'error');
        }
    });
}
// 重命名认证项目（任意组，含默认「实名认证」）
function renameGroup(id, name) {
    var newName = prompt('请输入新的认证项目名称：', name);
    if (!newName) return;
    newName = newName.trim();
    if (!newName) { toast('名称不能为空', 'error'); return; }
    if (newName.length > 50) { toast('名称不能超过 50 字', 'error'); return; }
    postJSON(url('admin/renameCertGroup', {id: id}), {name: newName, _token: getCsrfToken()}, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) location.reload();
    });
}
// 删除认证项目
function deleteGroup(id, name) {
    confirmModal('删除认证项目', '确定删除「' + name + '」？该操作将同时删除该项目下的所有认证项，且不可恢复。', function() {
        postJSON(url('admin/deleteCertGroup', {id: id}), {_token: getCsrfToken()}, function(res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) setTimeout(function() { location.href = url('admin/certItems'); }, 600);
        });
    });
}
document.getElementById('badgeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var fileInput = document.getElementById('certBadgeFile');
    if (!fileInput.files || fileInput.files.length === 0) {
        toast('请先选择一个 SVG 文件', 'error');
        return;
    }
    window.ajax({
        method: 'POST',
        url: url('admin/saveCertGroupIcon'),
        data: new FormData(this),
        success: function(res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) setTimeout(function() { location.reload(); }, 800);
        },
        error: function(res) { toast(res && res.message ? res.message : '上传失败', 'error'); }
    });
});
// 选中文件即时预览
document.getElementById('certBadgeFile').addEventListener('change', function(e) {
    var f = e.target.files[0];
    var box = document.getElementById('badgePreview');
    if (!f) return;
    if (!/\.svg$/i.test(f.name) && f.type !== 'image/svg+xml') {
        toast('请选择 .svg 文件', 'error');
        e.target.value = '';
        return;
    }
    if (f.size > 51200) {
        toast('SVG 文件不能超过 50KB', 'error');
        e.target.value = '';
        return;
    }
    var reader = new FileReader();
    reader.onload = function(ev) {
        box.innerHTML = ev.target.result;
    };
    reader.readAsText(f);
});
// 清除当前项目组图标
var rm = document.getElementById('badgeRemoveBtn');
if (rm) rm.addEventListener('click', function() {
    confirmModal('清除图标', '确定删除「<?= e($currentGroupName) ?>」当前认证图标？保存后该组已认证用户将不再显示该标识。', function() {
        var fd = new FormData();
        fd.append('group_id', '<?= (int)$currentGroupId ?>');
        fd.append('cert_badge_svg_remove', '1');
        window.ajax({
            method: 'POST',
            url: url('admin/saveCertGroupIcon'),
            data: fd,
            success: function(res) {
                toast(res.message, res.code === 0 ? 'success' : 'error');
                if (res.code === 0) setTimeout(function() { location.reload(); }, 600);
            }
        });
    });
});
// 恢复默认图标（永远恢复到 AdminController::DEFAULT_CERT_BADGE_SVG：品牌红圆底+白✓ 24×24）
var rb = document.getElementById('badgeRestoreBtn');
if (rb) rb.addEventListener('click', function() {
    confirmModal('恢复默认图标', '确定将「<?= e($currentGroupName) ?>」图标恢复为系统默认（品牌红圆底+白✓ 24×24）？无论中途修改过多少次，都将回到此默认样式。', function() {
        var fd = new FormData();
        fd.append('group_id', '<?= (int)$currentGroupId ?>');
        fd.append('_token', document.querySelector('#badgeForm input[name="_token"]').value);
        window.ajax({
            method: 'POST',
            url: url('admin/restoreCertGroupIcon'),
            data: fd,
            success: function(res) {
                toast(res.message, res.code === 0 ? 'success' : 'error');
                if (res.code === 0) setTimeout(function() { location.reload(); }, 600);
            },
            error: function(res) { toast(res && res.message ? res.message : '操作失败', 'error'); }
        });
    });
});
document.getElementById('addItemForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData(this); var data = {};
    fd.forEach(function(v, k) { data[k] = v; });
    postJSON(url('admin/addCertItem'), data, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) setTimeout(function() { location.reload(); }, 800);
    });
});
function toggleItemStatus(id, cur) {
    postJSON(url('admin/updateCertItem', {id: id}), {status: cur ? 0 : 1, _token: getCsrfToken()}, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) setTimeout(function() { location.reload(); }, 600);
    });
}
function moveItem(id, dir) {
    var row = document.querySelector('tr[data-id="' + id + '"]');
    var target = dir === 'up' ? row.previousElementSibling : row.nextElementSibling;
    if (!target) return;
    var curOrder = parseInt(row.children[5].textContent);
    var targetOrder = parseInt(target.children[5].textContent);
    postJSON(url('admin/updateCertItem', {id: id}), {sort_order: targetOrder, _token: getCsrfToken()}, function() {
        postJSON(url('admin/updateCertItem', {id: target.dataset.id}), {sort_order: curOrder, _token: getCsrfToken()}, function(res) {
            if (res.code === 0) location.reload();
        });
    });
}
function deleteItem(id) {
    confirmModal('删除认证项', '确定删除该认证项？已提交的认证申请不受影响。', function() {
        postJSON(url('admin/deleteCertItem', {id: id}), {_token: getCsrfToken()}, function(res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) setTimeout(function() { location.reload(); }, 600);
        });
    });
}
</script>
