<?php /** 邀请注册管理 */ $title = '邀请注册'; ?>
<?php
$now = date('Y-m-d H:i:s');
foreach ($invites as &$iv) {
    if ((int)$iv['status'] === 0) {
        $iv['_status_label'] = '已停用';
        $iv['_status_cls'] = '#999';
    } elseif (!empty($iv['expires_at']) && $iv['expires_at'] < $now) {
        $iv['_status_label'] = '已过期';
        $iv['_status_cls'] = '#999';
    } elseif ((int)$iv['used_count'] >= (int)$iv['max_uses']) {
        $iv['_status_label'] = '已用完';
        $iv['_status_cls'] = '#999';
    } else {
        $iv['_status_label'] = '有效';
        $iv['_status_cls'] = '#52c41a';
    }
}
unset($iv);
?>
<div class="card" style="margin-bottom:16px;">
    <div class="card-header">生成邀请码</div>
    <div class="card-body">
        <form id="genForm" onsubmit="return false;">
            <?= csrf_field() ?>
            <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;">
                <div class="form-group" style="margin:0;">
                    <label class="form-label">有效期（天，0=永久）</label>
                    <input type="number" name="expire_days" min="0" max="3650" value="0" class="form-control" style="width:150px;">
                </div>
                <?php if (is_webmaster()): ?>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">
                        最大使用次数
                        <span class="text-muted" style="font-size:11px;font-weight:normal;">（站长专属）</span>
                    </label>
                    <input type="number" name="max_uses" min="1" max="999" value="1" class="form-control" style="width:130px;">
                </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary" id="genBtn">生成邀请码</button>
            </div>
            <p class="form-hint">
                邀请码生成后，把下方「邀请链接」发给好友，对方打开即可注册。
                <?php if (is_webmaster()): ?>
                <strong style="color:#ea6f5a;">站长可设定 1-999 人的使用上限；</strong>
                <?php endif; ?>
                一码仅限一人使用。站点需处于「邀请注册」模式。
            </p>
        </form>
        <div id="genResult" style="display:none;margin-top:16px;padding:14px 16px;border:1px dashed #ea6f5a;border-radius:8px;background:#fff7f5;">
            <div style="font-size:13px;color:#888;margin-bottom:6px;">邀请码</div>
            <div style="font-size:22px;font-weight:700;letter-spacing:2px;color:#ea6f5a;margin-bottom:12px;" id="resCode"></div>
            <div style="font-size:13px;color:#888;margin-bottom:6px;">邀请链接</div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="text" id="resLink" class="form-control" style="flex:1;min-width:220px;" readonly>
                <button type="button" class="btn" onclick="copyText(document.getElementById('resLink').value)">复制链接</button>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-header">我的邀请码（<?= count($invites) ?>）</div>
    <div class="card-body">
        <?php if (empty($invites)): ?>
            <p class="text-muted" style="margin:0;">还没有生成任何邀请码。</p>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>邀请码</th>
                    <th>状态</th>
                    <th>已使用</th>
                    <th>有效期</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invites as $iv): ?>
                <tr>
                    <td style="font-weight:600;letter-spacing:1px;color:#ea6f5a;">
                        <?= e($iv['code']) ?>
                        <a href="javascript:;" onclick="copyText('<?= e($iv['code']) ?>')" title="复制邀请码" style="color:#ea6f5a;margin-left:8px;font-size:12px;font-weight:normal;text-decoration:none;">复制</a>
                    </td>
                    <td><span style="color:<?= $iv['_status_cls'] ?>;font-weight:600;"><?= $iv['_status_label'] ?></span></td>
                    <td><?= (int)$iv['used_count'] ?><span style="color:#bbb;">/<?= (int)$iv['max_uses'] ?></span></td>
                    <td><?= empty($iv['expires_at']) ? '永久' : e(date('Y-m-d', strtotime($iv['expires_at']))) ?></td>
                    <td><?= e(date('Y-m-d H:i', strtotime($iv['created_at']))) ?></td>
                    <td>
                        <a href="javascript:;" style="color:#ea6f5a;margin-right:10px;" onclick="copyText('<?= e(url('auth/register', ['code' => $iv['code']], true)) ?>')">复制链接</a>
                        <?php if ((int)$iv['status'] === 1): ?>
                        <a href="javascript:;" style="color:#f5222d;" onclick="revokeInvite(<?= (int)$iv['id'] ?>, this)">停用</a>
                        <?php else: ?>
                        <span style="color:#bbb;">已停用</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">我已邀请的用户（<?= count($invitedUsers) ?>）</div>
    <div class="card-body">
        <?php
        // 诊断：累计已使用次数 vs 实际归入当前用户名下的注册用户数
        $_usedTotal = 0;
        foreach (($invites ?? []) as $__iv) { $_usedTotal += (int)$__iv['used_count']; }
        ?>
        <?php if (empty($invitedUsers)): ?>
            <?php if ($_usedTotal > 0): ?>
                <p class="text-muted" style="margin:0;">
                    您的邀请码已被使用了 <strong style="color:#ea6f5a;"><?= (int)$_usedTotal ?></strong> 次，但暂未在下方显示。
                    常见原因：
                </p>
                <ul style="color:#888;font-size:13px;line-height:1.8;margin-top:8px;padding-left:20px;">
                    <li>对方注册时站点注册模式<strong>不是「邀请注册」</strong>（系统不会写入 <code>invited_by</code>）→ 请到「系统设置」确认当前是邀请注册模式后让对方重新注册。</li>
                    <li>数据库迁移未执行：已安装站点需要访问 <code>install/upgrade.php</code> 才会创建 <code>invites</code> 表与 <code>users.invited_by / invite_code</code> 列。</li>
                    <li>对方注册成功但被你或管理员手动清理过账号。</li>
                </ul>
            <?php else: ?>
                <p class="text-muted" style="margin:0;">还没有通过您的邀请码注册的用户。</p>
            <?php endif; ?>
        <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:14px;">
            <?php foreach ($invitedUsers as $u): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid #f0f0f0;border-radius:8px;min-width:220px;">
                <?= avatar_html($u, 36, true) ?>
                <div style="line-height:1.4;">
                    <div style="font-weight:600;font-size:14px;">
                        <a href="<?= url('user/profile', ['id' => $u['id']]) ?>" style="color:#333;text-decoration:none;"><?= e($u['nickname'] ?: $u['username']) ?></a>
                    </div>
                    <div style="font-size:12px;color:#999;">
                        <?= e(role_display_name($u['role'])) ?> · <?= e(date('Y-m-d', strtotime($u['created_at']))) ?> 加入
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function(){ toast('已复制到剪贴板', 'success'); }, function(){ fallbackCopy(text); });
    } else {
        fallbackCopy(text);
    }
}
function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); toast('已复制到剪贴板', 'success'); }
    catch (e) { toast('复制失败，请手动复制', 'error'); }
    document.body.removeChild(ta);
}

