<?php /** 板块管理 */ $title = '板块管理'; ?>
<div class="card" style="margin-bottom:16px;">
    <div class="card-header">新建板块</div>
    <div class="card-body">
        <form id="addCatForm" onsubmit="return false;" style="display:flex;flex-direction:column;gap:14px;">
            <?= csrf_field() ?>
            <div class="form-grid" style="grid-template-columns:1fr 2fr;">
                <div class="form-group" style="margin:0;">
                    <label class="form-label">板块名称</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">描述</label>
                    <input type="text" name="description" class="form-control">
                </div>
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">Font Awesome / iconfont 图标类名（v6）</label>
                <input type="text" name="icon" class="form-control" placeholder="如：fa-solid fa-house / fa-brands fa-weibo">
                <p class="form-hint">填写 <code>fa-solid fa-图标名</code> 即可（如 <code>fa-solid fa-house</code>，品牌图标用 <code>fa-brands fa-weibo</code>）。兼容误填的 FA4 写法（<code>fa fa-home</code> 会自动归一到 FA6 新名）。填写后在侧边栏、板块信息等位置同步显示。<a href="<?= url('admin/icons') ?>" style="color:#ea6f5a;">参考图标库 →</a></p>
            </div>

            <div class="form-grid-icon">
                <div class="form-group" style="margin:0;">
                    <label class="form-label">版主（手动输入用户名，多个用逗号/空格分隔）</label>
                    <input type="text" name="moderator_names" class="form-control" placeholder="如：admin, amo 或 admin amo">
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">颜色</label>
                    <input type="text" name="color" class="form-control" placeholder="#ea6f5a">
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">排序</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">仅认证</label>
                    <select name="is_certification_required" class="form-control">
                        <option value="0">否</option>
                        <option value="1">是</option>
                    </select>
                </div>
            </div>

            <div class="role-permissions" style="display:flex;flex-direction:column;gap:20px;padding:16px;background:#f8f9fa;border-radius:8px;border:1px solid #e9ecef;">
                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-weight:600;">浏览权限（角色多选，留空=所有人均可浏览）</label>
                    <div class="role-checkbox-group" style="display:flex;flex-wrap:wrap;gap:16px;padding:8px 0;">
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="browse_roles[]" value="guest"> 游客</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="browse_roles[]" value="user"> 普通用户</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="browse_roles[]" value="certified"> 认证用户</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="browse_roles[]" value="moderator"> 版主</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="browse_roles[]" value="admin"> 管理员</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="browse_roles[]" value="super_admin"> 超级管理员</label>
                    </div>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-weight:600;">发表权限（角色多选，留空=所有登录用户均可发表）</label>
                    <div class="role-checkbox-group" style="display:flex;flex-wrap:wrap;gap:16px;padding:8px 0;">
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="publish_roles[]" value="user"> 普通用户</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="publish_roles[]" value="certified"> 认证用户</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="publish_roles[]" value="moderator"> 版主</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="publish_roles[]" value="admin"> 管理员</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="publish_roles[]" value="super_admin"> 超级管理员</label>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">版规（板块页展示，支持 Markdown）</label>
                <textarea name="rule" class="form-control" rows="3" placeholder="支持 Markdown：**加粗** *斜体* ## 标题 > 引用 `代码` - 列表 [链接](url)"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="align-self:flex-start;">添加</button>
        </form>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr><th>ID</th><th>板块名称</th><th>描述</th><th>版主</th><th>仅认证</th><th>帖子数</th><th>排序</th><th>状态</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach ($cats as $c): ?>
        <tr>
            <td><?= $c['id'] ?></td>
            <td>
                <strong><?= e($c['name']) ?></strong>
                <?php if (!empty($c['icon'])): ?>
                &nbsp;<i class="<?= e(fa_icon_class($c['icon'])) ?>" style="color:#666;"></i>
                <?php endif; ?>
            </td>
            <td><?= e($c['description']) ?></td>
            <td>
                <?php if (!empty($c['moderators'])): ?>
                    <?php foreach ($c['moderators'] as $cm): ?>
                    <span class="status-tag success" style="margin-right:4px;"><?= e($cm['nickname'] ?: $cm['username']) ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span style="color:#bbb;">-</span>
                <?php endif; ?>
            </td>
            <td><?= $c['is_certification_required'] ? '<span class="status-tag warning">是</span>' : '否' ?></td>
            <td><?= $c['actual_count'] ?></td>
            <td><?= $c['sort_order'] ?></td>
            <td><?= $c['status'] ? '<span class="status-tag success">开启</span>' : '<span class="status-tag danger">关闭</span>' ?></td>
            <td class="table-actions">
                <a href="javascript:editCategory(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)">编辑</a>
                <?php if (is_high_admin()): ?>
                <a href="javascript:adminAction('删除板块','确定删除该板块？','<?= url('admin/deleteCategory', ['id' => $c['id']]) ?>')" style="color:#f5222d;">删除</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header"><span>编辑板块</span><button class="modal-close" onclick="document.getElementById('editModal').classList.remove('active')">&times;</button></div>
        <form id="editCatForm" onsubmit="return false;">
            <?= csrf_field() ?>
            <input type="hidden" name="id">
            <div class="form-group"><label class="form-label">名称</label><input type="text" name="name" class="form-control" required></div>
            <div class="form-group"><label class="form-label">描述</label><input type="text" name="description" class="form-control"></div>
            <div class="form-group">
                <label class="form-label">Font Awesome / iconfont 图标类名（v6）</label>
                <input type="text" name="icon" class="form-control" placeholder="如：fa-solid fa-house">
            </div>
            <div class="form-group">
                <label class="form-label">版主（手动输入用户名，多个用逗号/空格分隔）</label>
                <input type="text" name="moderator_names" class="form-control" id="editModNames" placeholder="如：admin, amo">
                <p class="form-hint">已设置的版主：<span id="editModList" style="color:#666;"></span></p>
            </div>
            <div class="form-group"><label class="form-label">颜色</label><input type="text" name="color" class="form-control" placeholder="#ea6f5a"></div>
            <div class="form-group">
                <label class="form-label">版规（板块页展示，支持 Markdown）</label>
                <textarea name="rule" class="form-control" rows="4"></textarea>
            </div>
            <div class="form-grid-3">
                <div class="form-group" style="margin:0;"><label class="form-label">排序</label><input type="number" name="sort_order" class="form-control" value="0"></div>
                <div class="form-group" style="margin:0;"><label class="form-label">仅认证发帖</label>
                    <select name="is_certification_required" class="form-control"><option value="0">否</option><option value="1">是</option></select>
                </div>
                <div class="form-group" style="margin:0;"><label class="form-label">状态</label>
                    <select name="status" class="form-control"><option value="1">开启</option><option value="0">关闭</option></select>
                </div>
            </div>
            <div class="role-permissions" style="display:flex;flex-direction:column;gap:20px;padding:16px;background:#f8f9fa;border-radius:8px;border:1px solid #e9ecef;">
                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-weight:600;">浏览权限（角色多选，留空=所有人均可浏览）</label>
                    <div class="role-checkbox-group" style="display:flex;flex-wrap:wrap;gap:16px;padding:8px 0;">
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="browse_roles[]" value="guest"> 游客</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="browse_roles[]" value="user"> 普通用户</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="browse_roles[]" value="certified"> 认证用户</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="browse_roles[]" value="moderator"> 版主</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="browse_roles[]" value="admin"> 管理员</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="browse_roles[]" value="super_admin"> 超级管理员</label>
                    </div>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label" style="font-weight:600;">发表权限（角色多选，留空=所有登录用户均可发表）</label>
                    <div class="role-checkbox-group" style="display:flex;flex-wrap:wrap;gap:16px;padding:8px 0;">
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="publish_roles[]" value="user"> 普通用户</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="publish_roles[]" value="certified"> 认证用户</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="publish_roles[]" value="moderator"> 版主</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="publish_roles[]" value="admin"> 管理员</label>
                        <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;white-space:nowrap;"><input type="checkbox" name="publish_roles[]" value="super_admin"> 超级管理员</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">保存</button></div>
        </form>
    </div>
