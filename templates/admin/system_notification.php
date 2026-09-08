<?php /** 系统通知发送 - 仅站长 */ $title = '系统通知'; ?>
<h2 style="font-size:20px;margin-bottom:14px;display:flex;align-items:center;gap:10px;">
    <svg viewBox="0 0 24 24" style="width:22px;height:22px;color:#ea6f5a;" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2L4 6v6c0 5 3.5 9.5 8 10 4.5-.5 8-5 8-10V6l-8-4z" fill="currentColor"/>
        <path d="M10 12l-1.5 1.5L11 16l4-4-1.5-1.5L11 13l-1-1z" fill="#fff"/>
    </svg>
    系统通知 <span class="text-muted" style="font-size:12px;font-weight:normal;">（仅站长）</span>
</h2>
<div class="alert alert-info" style="font-size:13px;">
    系统通知会以 <span style="color:#ea6f5a;font-weight:600;">红边高亮</span> 出现在站内所有用户的「消息通知 → 系统通知」tab 下，
    用于发布站务公告、活动通知、安全提醒等。支持一次发送任意人数，目标支持按角色组或精确 ID/用户名筛选。
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-header">发送系统通知</div>
    <div class="card-body">
        <form method="post" action="<?= url('admin/sendSystemNotification') ?>" id="sysNotifyForm">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">通知摘要（标题）<span class="required">*</span></label>
                <input type="text" name="title" class="form-control" maxlength="100" placeholder="如：站务通知 / 公告 / 维护提醒" required>
                <div class="form-hint">站内显示为「摘要 + 内容」，摘要上限 100 字。</div>
            </div>

            <div class="form-group">
                <label class="form-label">通知内容 <span class="required">*</span></label>
                <textarea name="content" class="form-control" rows="3" maxlength="255" required placeholder="请输入通知正文，单行展示，最多 255 字。"></textarea>
                <div class="form-hint">单行展示，若过长将截断显示。上限 255 字。</div>
            </div>

            <div class="form-group">
                <label class="form-label">跳转链接（可选）</label>
                <input type="text" name="link" class="form-control" placeholder="如公告对应的帖子/活动链接，不填则通知仅展示内容不跳转">
                <div class="form-hint">填写站内相对路径或完整 URL；用户点击通知中「查看」会跳到此链接。</div>
            </div>

            <div class="form-group">
                <label class="form-label">发送目标 <span class="required">*</span></label>
                <div style="display:flex;gap:14px;flex-wrap:wrap;">
                    <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;padding:10px 14px;border:1px solid #eee;border-radius:6px;">
                        <input type="radio" name="target_type" value="all" checked onchange="toggleTarget('all')">
                        <span>所有用户</span>
                        <span class="text-muted" style="font-size:12px;">（全站广播）</span>
                    </label>
                    <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;padding:10px 14px;border:1px solid #eee;border-radius:6px;">
                        <input type="radio" name="target_type" value="role" onchange="toggleTarget('role')">
                        <span>按角色组</span>
                    </label>
                    <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;padding:10px 14px;border:1px solid #eee;border-radius:6px;">
                        <input type="radio" name="target_type" value="users" onchange="toggleTarget('users')">
                        <span>指定用户</span>
                        <span class="text-muted" style="font-size:12px;">（用户名/ID）</span>
                    </label>
                </div>

                <!-- 角色组选项 -->
                <div id="targetRole" style="display:none;margin-top:12px;padding:14px;background:#fafafa;border:1px solid #eee;border-radius:6px;">
                    <div class="form-hint" style="margin-bottom:8px;">选择要接收此通知的角色组（站长默认全部都能收，可不选）；同一用户发送 1 条。</div>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                        <?php foreach ($roleList as $code => $name): ?>
                        <label style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;background:#fff;border:1px solid #eee;border-radius:4px;cursor:pointer;">
                            <input type="checkbox" name="roles[]" value="<?= e($code) ?>">
                            <span><?= e($name) ?></span>
                            <code style="font-size:11px;color:#888;"><?= e($code) ?></code>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 指定用户选项 -->
                <div id="targetUsers" style="display:none;margin-top:12px;padding:14px;background:#fafafa;border:1px solid #eee;border-radius:6px;">
                    <div class="form-hint" style="margin-bottom:8px;">每行一个：用户 ID 或用户名（不区分大小写会自动匹配 username），用逗号或换行分隔，不限数量。</div>
                    <textarea name="user_list" class="form-control" rows="4" placeholder="例如：&#10;amo&#10;101&#10;tom,jerry"></textarea>
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:18px;">
                <button type="submit" class="btn btn-primary" id="sysNotifySubmit">立即发送</button>
                <button type="reset" class="btn">重置</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">发送历史（最近 20 条）</div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($logs)): ?>
        <div class="empty-state"><div class="empty-icon">空</div><p>暂无发送记录</p></div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:140px;">发送时间</th>
                    <th>摘要</th>
                    <th style="width:120px;">目标</th>
                    <th style="width:80px;text-align:right;">接收人</th>
                    <th>发送人</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log):
                    $tt = $log['target_type'] ?? 'all';
                    $ttLabel = $tt === 'all' ? '全员' : ($tt === 'role' ? '角色组' : '指定用户');
                    $tv = $log['target_value'] ?? '';
                    if ($tt === 'role' && $tv !== '') {
                        $tvArr = json_decode($tv, true);
                        if (is_array($tvArr)) {
                            $ttLabel .= '：' . implode(', ', array_map(function ($c) use ($roleList) { return $roleList[$c] ?? $c; }, $tvArr));
                        }
                    } elseif ($tt === 'users' && $tv !== '') {
                        $tvArr = json_decode($tv, true);
                        if (is_array($tvArr)) {
                            $preview = array_slice($tvArr, 0, 5);
                            $ttLabel .= '：' . implode(', ', array_map(function ($x) { return mb_strlen((string)$x) > 12 ? mb_substr((string)$x, 0, 12) . '…' : $x; }, $preview));
                            if (count($tvArr) > 5) $ttLabel .= ' +' . (count($tvArr) - 5);
                        }
                    }
                ?>
                <tr>
                    <td style="color:#999;font-size:12px;"><?= e($log['created_at']) ?></td>
                    <td>
                        <div style="font-weight:500;color:#2f2f2f;"><?= e(mb_substr($log['title'], 0, 30)) ?><?= mb_strlen($log['title']) > 30 ? '…' : '' ?></div>
                        <div style="font-size:12px;color:#888;margin-top:2px;"><?= e(mb_substr($log['content'], 0, 60)) ?><?= mb_strlen($log['content']) > 60 ? '…' : '' ?></div>
                    </td>
                    <td><span class="status-tag info"><?= e($ttLabel) ?></span></td>
                    <td style="text-align:right;color:#ea6f5a;font-weight:600;"><?= (int)$log['sent_count'] ?></td>
                    <td style="font-size:13px;color:#555;"><?= e($log['sender_nick'] ?: $log['sender_name'] ?: '站长') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleTarget(t) {
    document.getElementById('targetRole').style.display  = (t === 'role')  ? 'block' : 'none';
    document.getElementById('targetUsers').style.display = (t === 'users') ? 'block' : 'none';
}