document.getElementById('genForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('genBtn');
    btn.disabled = true;
    btn.textContent = '生成中...';
    var fd = new FormData(this);
    var data = {};
    fd.forEach(function(v, k) { data[k] = v; });
    postJSON(url('user/inviteGenerate'), data, function(res) {
        if (res.code === 0) {
            document.getElementById('resCode').textContent = res.data.code;
            document.getElementById('resLink').value = res.data.link;
            document.getElementById('genResult').style.display = 'block';
            toast('邀请码已生成', 'success');
            setTimeout(function() { location.reload(); }, 1200);
        } else {
            toast(res.message || '生成失败', 'error');
        }
        btn.disabled = false;
        btn.textContent = '生成邀请码';
    }, function(res) {
        toast(res.message || '网络错误，请稍后重试', 'error');
        btn.disabled = false;
        btn.textContent = '生成邀请码';
    });
});

function revokeInvite(id, btn) {
    if (!confirm('确定停用该邀请码？已注册用户不受影响，未被使用的链接将失效。')) return;
    btn.disabled = true;
    postJSON(url('user/inviteRevoke'), {id: id, _token: getCsrfToken()}, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) setTimeout(function() { location.reload(); }, 600);
        else btn.disabled = false;
    }, function(res) {
        toast(res.message || '网络错误', 'error');
        btn.disabled = false;
    });
}
</script>
