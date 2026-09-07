<?php /** 用户管理 */ $title = '用户管理'; ?>
<div class="card" style="margin-bottom:16px;">
    <div class="card-body">
        <form method="get" action="<?= url('admin/users') ?>" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="r" value="admin/users">
            <input type="text" name="q" class="form-control" placeholder="用户名/邮箱/昵称" value="<?= e($keyword) ?>" style="width:200px;">
            <select name="role" class="form-control" style="width:130px;">
                <option value="">全部角色</option>
                <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>普通用户</option>
                <option value="certified_user" <?= $role === 'certified_user' ? 'selected' : '' ?>>已认证用户</option>
                <option value="moderator" <?= $role === 'moderator' ? 'selected' : '' ?>>版主</option>
                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>管理员</option>
                <option value="super_admin" <?= $role === 'super_admin' ? 'selected' : '' ?>>超级管理员</option>
            </select>
            <select name="cert" class="form-control" style="width:130px;">
                <option value="">认证状态</option>
                <option value="yes" <?= $certStatus === 'yes' ? 'selected' : '' ?>>已认证</option>
                <option value="no" <?= $certStatus === 'no' ? 'selected' : '' ?>>未认证</option>
            </select>
            <button type="submit" class="btn btn-primary">搜索</button>
            <span class="text-muted" style="font-size:13px;">共 <?= $total ?> 个用户</span>
        </form>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th><th>用户</th><th>邮箱</th><th>角色</th><th>认证</th><th>状态</th><th>注册时间</th><th>操作</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?= $u['id'] ?></td>
            <td>
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="width:28px;height:28px;border-radius:50%;background:#ea6f5a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;overflow:hidden;">
                        <?php if (!empty($u['avatar'])): ?>
                        <img src="<?= upload_url($u['avatar']) ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                        <?php else: ?>
                        <?= e(mb_substr($u['nickname'] ?: $u['username'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div><?= e($u['nickname'] ?: $u['username']) ?></div>
                        <div class="text-muted" style="font-size:11px;"><?= e($u['username']) ?></div>
                    </div>
                </div>
            </td>
            <td><?= e($u['email']) ?></td>
            <td>
                <?php
                $roleMap = ['user'=>'普通','certified_user'=>'已认证','moderator'=>'版主','admin'=>'管理员','super_admin'=>'超管'];
                $roleColor = ['user'=>'default','certified_user'=>'info','moderator'=>'warning','admin'=>'success','super_admin'=>'danger'];
                ?>
                <span class="status-tag <?= $roleColor[$u['role']] ?? 'default' ?>"><?= $roleMap[$u['role']] ?? $u['role'] ?></span>
            </td>
            <td><?= $u['is_certified'] ? '<span class="status-tag success">已认证</span>' : '<span class="status-tag default">未认证</span>' ?></td>
            <td><?= $u['status'] ? '<span class="status-tag success">正常</span>' : '<span class="status-tag danger">禁用</span>' ?></td>
            <td><?= $u['created_at'] ?></td>
            <td class="table-actions">
                <a href="<?= url('user/profile', ['id' => $u['id']]) ?>" target="_blank">查看</a>
                <?php if (is_super_admin()): ?>
                <a href="javascript:openEditUser(<?= $u['id'] ?>)">编辑</a>
                <?php endif; ?>
                <?php if ($u['role'] !== 'super_admin'): ?>
                <?php if ($u['status']): ?>
                <a href="javascript:adminAction('禁用用户','确定禁用该用户？','<?= url('admin/banUser', ['id' => $u['id']]) ?>')">禁用</a>
                <?php else: ?>
                <a href="javascript:adminAction('启用用户','确定启用该用户？','<?= url('admin/unbanUser', ['id' => $u['id']]) ?>')">启用</a>
                <?php endif; ?>
                <?php if (is_super_admin()): ?>
                <?php if ($u['role'] !== 'moderator'): ?>
                <a href="javascript:assignModerator(<?= $u['id'] ?>)">设为版主</a>
                <?php else: ?>
                <a href="javascript:adminAction('撤销版主','确定撤销该用户版主身份？','<?= url('admin/revokeModerator', ['id' => $u['id']]) ?>')">撤销版主</a>
                <?php endif; ?>
                <a href="javascript:adminAction('删除用户','警告：删除后无法恢复，确定继续？','<?= url('admin/deleteUser', ['id' => $u['id']]) ?>')" style="color:#f5222d;">删除</a>
                <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if (ceil($total / $perPage) > 1): ?>
<?= pagination($total, $page, $perPage, 'admin/users', ['q' => $keyword, 'role' => $role, 'cert' => $certStatus]) ?>
<?php endif; ?>

<script>
function adminAction(title, body, urlStr) {
    confirmModal(title, body, function() {
        postJSON(urlStr, {_token: getCsrfToken()}, function(res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) setTimeout(function() { location.reload(); }, 800);
        });
    });
}
function assignModerator(userId) {
    var catId = prompt('请输入要分配的板块ID（可在板块管理查看）：');
    if (!catId) return;
    postJSON(url('admin/assignModerator', {id: userId}), {category_id: catId, _token: getCsrfToken()}, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) setTimeout(function() { location.reload(); }, 800);
    });
}