</div>

<script>
// 历史原因：本页早期实现只把 addCatForm 绑了一次 submit handler（不含 browse/publish 角色多选归一），
// 后续为了支持「浏览/发表角色多选」又补了一版完整 handler。两次绑定会同时触发，导致点击「添加」
// 会向 /admin/storeCategory 连发两次 POST，DB 出现两条 id 相邻、内容相同的板块记录。
// 因此这里**只保留一版**完整 handler（包含 fd.set('browse_roles', ...) 与 fd.set('publish_roles', ...)
// 把多选复选框归一成逗号分隔字符串），不再重复绑定。
(function () {
    // 新建板块
    document.getElementById('addCatForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(this);
        var browse  = Array.prototype.filter.call(this.querySelectorAll('input[name="browse_roles[]"]'),  function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
        var publish = Array.prototype.filter.call(this.querySelectorAll('input[name="publish_roles[]"]'), function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
        fd.set('browse_roles',  browse.join(','));
        fd.set('publish_roles', publish.join(','));
        var data = {};
        fd.forEach(function (v, k) { data[k] = v; });
        postJSON(url('admin/storeCategory'), data, function (res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) setTimeout(function () { location.reload(); }, 800);
        }, function (res) {
            var msg = (res && res.message) ? res.message : '保存失败，请稍后重试';
            toast(msg, 'error', 5000);
        });
    });

    // 编辑板块（模态框）
    document.getElementById('editCatForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(this);
        var browse  = Array.prototype.filter.call(this.querySelectorAll('input[name="browse_roles[]"]'),  function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
        var publish = Array.prototype.filter.call(this.querySelectorAll('input[name="publish_roles[]"]'), function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
        fd.set('browse_roles',  browse.join(','));
        fd.set('publish_roles', publish.join(','));
        var data = {};
        fd.forEach(function (v, k) { data[k] = v; });
        postJSON(url('admin/updateCategory', { id: data.id }), data, function (res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) setTimeout(function () { location.reload(); }, 800);
        }, function (res) {
            var msg = (res && res.message) ? res.message : '保存失败，请稍后重试';
            toast(msg, 'error', 5000);
        });
    });
})();

