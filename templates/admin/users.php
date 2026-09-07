<?php /** 用户管理 */ $title = '用户管理'; $isWebmaster = (is_webmaster()); ?>
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
            <span style="flex:1;"></span>
            <?php if ($isWebmaster): ?>
            <button type="button" class="btn btn-primary" onclick="openAddUser()" style="background:#ea6f5a;border-color:#ea6f5a;">
                <i class="fa fa-plus" style="margin-right:4px;"></i>添加用户
            </button>
            <?php endif; ?>
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
                $roleMap = ['user'=>'普通','certified_user'=>'已认证','moderator'=>'版主','admin'=>'管理员','super_admin'=>'超管','webmaster'=>'站长'];
                $roleColor = ['user'=>'default','certified_user'=>'info','moderator'=>'warning','admin'=>'success','super_admin'=>'danger','webmaster'=>'webmaster'];
                // 当前操作者能否编辑本行用户：依据「用户管理」权限 code 而不仅是角色
                $canEditRow = can('user.manage') && ($u['role'] !== 'webmaster' || is_webmaster());
                // 当前操作者能否对本行用户执行「删除」管理：依据「删除用户」权限 code
                $canDeleteRow = can('user.delete') && ($u['role'] !== 'webmaster') && ($u['role'] !== 'super_admin' || is_webmaster());
                ?>
                <span class="status-tag <?= $roleColor[$u['role']] ?? 'default' ?>"><?= $roleMap[$u['role']] ?? $u['role'] ?></span>
            </td>
            <td><?= $u['is_certified'] ? '<span class="status-tag success">已认证</span>' : '<span class="status-tag default">未认证</span>' ?></td>
            <td><?= $u['status'] ? '<span class="status-tag success">正常</span>' : '<span class="status-tag danger">禁用</span>' ?></td>
            <td><?= $u['created_at'] ?></td>
            <td class="table-actions">
                <a href="<?= url('user/profile', ['id' => $u['id']]) ?>" target="_blank">查看</a>
                <?php if ($canEditRow): ?>
                <a href="javascript:void(0);" onclick="openEditUser(<?= $u['id'] ?>); return false;">编辑</a>
                <?php endif; ?>
                <?php if (can('user.manage')): ?>
                <?php if ($u['status']): ?>
                <a href="javascript:adminAction('禁用用户','确定禁用该用户？','<?= url('admin/banUser', ['id' => $u['id']]) ?>')">禁用</a>
                <?php else: ?>
                <a href="javascript:adminAction('启用用户','确定启用该用户？','<?= url('admin/unbanUser', ['id' => $u['id']]) ?>')">启用</a>
                <?php endif; ?>
                <?php if ($u['role'] !== 'moderator'): ?>
                <a href="javascript:assignModerator(<?= $u['id'] ?>)">设为版主</a>
                <?php else: ?>
                <a href="javascript:adminAction('撤销版主','确定撤销该用户版主身份？','<?= url('admin/revokeModerator', ['id' => $u['id']]) ?>')">撤销版主</a>
                <?php endif; ?>
                <?php if ($canDeleteRow): ?>
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