/* ========== 编辑用户 ========== */
function getEditModal() { return document.getElementById('editUserModal'); }
function openEditUser(userId) {
    postJSON(url('admin/editUser', {id: userId}), {_token: getCsrfToken()}, function(res) {
        if (res.code !== 0) { toast(res.message, 'error'); return; }
        var u = res.data;
        document.getElementById('eu_id').value = u.id;
        document.getElementById('eu_title_id').textContent = u.id;
        document.getElementById('eu_username').value = u.username;
        document.getElementById('eu_nickname').value = u.nickname || '';
        document.getElementById('eu_avatar').value = u.avatar || '';
        document.getElementById('eu_email').value = u.email;
        document.getElementById('eu_email_verified').value = u.email_verified ? '1' : '0';
        document.getElementById('eu_is_certified').value = u.is_certified ? '1' : '0';
        document.getElementById('eu_bio').value = u.bio || '';
        document.getElementById('eu_role').value = u.role;
        document.getElementById('eu_status').value = u.status ? '1' : '0';
        getEditModal().style.display = 'flex';
    });
}
function closeEditUser() { getEditModal().style.display = 'none'; }
function bindEditUserForm() {
    var form = document.getElementById('editUserForm');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var fd = new FormData(this); var data = {};
        fd.forEach(function(v, k) { data[k] = v; });
        var btn = document.getElementById('eu_submit');
        btn.disabled = true; btn.textContent = '保存中...';
        postJSON(url('admin/updateUser'), data, function(res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) { closeEditUser(); setTimeout(function() { location.reload(); }, 800); }
            else { btn.disabled = false; btn.textContent = '保存修改'; }
        }, function() { toast('网络错误', 'error'); btn.disabled = false; btn.textContent = '保存修改'; });
    });
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindEditUserForm);
} else {
    bindEditUserForm();
}
</script>

<!-- 编辑用户弹窗（置于脚本之前，确保 DOM 已就绪） -->
<div id="editUserModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:8px;width:100%;max-width:520px;max-height:90vh;overflow:auto;">
        <div style="padding:16px 20px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center;">
            <strong>编辑用户 #<span id="eu_title_id"></span></strong>
            <button type="button" onclick="closeEditUser()" style="border:none;background:none;font-size:20px;cursor:pointer;line-height:1;">&times;</button>
        </div>
        <form id="editUserForm" style="padding:20px;">
            <input type="hidden" name="id" id="eu_id">
            <div class="form-group">
                <label class="form-label">用户名</label>
                <input type="text" name="username" id="eu_username" class="form-control" maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label">昵称</label>
                <input type="text" name="nickname" id="eu_nickname" class="form-control" maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label">头像 URL</label>
                <input type="text" name="avatar" id="eu_avatar" class="form-control" placeholder="留空则使用默认头像">
            </div>
            <div class="form-group">
                <label class="form-label">邮箱</label>
                <input type="text" name="email" id="eu_email" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">邮箱验证状态</label>
                <select name="email_verified" id="eu_email_verified" class="form-control">
                    <option value="0">未验证</option>
                    <option value="1">已验证</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">账号认证状态</label>
                <select name="is_certified" id="eu_is_certified" class="form-control">
                    <option value="0">未认证</option>
                    <option value="1">已认证</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">签名档</label>
                <textarea name="bio" id="eu_bio" class="form-control" maxlength="500" style="min-height:70px;"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">用户角色</label>
                <select name="role" id="eu_role" class="form-control">
                    <option value="user">普通用户</option>
                    <option value="moderator">版主</option>
                    <option value="admin">管理员</option>
                    <option value="super_admin">超级管理员</option>
                </select>
                <p class="form-hint">超级管理员角色不可在此修改</p>
            </div>
            <div class="form-group">
                <label class="form-label">账号状态（封禁）</label>
                <select name="status" id="eu_status" class="form-control">
                    <option value="1">正常</option>
                    <option value="0">封禁</option>
                </select>
                <p class="form-hint">封禁后该用户前台个人中心用户名后显示「封禁」字样，且无法登录</p>
            </div>
            <div style="margin-top:8px;text-align:right;">
                <button type="button" onclick="closeEditUser()" class="btn btn-ghost">取消</button>
                <button type="submit" id="eu_submit" class="btn btn-primary">保存修改</button>
            </div>
        </form>
    </div>
</div>