function editCategory(c) {
    var form = document.getElementById('editCatForm');
    ['id','name','description','rule','icon','color','sort_order'].forEach(function(k) {
        if (form[k]) form[k].value = c[k] || '';
    });
    form.is_certification_required.value = c.is_certification_required;
    form.status.value = c.status;
    // 浏览/发表角色权限回填
    var browse = (c.browse_roles || '').split(',').map(function(s){return s.trim();}).filter(Boolean);
    var publish = (c.publish_roles || '').split(',').map(function(s){return s.trim();}).filter(Boolean);
    Array.prototype.forEach.call(form.querySelectorAll('input[name="browse_roles[]"]'), function(cb) {
        cb.checked = browse.indexOf(cb.value) !== -1;
    });
    Array.prototype.forEach.call(form.querySelectorAll('input[name="publish_roles[]"]'), function(cb) {
        cb.checked = publish.indexOf(cb.value) !== -1;
    });
    // 版主回填为逗号分隔的 username 列表
    if (c.moderators && c.moderators.length) {
        var names = [];
        var labels = [];
        for (var i = 0; i < c.moderators.length; i++) {
            names.push(c.moderators[i].username);
            labels.push(c.moderators[i].nickname || c.moderators[i].username);
        }
        document.getElementById('editModNames').value = names.join(', ');
        document.getElementById('editModList').textContent = labels.join('、');
    } else {
        document.getElementById('editModNames').value = '';
        document.getElementById('editModList').textContent = '（暂无）';
    }
    document.getElementById('editModal').classList.add('active');
}

function adminAction(title, body, urlStr) {
    confirmModal(title, body, function() {
        postJSON(urlStr, {_token: getCsrfToken()}, function(res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) setTimeout(function() { location.reload(); }, 800);
        }, function (res) {
            var msg = (res && res.message) ? res.message : '操作失败，请稍后重试';
            toast(msg, 'error', 5000);
        });
    });
}
</script>