// 表单 → AJAX：拦截原生 submit，由 postJSON 走后端；成功后 toast 提示并刷新页面让历史表更新
document.getElementById('sysNotifyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = e.target;
    var btn  = document.getElementById('sysNotifySubmit');
    if (btn.disabled) return;

    // 基础校验（浏览器 required 已挡住大部分非法）
    var title = (form.title.value || '').trim();
    var content = (form.content.value || '').trim();
    if (!title)   { window.toast('请填写通知摘要（标题）', 'error'); form.title.focus(); return; }
    if (!content) { window.toast('请填写通知内容', 'error'); form.content.focus(); return; }
    if (title.length > 100)   { window.toast('摘要不能超过 100 字', 'error'); return; }
    if (content.length > 255) { window.toast('内容不能超过 255 字', 'error'); return; }
    var targetType = (form.target_type.value || '');
    if (targetType === 'role') {
        var anyChecked = form.querySelectorAll('input[name="roles[]"]:checked').length;
        if (anyChecked === 0) { window.toast('请至少选择一个角色组', 'error'); return; }
    }
    if (targetType === 'users') {
        var ul = (form.user_list.value || '').trim();
        if (!ul) { window.toast('请输入至少一个用户名或用户 ID', 'error'); form.user_list.focus(); return; }
    }

    btn.disabled = true;
    var oldText = btn.textContent;
    btn.textContent = '发送中...';

    // 序列化（checkbox 用 getAll 习惯）
    var payload = {
        title: title,
        content: content,
        link: (form.link.value || '').trim(),
        target_type: targetType,
        roles: [],
        user_list: '',
        _token: window.getCsrfToken()
    };
    if (targetType === 'role') {
        form.querySelectorAll('input[name="roles[]"]:checked').forEach(function (cb) { payload.roles.push(cb.value); });
    } else if (targetType === 'users') {
        payload.user_list = (form.user_list.value || '').trim();
    }

    window.postJSON(url('admin/sendSystemNotification'), payload,
        function (res) {
            if (res && res.code === 0) {
                var sent = (res.data && res.data.sent) ? res.data.sent : 0;
                window.toast((res.message || ('已成功向 ' + sent + ' 名用户发送通知')), 'success');
                // 简单重置表单 + 刷新页面（让历史表立刻看到这一条）
                setTimeout(function () {
                    form.reset();
                    toggleTarget('all');
                    btn.disabled = false;
                    btn.textContent = oldText;
                    location.reload();
                }, 600);
            } else {
                window.toast((res && res.message) || '发送失败', 'error');
                btn.disabled = false;
                btn.textContent = oldText;
            }
        },
        function (err) {
            window.toast((err && err.message) || '网络错误，发送失败', 'error');
            btn.disabled = false;
            btn.textContent = oldText;
        }
    );
});
</script>
