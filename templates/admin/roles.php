<?php /** 角色权限管理 */ $title = '角色权限'; ?>
<div class="card" style="margin-bottom:16px;">
    <div class="card-header">新建角色</div>
    <div class="card-body">
        <form id="roleForm" onsubmit="return false;" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <?= csrf_field() ?>
            <div class="form-group"><label class="form-label">角色名称</label><input type="text" name="name" class="form-control" required></div>
            <div class="form-group"><label class="form-label">角色编码</label><input type="text" name="code" class="form-control" required placeholder="如 editor"></div>
            <div class="form-group" style="flex:1;min-width:200px;"><label class="form-label">描述</label><input type="text" name="description" class="form-control"></div>
            <button type="submit" class="btn btn-primary">创建</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <span>角色列表与权限分配</span>
        <div style="display:flex;align-items:center;gap:6px;font-size:13px;">
            <label for="roleSort" style="color:#666;font-weight:normal;margin:0;">排序：</label>
            <select id="roleSort" class="form-control" style="width:auto;display:inline-block;margin:0;padding:4px 8px;font-size:13px;" onchange="if(this.value){location.href='<?= url('admin/roles') ?>&sort='+this.value;}">
                <?php $sortOptions = ['level' => '默认（按权限等级）', 'name' => '按名称', 'code' => '按编码', 'created_desc' => '创建时间（新→旧）', 'created_asc' => '创建时间（旧→新）']; ?>
                <?php foreach ($sortOptions as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($sort ?? 'level') === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="card-body">
        <?php foreach ($roles as $role): $roleLocked = $role['code'] === 'webmaster'; ?>
        <div style="margin-bottom:24px;padding-bottom:20px;border-bottom:1px solid #f0f0f0;">
            <h3 style="font-size:15px;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;">
                <span>
                    <?= e($role['name']) ?> <span class="text-muted" style="font-size:12px;">(<?= e($role['code']) ?>)</span> - <?= e($role['description']) ?>
                    <?php if ($roleLocked): ?>
                    <span class="status-tag webmaster" style="margin-left:8px;">权限固定</span>
                    <?php endif; ?>
                </span>
                <span>
                    <button type="button" class="btn btn-sm" onclick='editRole(<?= htmlspecialchars(json_encode($role), ENT_QUOTES, "UTF-8") ?>)'>编辑</button>
                    <?php if (!in_array($role['code'], ['guest','user','certified_user','moderator','admin','super_admin','webmaster'])): ?>
                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteRole(<?= $role['id'] ?>)">删除</button>
                    <?php endif; ?>
                </span>
            </h3>
            <?php if ($roleLocked): ?>
            <p class="form-hint" style="margin-bottom:12px;color:#999;">站长权限为系统固定项：全部强制开启，前端不允许取消。后端会再次校验，绕过前端无效。</p>
            <?php endif; ?>
            <form class="permForm" data-role-id="<?= $role['id'] ?>" data-role-locked="<?= $roleLocked ? '1' : '0' ?>" onsubmit="return false;">
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <?php
                    // 「板块管理」「系统设置」已下沉为站长专属入口（侧边栏与 controller 入口均仅站长可见），
                    // 不再需要权限开关；前端不再展示这两项；后端 updateRolePermissions 写入时也按 code 过滤。
                    $hiddenPermCodes = ['category.manage', 'system.manage'];
                    foreach ($permissions as $perm):
                        if (in_array($perm['code'], $hiddenPermCodes, true)) continue;
                    ?>
                    <label style="display:flex;align-items:center;gap:4px;padding:4px 10px;background:#f5f5f5;border-radius:4px;font-size:13px;cursor:<?= $roleLocked ? 'not-allowed' : 'pointer' ?>;opacity:<?= $roleLocked ? '0.7' : '1' ?>;">
                        <input type="checkbox"
                               name="permission_ids[]"
                               value="<?= $perm['id'] ?>"
                               <?= ($roleLocked || in_array($perm['id'], $rolePerms[$role['id']] ?? [])) ? 'checked' : '' ?>
                               <?= $roleLocked ? 'disabled' : '' ?>>
                        <?= e($perm['name']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php if (!$roleLocked): ?>
                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:12px;">保存权限</button>
                <?php endif; ?>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal-overlay" id="editRoleModal">
    <div class="modal">
        <div class="modal-header"><span>编辑角色</span><button class="modal-close" onclick="document.getElementById('editRoleModal').classList.remove('active')">&times;</button></div>
        <form id="editRoleForm" onsubmit="return false;">
            <?= csrf_field() ?>
            <input type="hidden" name="id">
            <div class="form-group"><label class="form-label">角色名称</label><input type="text" name="name" class="form-control" required></div>
            <div class="form-group"><label class="form-label">角色编码</label><input type="text" name="code" class="form-control" required></div>
            <div class="form-group"><label class="form-label">描述</label><input type="text" name="description" class="form-control"></div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">保存</button></div>
        </form>
    </div>
</div>
<script>
function editRole(role) {
    var form = document.getElementById('editRoleForm');
    form.id.value = role.id;
    form.name.value = role.name;
    form.code.value = role.code;
    form.description.value = role.description || '';
    document.getElementById('editRoleModal').classList.add('active');
}
document.getElementById('editRoleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData(this); var data = {};
    fd.forEach(function(v, k) { data[k] = v; });
    postJSON(url('admin/updateRole', {id: data.id}), data, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) { document.getElementById('editRoleModal').classList.remove('active'); setTimeout(function() { location.reload(); }, 600); }
    });
});
function deleteRole(id) {
    confirmModal('删除角色', '确定删除该角色？关联的权限分配也会清除。', function() {
        postJSON(url('admin/deleteRole', {id: id}), {_token: getCsrfToken()}, function(res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) setTimeout(function() { location.reload(); }, 600);
        });
    });
}
document.getElementById('roleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData(this); var data = {};
    fd.forEach(function(v, k) { data[k] = v; });
    postJSON(url('admin/createRole'), data, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) setTimeout(function() { location.reload(); }, 800);
    });
});
document.querySelectorAll('.permForm').forEach(function(form) {
    if (form.dataset.roleLocked === '1') {
        // 站长权限固定：阻止任何提交尝试，后端会再次校验兜底
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            toast('站长权限为系统固定项，全部开启，不可修改', 'warning');
        });
        return;
    }
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var roleId = this.dataset.roleId;
        var ids = [];
        this.querySelectorAll('input[type=checkbox]:checked').forEach(function(cb) { ids.push(cb.value); });
        postJSON(url('admin/updateRolePermissions', {id: roleId}), {permission_ids: ids, _token: getCsrfToken()}, function(res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
        });
    });
});
</script>