<!-- 编辑 / 添加用户弹窗（必须置于引用它的脚本之前） -->
<div id="editUserModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:8px;width:100%;max-width:520px;max-height:90vh;overflow:auto;">
        <div style="padding:16px 20px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center;">
            <strong><span id="eu_title_text">编辑用户</span> <span id="eu_title_id" style="display:none;">#<span></span></span></strong>
            <button type="button" onclick="closeEditUser()" style="border:none;background:none;font-size:20px;cursor:pointer;line-height:1;">&times;</button>
        </div>
        <form id="editUserForm" style="padding:20px;">
            <input type="hidden" name="id" id="eu_id">
            <input type="hidden" id="eu_self_id" value="<?= (int)(Auth::id() ?? 0) ?>">
            <input type="hidden" id="eu_self_role" value="<?= e(Auth::role()) ?>">
            <input type="hidden" id="eu_mode" value="edit"> <!-- edit | add -->

            <!-- 原「权限锁定 / 唯一站长」提示横幅已移除；站长自编辑时仍通过 select 禁用 + 🔒 徽章 + 红边框进行视觉锁定 -->

            <div class="form-group">
                <label class="form-label">用户名 <span class="text-muted" id="eu_username_required" style="display:none;color:#f5222d;">*</span></label>
                <input type="text" name="username" id="eu_username" class="form-control" maxlength="50"
                       oninput="checkNameAvailability(this,'username', document.getElementById('eu_id').value, true)"
                       onblur="checkNameAvailability(this,'username', document.getElementById('eu_id').value, true)">
                <p class="form-hint name-hint" id="eu_usernameHint" style="display:none;margin-top:6px;padding:0;font-size:13px;line-height:1.4;"></p>
            </div>
            <div class="form-group">
                <label class="form-label">昵称</label>
                <input type="text" name="nickname" id="eu_nickname" class="form-control" maxlength="50"
                       oninput="checkNameAvailability(this,'nickname', document.getElementById('eu_id').value)"
                       onblur="checkNameAvailability(this,'nickname', document.getElementById('eu_id').value)">
                <p class="form-hint name-hint" id="eu_nicknameHint" style="display:none;margin-top:6px;padding:0;font-size:13px;line-height:1.4;"></p>
            </div>
            <div class="form-group">
                <label class="form-label">头像 URL</label>
                <input type="text" name="avatar" id="eu_avatar" class="form-control" placeholder="留空则使用默认头像">
            </div>
            <!-- 密码（仅站长可改，与邮箱权限矩阵一致）：编辑时为空=不改；添加时必填 -->
            <div class="form-group" id="eu_password_group" style="display:none;">
                <label class="form-label">
                    密码 <span class="text-muted" id="eu_password_required" style="color:#f5222d;display:none;">*</span>
                    <span class="text-muted" style="font-size:11px;font-weight:normal;">（仅站长可改，编辑时留空=不修改）</span>
                </label>
                <input type="text" name="password" id="eu_password" class="form-control" autocomplete="new-password" placeholder="留空则不修改；至少 6 位">
                <p class="form-hint" id="eu_password_hint" style="display:none;">当前已设置密码</p>
            </div>
            <!-- 站长专属块：邮箱 / UID / 邮箱验证 / 账号认证 / 用户角色（仅站长可见可编辑） -->
            <div id="eu_webmaster_only_block" style="display:none;">
                <div class="form-group">
                    <label class="form-label">邮箱 <span class="text-muted" style="font-size:11px;">（仅站长可改）</span></label>
                    <input type="text" name="email" id="eu_email" class="form-control">
                </div>
                <!-- UID 字段（夹在「邮箱」和「邮箱验证状态」之间，对应业务诉求"账号认证状态和邮箱认证状态中间"） -->
                <div class="form-group" id="eu_uid_group">
                    <label class="form-label">
                        UID
                        <span class="text-muted" style="font-size:11px;font-weight:normal;">（仅站长可改；添加用户时强制 max+1，不可手填）</span>
                    </label>
                    <input type="number" name="uid" id="eu_uid" class="form-control" min="1" step="1">
                    <p class="form-hint" id="eu_uid_hint">新 uid 始终 = 当前最大 uid + 1；可手动把存量用户改大以预留编号；不能小于当前最小 UID，避免破坏 max+1 规则。</p>
                </div>
                <div class="form-group">
                    <label class="form-label">邮箱验证状态 <span class="text-muted" style="font-size:11px;">（仅站长可改）</span></label>
                    <select name="email_verified" id="eu_email_verified" class="form-control">
                        <option value="0">未验证</option>
                        <option value="1">已验证</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">账号认证状态 <span class="text-muted" style="font-size:11px;">（仅站长可改）</span></label>
                    <div id="eu_cert_groups_list" style="display:flex;flex-direction:column;gap:6px;padding:8px 4px;border:1px dashed #e5e5e5;border-radius:4px;background:#fafafa;min-height:42px;">
                        <span class="text-muted" style="font-size:12px;">加载中…</span>
                    </div>
                    <input type="hidden" name="is_certified" id="eu_is_certified" value="">
                    <p class="form-hint">勾选表示该用户已通过此认证；取消勾选视为未通过。空表示该用户未通过任何项目。增删后台认证项目后此项自动同步。<code>cert_groups[]</code> 已传入时忽略上方旧字段。</p>
                </div>
                <div class="form-group" id="eu_role_group">
                    <label class="form-label">
                        用户角色
                        <span class="text-muted" style="font-size:11px;font-weight:normal;">（仅站长可改）</span>
                        <span id="eu_role_locked_badge" style="display:none;margin-left:8px;padding:2px 8px;background:#f0f0f0;color:#888;border-radius:10px;font-size:11px;font-weight:normal;vertical-align:middle;">🔒 权限锁定</span>
                    </label>
                    <select name="role" id="eu_role" class="form-control" onchange="renderRolePerms(this.value)">
                        <?php foreach ($roles as $r): ?>
                            <?php if ($r['code'] === 'guest') continue; ?>
                            <option value="<?= e($r['code']) ?>"<?= $r['code'] === 'webmaster' ? ' data-self-only="1"' : '' ?>><?= e($r['name']) ?><?= $r['code'] === 'webmaster' ? '（仅站长本人可授予）' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint" id="eu_role_hint">角色名称与权限均取自「角色权限」模块；可互相指派，但站长本人的角色不可在此修改</p>
                </div>
            </div>
            <!-- 非站长查看时的角色只读提示 -->
            <div id="eu_role_readonly" class="form-group" style="display:none;">
                <label class="form-label">
                    用户角色
                    <span class="text-muted" style="font-size:11px;font-weight:normal;">（仅站长可修改）</span>
                </label>
                <div style="padding:8px 12px;background:#fafafa;border:1px solid #eee;border-radius:4px;font-size:13px;">
                    当前角色：<strong id="eu_role_readonly_value">—</strong>
                </div>
                <p class="form-hint">仅站长（webmaster）可修改用户角色；当前账号无权变更。</p>
            </div>
            <!-- 角色权限联动：与「角色权限」模块共用同一份 roles / role_permissions / permissions 数据，
                 选角色后展示该角色拥有的权限（始终可见，非站长只读模式也展示当前角色权限） -->
            <div class="form-group" id="eu_role_perms_group" style="display:none;">
                <label class="form-label">该角色权限 <span class="text-muted" style="font-size:11px;font-weight:normal;">（对照「角色权限」模块）</span></label>
                <div id="eu_role_perms_list" style="display:flex;flex-wrap:wrap;gap:6px;padding:10px 12px;background:#fafafa;border:1px solid #eee;border-radius:6px;font-size:13px;min-height:20px;"></div>
            </div>
            <div class="form-group">
                <label class="form-label">签名档</label>
                <textarea name="bio" id="eu_bio" class="form-control" maxlength="500" style="min-height:70px;"></textarea>
            </div>
            <div class="form-group" id="eu_status_group">
                <label class="form-label">
                    账号状态（封禁）
                    <span id="eu_status_locked_badge" style="display:none;margin-left:8px;padding:2px 8px;background:#f0f0f0;color:#888;border-radius:10px;font-size:11px;font-weight:normal;vertical-align:middle;">🔒 权限锁定</span>
                </label>
                <select name="status" id="eu_status" class="form-control">
                    <option value="1">正常</option>
                    <option value="0">封禁</option>
                </select>
                <p class="form-hint" id="eu_status_hint">封禁后该用户前台个人中心用户名后显示「封禁」字样，且无法登录；站长本人的状态不可在此修改</p>
            </div>
            <div style="margin-top:8px;text-align:right;">
                <button type="button" onclick="closeEditUser()" class="btn btn-ghost">取消</button>
                <button type="submit" id="eu_submit" class="btn btn-primary">保存修改</button>
            </div>
        </form>
    </div>
</div>

<script>
/* === 角色权限联动数据（与「角色权限」模块共用同一份 roles / role_permissions / permissions） === */
var __ROLES__ = <?= json_encode(array_map(function($r){return ['id'=>(int)$r['id'],'code'=>$r['code'],'name'=>$r['name']];}, $roles), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
var __ROLE_PERMS__ = <?= json_encode($rolePerms) ?>;
var __PERMISSIONS__ = <?= json_encode(array_map(function($p){return ['id'=>(int)$p['id'],'name'=>$p['name'],'code'=>$p['code'],'module'=>$p['module'] ?? ''];}, $permissions), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
var __HIDDEN_PERMS__ = ['category.manage', 'system.manage'];

/* 渲染所选角色拥有的权限（对照「角色权限」模块，始终显示：站长可改角色、非站长只读也展示当前角色权限） */
function renderRolePerms(roleCode) {
    var group = document.getElementById('eu_role_perms_group');
    var list = document.getElementById('eu_role_perms_list');
    if (!group || !list) return;
    while (list.firstChild) list.removeChild(list.firstChild);
    if (!roleCode) { group.style.display = 'none'; return; }
    group.style.display = 'block';
    if (roleCode === 'webmaster') {
        var all = document.createElement('span');
        all.style.color = '#ea6f5a';
        all.textContent = '全部权限（系统固定）';
        list.appendChild(all);
        return;
    }
    var roleId = null;
    for (var i = 0; i < __ROLES__.length; i++) {
        if (__ROLES__[i].code === roleCode) { roleId = __ROLES__[i].id; break; }
    }
    if (roleId === null) { group.style.display = 'none'; return; }
    var permIds = __ROLE_PERMS__[roleId] || [];
    var permMap = {};
    for (var j = 0; j < __PERMISSIONS__.length; j++) { permMap[__PERMISSIONS__[j].id] = __PERMISSIONS__[j]; }
    var frag = document.createDocumentFragment();
    var shown = 0;
    permIds.forEach(function (pid) {
        var p = permMap[pid];
        if (!p) return;
        if (__HIDDEN_PERMS__.indexOf(p.code) !== -1) return; // 与角色权限模块一致隐藏「板块管理/系统设置」
        var s = document.createElement('span');
        s.textContent = p.name;
        s.style.cssText = 'padding:2px 8px;background:#fff;border:1px solid #eee;border-radius:10px;font-size:12px;';
        frag.appendChild(s);
        shown++;
    });
    if (shown === 0) {
        var none = document.createElement('span');
        none.style.color = '#999';
        none.textContent = '无权限';
        list.appendChild(none);
    } else {
        list.appendChild(frag);
    }
}

// 后台编辑用户：用户名 / 昵称实时交叉查重（与 register.php / edit.php 同实现）。
// 第 4 参 loose=true 时跳过用户名长度约束（注册页 / 个人资料页强制 3-15 字节加权）；后端 AuthController::checkName 同时支持。
var _nameTimers = {};
function checkNameAvailability(input, field, exceptId, loose) {
    var val = (input.value || '').trim();
    var hint = document.getElementById(input.id + 'Hint')
            || input.nextElementSibling
            || (input.parentNode && input.parentNode.querySelector && input.parentNode.querySelector('.name-hint'));
    if (!hint) return;
    if (val === '') {
        hint.style.display = 'none';
        hint.textContent = '';
        input.style.borderColor = '';
        delete input.dataset.nameValid;
        return;
    }
    hint.style.display = 'block';
    hint.style.color = '#888';
    hint.textContent = '检查中...';
    delete input.dataset.nameValid;
    clearTimeout(_nameTimers[field]);
    _nameTimers[field] = setTimeout(function() {
        var params = { field: field, value: val };
        if (exceptId) params.id = exceptId;
        if (loose) params.loose = '1';
        fetch(url('auth/checkName', params), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function(r) {
            var ct = r.headers.get('content-type') || '';
            if (ct.indexOf('application/json') !== -1) {
                return r.json().then(function(res) { return { ok: r.ok, res: res, status: r.status }; });
            }
            return r.text().then(function(txt) { return { ok: false, res: null, status: r.status, text: txt.slice(0, 120) }; });
        }).then(function(out) {
            if (out.res && out.res.code === 0 && out.res.data) {
                var okText = field === 'username' ? '✓ 用户名可用' : '✓ 昵称可用';
                if (out.res.data.available) {
                    hint.style.color = '#52c41a';
                    hint.textContent = okText;
                    input.style.borderColor = '#52c41a';
                    input.dataset.nameValid = '1';
                } else {
                    hint.style.color = '#f5222d';
                    hint.textContent = '✗ 不可用：' + (out.res.data.reason || '不符合要求');
                    input.style.borderColor = '#f5222d';
                    input.dataset.nameValid = '0';
                }
            } else if (out.res && out.res.message) {
                hint.style.color = '#f5222d';
                hint.textContent = '✗ ' + out.res.message;
                input.style.borderColor = '#f5222d';
                if (out.res.data && out.res.data.available) {
                    hint.style.color = '#fa8c16';
                    hint.style.borderColor = '';
                }
            } else {
                var msg = '✗ 接口异常';
                if (out.status) msg += '（HTTP ' + out.status + '）';
                if (out.text) msg += '：' + out.text;
                msg += '，请稍后重试';
                hint.style.color = '#f5222d';
                hint.textContent = msg;
                input.style.borderColor = '#f5222d';
            }
        }).catch(function() {
            hint.style.color = '#f5222d';
            hint.textContent = '✗ 网络错误，请稍后重试';
            input.style.borderColor = '#f5222d';
        });
    }, 350);
}
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

/* ========== 添加用户 / 编辑用户 共用弹窗 ========== */
var _euCurrentMode = 'edit'; // 'edit' | 'add'

function _euResetForm() {
    // 把所有可能 dirty 的输入归零（避免从 add 切到 edit 时残留）
    var form = document.getElementById('editUserForm');
    form.reset();
    ['eu_usernameHint','eu_nicknameHint'].forEach(function(id){
        var el = document.getElementById(id);
        if (el) { el.style.display='none'; el.textContent=''; }
    });
    var cgl = document.getElementById('eu_cert_groups_list');
    if (cgl) cgl.innerHTML = '<span class="text-muted" style="font-size:12px;">加载中…</span>';
    document.getElementById('eu_is_certified').value = '';
    document.getElementById('eu_password_hint').style.display = 'none';
}

function openAddUser() {
    if (typeof postJSON !== 'function') { toast('页面脚本未加载完成，请刷新重试', 'error'); return; }
    _euCurrentMode = 'add';
    var form = document.getElementById('editUserForm');
    form.dataset.mode = 'add';
    document.getElementById('eu_mode').value = 'add';
    document.getElementById('eu_id').value = '';
    document.getElementById('eu_title_text').textContent = '添加用户';
    document.getElementById('eu_title_id').style.display = 'none';

    // 非站长：连这个按钮都不该出现；二次保护
    var selfRole = document.getElementById('eu_self_role').value || '';
    if (selfRole !== 'webmaster') { toast('仅站长可添加用户', 'error'); return; }

    _euResetForm();
    // 站长专属块全部展示
    document.getElementById('eu_webmaster_only_block').style.display = 'block';
    document.getElementById('eu_role_readonly').style.display = 'none';

    // 用户名必填红星
    document.getElementById('eu_username_required').style.display = 'inline';
    // 密码框可见 + 必填
    var pwGroup = document.getElementById('eu_password_group');
    pwGroup.style.display = 'block';
    document.getElementById('eu_password_required').style.display = 'inline';
    document.getElementById('eu_password').value = '';
    document.getElementById('eu_password_hint').style.display = 'none';

    // 添加用户：UID 锁定为"待后端分配"，前端不送 uid 字段（让后端强制 max+1）
    var uidEl = document.getElementById('eu_uid');
    uidEl.value = '由后端分配（max+1）';
    uidEl.disabled = true;
    uidEl.style.background = '#f5f5f5';
    uidEl.style.cursor = 'not-allowed';

    // 默认角色 = 普通用户；role select 启用；状态 = 正常
    var roleEl = document.getElementById('eu_role');
    roleEl.disabled = false;
    roleEl.value = 'user';
    renderRolePerms('user');
    document.getElementById('eu_status').value = '1';
    document.getElementById('eu_role_locked_badge').style.display = 'none';
    document.getElementById('eu_status_locked_badge').style.display = 'none';

    // 「站长」option 在添加模式下不展示（防止误加新站长）
    var webmasterOpt = document.querySelector('#eu_role option[value="webmaster"]');
    if (webmasterOpt) { webmasterOpt.hidden = true; webmasterOpt.disabled = true; }

    // 邮箱验证状态默认 = 已验证（站长人工添加无需收验证码）
    document.getElementById('eu_email_verified').value = '1';
    document.getElementById('eu_status').value = '1';

    // 认证项目组默认全空（管理员按需勾选）
    renderCertGroupsList([]);

    document.getElementById('eu_submit').textContent = '创建用户';
    document.getElementById('editUserModal').style.display = 'flex';
}

function openEditUser(userId) {
    if (typeof postJSON !== 'function') { toast('页面脚本未加载完成，请刷新重试', 'error'); return; }
    _euCurrentMode = 'edit';
    var form = document.getElementById('editUserForm');
    form.dataset.mode = 'edit';
    document.getElementById('eu_mode').value = 'edit';
    document.getElementById('eu_title_text').textContent = '编辑用户';
    document.getElementById('eu_title_id').style.display = 'inline';
    document.getElementById('eu_username_required').style.display = 'none';

    toast('加载用户数据中...', 'info');
    postJSON(url('admin/editUser', {id: userId}), {_token: getCsrfToken()}, function(res) {
        if (res.code !== 0) { toast(res.message || '加载失败', 'error'); return; }
        var u = res.data;
        var selfId = parseInt(document.getElementById('eu_self_id').value || '0', 10);
        var selfRole = document.getElementById('eu_self_role').value || '';
        var isSelfWebmaster = (selfRole === 'webmaster' && u.id === selfId);

        _euResetForm();

        // === 「用户角色 / 邮箱 / 邮箱验证状态 / 账号认证状态 / UID」仅站长可见可编辑 ===
        // 非站长编辑他人时：专属块整体隐藏；前端 submit handler 同步不发送这些字段。
        // 即使绕过前端，submit handler 与后端 updateUser/doAddUser 也会兜底拒绝写入。
        var isWebmaster = (selfRole === 'webmaster');
        var wmBlock = document.getElementById('eu_webmaster_only_block');
        var roBlock = document.getElementById('eu_role_readonly');
        wmBlock.style.display = isWebmaster ? 'block' : 'none';
        if (!isWebmaster) {
            roBlock.style.display = 'block';
            renderRolePerms(u.role); // 非站长只读模式也对照显示当前角色权限
            var roleSelectEl = document.getElementById('eu_role');
            document.getElementById('eu_role_readonly_value').textContent = roleSelectEl.options[roleSelectEl.selectedIndex].text || u.role;
        } else {
            roBlock.style.display = 'none';
        }

        // 仅站长：显示密码框（编辑时留空 = 不修改）
        var pwGroup = document.getElementById('eu_password_group');
        if (isWebmaster) {
            pwGroup.style.display = 'block';
            document.getElementById('eu_password_required').style.display = 'none';
            document.getElementById('eu_password').value = '';
            var pwHint = document.getElementById('eu_password_hint');
            pwHint.textContent = u.has_password ? '当前已设置密码；留空则不修改，填写则覆盖' : '当前未设置密码；填写则创建';
            pwHint.style.display = 'block';
        } else {
            pwGroup.style.display = 'none';
        }

        document.getElementById('eu_id').value = u.id;
        document.getElementById('eu_title_id').querySelector('span').textContent = u.id;
        document.getElementById('eu_username').value = u.username;
        document.getElementById('eu_nickname').value = u.nickname || '';
        document.getElementById('eu_avatar').value = u.avatar || '';
        document.getElementById('eu_email').value = u.email;
        document.getElementById('eu_uid').value = u.id;
        document.getElementById('eu_uid').disabled = false;
        document.getElementById('eu_uid').style.background = '';
        document.getElementById('eu_uid').style.cursor = '';
        document.getElementById('eu_email_verified').value = u.email_verified ? '1' : '0';
        document.getElementById('eu_bio').value = u.bio || '';
        document.getElementById('eu_role').value = u.role;
        renderRolePerms(u.role);
        document.getElementById('eu_status').value = u.status ? '1' : '0';
        document.getElementById('eu_is_certified').value = '';

        // 把「真实目标 role/status」存到 form.dataset 上，提交时优先用。
        // 关键：站长编辑自己时 #eu_role / #eu_status 是 disabled 的，
        // FormData 不会序列化 disabled 字段，否则后端收到 role='' 报"非法的用户角色"。
        // 邮箱/邮箱验证/账号认证状态/UID 仅站长可见可编辑；非站长时这些字段不会包含在 payload。
        form.dataset.targetRole = String(u.role || 'user');
        form.dataset.targetStatus = u.status ? '1' : '0';
        form.dataset.targetEmail = String(u.email || '');
        form.dataset.targetEmailVerified = u.email_verified ? '1' : '0';

        // 渲染"按认证项目组"勾选列表
        renderCertGroupsList(u.cert_groups || []);

        // === 调整「站长」option 状态 ===
        // 规则：系统全局只有一个站长，操作者若不是站长 → 选项彻底隐藏；
        //       操作者是站长但编辑的不是自己 → 选项 disabled + 改文案。
        //       操作者是站长且编辑的是自己 → 整个 role select 被锁定（下面的 isSelfWebmaster 分支处理）。
        var webmasterOpt = document.querySelector('#eu_role option[value="webmaster"]');
        if (webmasterOpt) {
            if (selfRole !== 'webmaster') {
                webmasterOpt.hidden = true;
                webmasterOpt.disabled = true;
            } else if (u.id !== selfId) {
                webmasterOpt.disabled = true;
                webmasterOpt.textContent = '站长（系统全局唯一，你已担任）';
            } else {
                webmasterOpt.disabled = false;
                webmasterOpt.textContent = '站长（系统全局唯一，不可授予他人）';
            }
        }

        // 站长本人的角色与账号状态不可改（前端锁定 + 后端兜底双重保护）。
        var roleEl = document.getElementById('eu_role');
        var statusEl = document.getElementById('eu_status');
        var roleBadge = document.getElementById('eu_role_locked_badge');
        var statusBadge = document.getElementById('eu_status_locked_badge');
        var roleGroup = document.getElementById('eu_role_group');
        var statusGroup = document.getElementById('eu_status_group');

        if (isSelfWebmaster) {
            roleEl.disabled = true;
            statusEl.disabled = true;
            roleBadge.style.display = 'inline-block';
            statusBadge.style.display = 'inline-block';
            roleEl.style.background = '#f5f5f5';
            roleEl.style.cursor = 'not-allowed';
            statusEl.style.background = '#f5f5f5';
            statusEl.style.cursor = 'not-allowed';
            roleGroup.style.padding = '8px 12px';
            roleGroup.style.borderLeft = '3px solid #ea6f5a';
            roleGroup.style.background = '#fffafa';
            roleGroup.style.borderRadius = '4px';
            statusGroup.style.padding = '8px 12px';
            statusGroup.style.borderLeft = '3px solid #ea6f5a';
            statusGroup.style.background = '#fffafa';
            statusGroup.style.borderRadius = '4px';
        } else {
            roleEl.disabled = false;
            statusEl.disabled = false;
            roleBadge.style.display = 'none';
            statusBadge.style.display = 'none';
            roleEl.style.background = '';
            roleEl.style.cursor = '';
            statusEl.style.background = '';
            statusEl.style.cursor = '';
            roleGroup.style.padding = '';
            roleGroup.style.borderLeft = '';
            roleGroup.style.background = '';
            roleGroup.style.borderRadius = '';
            statusGroup.style.padding = '';
            statusGroup.style.borderLeft = '';
            statusGroup.style.background = '';
            statusGroup.style.borderRadius = '';
        }

        document.getElementById('eu_submit').textContent = '保存修改';
        document.getElementById('editUserModal').style.display = 'flex';
    }, function(err) {
        toast((err && err.message) || '请求失败，请检查网络或刷新页面', 'error');
    });
}

function closeEditUser() { document.getElementById('editUserModal').style.display = 'none'; }

/* 把服务端返回的认证项目组渲染成勾选列表 */
function renderCertGroupsList(list) {
    var box = document.getElementById('eu_cert_groups_list');
    if (!box) return;
    box.innerHTML = '';
    if (!list.length) {
        box.innerHTML = '<span class="text-muted" style="font-size:12px;">暂无任何认证项目组，请先在「认证项设置」中添加</span>';
        return;
    }
    list.forEach(function(g) {
        var label = document.createElement('label');
        label.style.display = 'flex';
        label.style.alignItems = 'center';
        label.style.gap = '6px';
        label.style.fontWeight = 'normal';
        label.style.margin = '0';
        label.style.cursor = 'pointer';
        label.style.padding = '2px 4px';
        label.style.borderRadius = '3px';
        var cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.name = 'cert_groups[]';
        cb.value = String(g.id);
        cb.checked = !!g.is_certified;
        cb.dataset.groupId = String(g.id);
        label.appendChild(cb);
        var txt = document.createElement('span');
        txt.textContent = g.name + (g.is_default ? '（默认组）' : '');
        label.appendChild(txt);
        box.appendChild(label);
    });
}

document.getElementById('editUserForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this;
    var selfId = parseInt(document.getElementById('eu_self_id').value || '0', 10);
    var selfRole = document.getElementById('eu_self_role').value || '';
    var isWebmaster = (selfRole === 'webmaster');
    var mode = form.dataset.mode === 'add' ? 'add' : 'edit';

    // === 添加模式：把表单当成"注册表单"用，密码必填、强制 role=普通用户、UID 锁定后端分配 ===
    if (mode === 'add') {
        if (!isWebmaster) { toast('仅站长可添加用户', 'error'); return; }
        var fd = new FormData(form);
        // 添加模式下不发送 uid（后端 allocate_user_id 强制 max+1）；不发送 id（由后端生成）
        var data = {};
        fd.forEach(function (v, k) {
            // FormData 多值键（cert_groups[]）合并
            if (k === 'cert_groups[]') return;
            if (data[k] === undefined) {
                data[k] = v;
            } else {
                if (!Array.isArray(data[k])) data[k] = [data[k]];
                data[k].push(v);
            }
        });
        delete data['uid'];
        delete data['id'];
        data['cert_groups'] = Array.from(form.querySelectorAll('input[name="cert_groups[]"]:checked')).map(function (cb) { return cb.value; });
        if (!data['username'] || String(data['username']).trim() === '') { toast('请填写用户名', 'error'); return; }
        if (!data['password'] || String(data['password']).length < 6) { toast('请填写密码（至少 6 位）', 'error'); return; }
        if (!data['email'] || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(data['email']))) { toast('请填写有效邮箱', 'error'); return; }
        // 兜底：禁用 webmaster 这一支
        if (data['role'] === 'webmaster') data['role'] = 'user';

        var btn = document.getElementById('eu_submit');
        btn.disabled = true; btn.textContent = '创建中...';
        postJSON(url('admin/doAddUser'), data, function(res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) { closeEditUser(); setTimeout(function() { location.reload(); }, 800); }
            else { btn.disabled = false; btn.textContent = '创建用户'; }
        }, function(err) { toast((err && err.message) || '网络错误', 'error'); btn.disabled = false; btn.textContent = '创建用户'; });
        return;
    }

    // === 编辑模式：保留原有逻辑（兼容 disabled select 兜底） ===
    var fd = new FormData(form);
    var data = {};
    fd.forEach(function(v, k) {
        if (k === 'cert_groups[]') return;
        if (data[k] === undefined) {
            data[k] = v;
        } else {
            if (!Array.isArray(data[k])) data[k] = [data[k]];
            data[k].push(v);
        }
    });
    // === 兜底：被 disabled 的 select/checkbox 不会进入 FormData，必须从 dataset 显式回填 ===
    if (isWebmaster) {
        if (!data.role && form.dataset.targetRole) data.role = form.dataset.targetRole;
        if (!data.status && form.dataset.targetStatus) data.status = form.dataset.targetStatus;
        if (!data.email && form.dataset.targetEmail) data.email = form.dataset.targetEmail;
        if (!data.email_verified && form.dataset.targetEmailVerified) data.email_verified = form.dataset.targetEmailVerified;
    }
    // 非站长：用户角色/账号状态/邮箱/邮箱验证/UID 字段均不发送
    if (!isWebmaster) {
        delete data['role'];
        delete data['email'];
        delete data['email_verified'];
        delete data['uid'];
        delete data['password'];
    }
    // === 账号认证项目组：只有站长可改 ===
    if (isWebmaster) {
        data['cert_groups'] = Array.from(form.querySelectorAll('input[name="cert_groups[]"]:checked')).map(function (cb) { return cb.value; });
    } else {
        delete data['cert_groups'];
        delete data['is_certified'];
    }

    // 密码：仅站长可视；空字符串视为不修改 → 不要发送 password 字段
    if (isWebmaster && data['password'] && String(data['password']).trim() !== '') {
        // 保留原值
    } else {
        delete data['password'];
    }

    // UID：仅站长可视；编辑模式下若等于当前 id 不修改，不发送；否则发送整数
    if (isWebmaster && data['uid'] !== undefined && String(data['uid']) !== '' && parseInt(String(data['uid']).replace(/[^0-9]/g,''), 10) > 0) {
        var parsedUid = parseInt(String(data['uid']).replace(/[^0-9]/g,''), 10);
        if (parsedUid !== parseInt(form.dataset.targetId || document.getElementById('eu_id').value || '0', 10)) {
            data['uid'] = parsedUid;
        } else {
            delete data['uid'];
        }
    } else {
        delete data['uid'];
    }

    var btn = document.getElementById('eu_submit');
    btn.disabled = true; btn.textContent = '保存中...';
    postJSON(url('admin/updateUser'), data, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) { closeEditUser(); setTimeout(function() { location.reload(); }, 800); }
        else { btn.disabled = false; btn.textContent = '保存修改'; }
    }, function(err) { toast((err && err.message) || '网络错误', 'error'); btn.disabled = false; btn.textContent = '保存修改'; });
});
</script>
