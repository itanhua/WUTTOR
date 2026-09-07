<?php /** 编辑资料页 */ $title = '编辑资料'; $hideSidebar = true; $js = 'js/mention.js'; ?>
<?php
// 默认认证项目名称（后台第一个组，即「实名认证」）
$defaultCertGroup = null;
try { $defaultCertGroup = Model::table('certification_groups')->where('status', 1)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->first(); } catch (Exception $e) {}
$defaultCertGroupName = $defaultCertGroup ? $defaultCertGroup['name'] : '实名认证';
// 是否封禁（禁言）：status!=1 且非超级管理员/站长（与 banned 中间件保持一致）
$isBanned = ((int)($user['status'] ?? 1) !== 1) && !in_array(Auth::role(), ['super_admin', 'webmaster'], true);
?>
<div class="card">
    <div class="card-header">编辑资料</div>
    <div class="card-body">
        <?php if ($isBanned): ?>
        <div style="margin-bottom:18px;padding:12px 14px;border-radius:6px;background:#fff7f4;border:1px solid #ffd5cd;color:#a63720;font-size:13px;line-height:1.7;">
            <div style="display:flex;align-items:center;gap:8px;font-weight:600;margin-bottom:4px;">
                <span style="display:inline-flex;width:18px;height:18px;border-radius:50%;background:#ea6f5a;color:#fff;align-items:center;justify-content:center;font-size:11px;">🔒</span>
                账号已被封禁（全站禁言）
            </div>
            <div style="opacity:.9;">当前账号处于封禁状态，资料不可修改。如需恢复，请联系管理员解封。</div>
        </div>
        <?php endif; ?>
        <fieldset style="border:none;padding:0;margin:0;<?= $isBanned ? 'opacity:.6;' : '' ?>"<?= $isBanned ? ' disabled' : '' ?>>
        <div style="display:flex;gap:24px;margin-bottom:24px;align-items:center;flex-wrap:wrap;">
            <div class="avatar-lg" id="avatarPreview" style="width:80px;height:80px;border-radius:50%;background:#ea6f5a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;overflow:hidden;">
                <?php if (!empty($user['avatar'])): ?>
                <img src="<?= upload_url($user['avatar']) ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                <?php else: ?>
                <?= e(mb_substr($user['nickname'] ?: $user['username'], 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div>
                <input type="file" id="avatarInput" accept="image/*" style="display:none;">
                <button class="btn" onclick="document.getElementById('avatarInput').click()">更换头像</button>
                <p class="form-hint">支持 JPG/PNG/GIF，不超过5MB</p>
            </div>
        </div>

        <form id="profileForm" onsubmit="return false;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">昵称</label>
                <input type="text" name="nickname" id="editNickname" class="form-control" value="<?= e($user['nickname']) ?>" maxlength="15" placeholder="3-15 个字节（中文/字母/数字）" oninput="checkNameAvailability(this,'nickname',<?= (int)$user['id'] ?>)" onblur="checkNameAvailability(this,'nickname',<?= (int)$user['id'] ?>)">
                <p class="form-hint name-hint" id="editNicknameHint" style="display:none;margin-top:6px;padding:0;font-size:13px;line-height:1.4;"></p>
            </div>
            <div class="form-group">
                <label class="form-label">个人简介</label>
                <textarea name="bio" class="form-control" data-mention="1" maxlength="500" style="min-height:100px;"><?= e($user['bio']) ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">手机号</label>
                <input type="text" name="phone" class="form-control" value="<?= e($user['phone']) ?>">
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary" id="saveBtn">保存资料</button>
                <a href="<?= url('user/profile', ['id' => $user['id']]) ?>" class="btn btn-ghost">返回</a>
            </div>
        </form>

        <hr style="margin:28px 0;border:none;border-top:1px solid #f0f0f0;">

        <h3 style="font-size:16px;margin-bottom:16px;">邮箱设置</h3>
        <?php
        $evOn = false;
        try { $r = Model::table('settings')->where('key_name','email_verify_enabled')->first(); $evOn = $r && $r['value']=='1'; } catch(Exception $e) {}
        ?>
        <form id="emailForm" onsubmit="return false;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">绑定邮箱 <?= !empty($user['email_verified']) ? '<span class="status-tag success" style="font-size:11px;">已验证</span>' : '<span class="status-tag warning" style="font-size:11px;">未验证</span>' ?></label>
                <input type="email" name="email" class="form-control" placeholder="请输入新邮箱" id="profileEmail">
                <p class="form-hint">原邮箱：<?= e($user['email']) ?></p>
            </div>
            <?php if ($evOn): ?>
            <div class="form-group">
                <label class="form-label">原邮箱验证码</label>
                <div style="display:flex;gap:10px;">
                    <input type="text" name="old_email_code" class="form-control" placeholder="原邮箱收到的验证码" maxlength="6">
                    <button type="button" class="btn btn-ghost" id="sendOldCodeBtn" onclick="sendOldEmailCode()">发送原邮箱验证码</button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">新邮箱验证码</label>
                <div style="display:flex;gap:10px;">
                    <input type="text" name="new_email_code" class="form-control" placeholder="新邮箱收到的验证码" maxlength="6">
                    <button type="button" class="btn btn-ghost" id="sendNewCodeBtn" onclick="sendNewEmailCode()">发送新邮箱验证码</button>
                </div>
                <p class="form-hint">需原邮箱与新邮箱的验证码均通过后，方可修改绑定邮箱</p>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <button type="submit" class="btn btn-primary" id="saveEmailBtn">保存邮箱修改</button>
            </div>
        </form>

        <hr style="margin:28px 0;border:none;border-top:1px solid #f0f0f0;">

        <?php
        // 按后台认证项目组依次展示，各组独立显示认证状态与图标
        $certGroups = [];
        $certGroupStatuses = [];
        // 三态：
        //   $showCertArr = null  → 用户从未设置过 → checkbox 默认按"已通过"勾选（兜底）
        //   $showCertArr = []    → 用户明确清空保存 → checkbox 全部不勾选（尊重用户意图）
        //   $showCertArr = [1,3] → 用户已选 → 按 in_array 判断
        $showCertArr = null;
        try {
            $certGroups = Model::table('certification_groups')->where('status', 1)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->get();
            $certRows = Model::table('certifications')->where('user_id', $user['id'])->get();
            foreach ($certRows as $cr) { $certGroupStatuses[(int)$cr['group_id']] = $cr; }
            // DB 字段为 null 或字符串 'null' 均视为未设置；其余合法 JSON 数组才解码
            if (isset($user['show_cert_badges']) && $user['show_cert_badges'] !== null && $user['show_cert_badges'] !== '') {
                $tmp = json_decode($user['show_cert_badges'], true);
                if (is_array($tmp)) {
                    $showCertArr = array_map('intval', $tmp);
                }
            }
        } catch (Exception $e) {}
        $cgTotal = count($certGroups);
        ?>
        <h3 style="font-size:16px;margin-bottom:6px;">认证图标显示设置</h3>
        <p class="form-hint" style="margin-bottom:16px;">勾选要展示的认证项目（最多 3 个），不勾选则在所有场景都不显示。</p>
        <form id="certBadgesForm" onsubmit="return false;">
            <?= csrf_field() ?>
            <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:8px;">
            <?php foreach ($certGroups as $cg):
                $cgId = (int)$cg['id'];
                $cgCert = $certGroupStatuses[$cgId] ?? null;
                // 兼容老数据：users.is_certified=1 但该 group 下无新表记录时（默认组），视为已通过
                if (!$cgCert && $cgId === 1 && !empty($user['is_certified'])) {
                    $cgCert = ['status' => 1, 'reviewed_at' => $user['certified_at'] ?? null];
                    $certGroupStatuses[1] = $cgCert;
                }
                $cgCertified = $cgCert && (int)$cgCert['status'] === 1;
                // null = 未设置（按已通过默认勾选）；[] = 用户明确清空（不勾选）；[...] = 按用户勾选
                $checked = $showCertArr === null ? $cgCertified : in_array($cgId, $showCertArr, true);
                // 是否永久锁定：未通过认证的组，CSS/JS 上保持灰显不可选
                $isLocked = !$cgCertified ? 1 : 0;
                $disabled = $isLocked ? 'disabled' : '';
            ?>
            <label class="cert-badge-label" style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid #f0f0f0;border-radius:6px;background:<?= $checked && !$disabled ? '#fff7f4' : '#fafafa' ?>;">
                <input type="checkbox" name="show_cert_badges[]" value="<?= $cgId ?>" <?= $checked ? 'checked' : '' ?> <?= $disabled ?> class="cert-badge-checkbox" data-cg-id="<?= $cgId ?>" data-locked="<?= $isLocked ?>">
                <span><?= e($cg['name']) ?></span>
                <?php if (!$cgCertified): ?><span class="form-hint" style="color:#bbb;">（未通过）</span><?php endif; ?>
            </label>
            <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-default" id="saveBadgesBtn" style="margin-top:8px;">保存显示设置</button>
            <p class="form-hint" id="badgeHint" style="margin-top:6px;color:#999;">已选 0 / 最多 3 个</p>
        </form>
        <hr style="margin:24px 0;border:none;border-top:1px solid #f0f0f0;">

        <?php foreach ($certGroups as $cgIndex => $cg):
            $cgId = (int)$cg['id'];
            $cgCert = $certGroupStatuses[$cgId] ?? null;
            // 兼容老数据：users.is_certified=1 但该 group 下无新表记录时（默认组），视为已通过
            if (!$cgCert && $cgId === 1 && !empty($user['is_certified'])) {
                $cgCert = [
                    'status' => 1,
                    'reviewed_at' => $user['certified_at'] ?? null,
                    'created_at' => $user['certified_at'] ?? null,
                    'reject_reason' => null,
                ];
                $certGroupStatuses[1] = $cgCert;
            }
            $cgCertified = $cgCert && (int)$cgCert['status'] === 1;
            $cgCertifying = $cgCert && (int)$cgCert['status'] === 0;
            $cgRejected  = $cgCert && (int)$cgCert['status'] === 2;
            $cgTime = $cgCertified ? ($cgCert['reviewed_at'] ?: ($user['certified_at'] ?? '-')) : '-';
        ?>
        <h3 style="font-size:16px;margin-bottom:16px;"><?= e($cg['name']) ?>认证</h3>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:8px;">
            <div>
                <?php if ($cgCertified): ?>
                    <p style="margin:0 0 6px;color:#52c41a;font-weight:500;"><?= cert_badge_html($user, 18, $cgId) ?> 「<?= e($cg['name']) ?>认证」已通过</p>
                    <p class="form-hint" style="margin:0;">认证时间：<?= e($cgTime) ?></p>
                <?php elseif ($cgCertifying): ?>
                    <p style="margin:0 0 6px;color:#fa8c16;">「<?= e($cg['name']) ?>认证」审核中</p>
                    <p class="form-hint" style="margin:0;">提交时间：<?= e($cgCert['created_at'] ?? '-') ?></p>
                <?php elseif ($cgRejected): ?>
                    <p style="margin:0 0 6px;color:#f5222d;">「<?= e($cg['name']) ?>认证」未通过</p>
                    <p class="form-hint" style="margin:0;">驳回原因：<?= e($cgCert['reject_reason'] ?? '未提供') ?></p>
                <?php else: ?>
                    <p style="margin:0 0 6px;color:#888;">尚未<?= e($cg['name']) ?>认证，<?= e($cg['name']) ?>认证后可解锁更多权益。</p>
                    <p class="form-hint" style="margin:0;">提交真实信息并通过审核后即可获得认证标识。</p>
                <?php endif; ?>
            </div>
            <a href="<?= url('certification/index', ['group_id' => $cgId]) ?>" class="btn btn-primary"><?= $cgCertified ? '查看认证' : '申请认证' ?></a>
        </div>
        <?php if ($cgIndex < $cgTotal - 1): ?>
        <hr style="margin:24px 0;border:none;border-top:1px solid #f0f0f0;">
        <?php endif; ?>
        <?php endforeach; ?>

        <hr style="margin:28px 0;border:none;border-top:1px solid #f0f0f0;">

        <script>
// 昵称唯一性 + 敏感词实时校验（与注册页 checkNameAvailability 同逻辑）
var _nameTimers = {};
function checkNameAvailability(input, field, exceptId) {
    var val = (input.value || '').trim();
    // 优先用 id（最稳定），其次 nextElementSibling，再次 parentNode querySelector
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
        // 用 url(params) 由 url() 内部用 http_build_query 拼成 &r=route&field=...&value=...，
        // 避免字符串拼接把第二个 ? 塞进 r 值导致 $_GET['r'] = 'auth/checkName?field=...'
        var params = { field: field, value: val };
        if (exceptId) params.id = exceptId;
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

// 加权字节计数已移除（取消常驻红色提示；保留后端实时校验 + maxlength 拦截即可）

// 勾选限制：最多 3 个；选满后未勾选的自动 disabled 灰显；取消任何一个后立即重新恢复可勾选（无需刷新页面）。
function updateBadgeHint() {
    var all = document.querySelectorAll('.cert-badge-checkbox');
    var n = 0;
    all.forEach(function (cb) { if (cb.checked) n++; });
    var hint = document.getElementById('badgeHint');
    if (hint) hint.textContent = '已选 ' + n + ' / 最多 3 个';
    all.forEach(function (cb) {
        // 仅"未通过认证"的组（data-locked=1）保持永久禁用；其余的每次都重新评估能否勾选。
        if (cb.dataset && cb.dataset.locked === '1') return;
        cb.disabled = !cb.checked && n >= 3;
    });
}
document.querySelectorAll('.cert-badge-checkbox').forEach(function(cb){
    cb.addEventListener('change', function(){
        var n = 0;
        document.querySelectorAll('.cert-badge-checkbox').forEach(function (x) { if (x.checked) n++; });
        // 极端兜底：DOM 被外部改动导致超过 3 个，回滚并提示
        if (n > 3) { cb.checked = false; n--; toast('最多只能勾选 3 个', 'warning'); }
        updateBadgeHint();
    });
});
updateBadgeHint();
var saveBadgesBtn = document.getElementById('saveBadgesBtn');
if (saveBadgesBtn) saveBadgesBtn.addEventListener('click', function(){
    var checked = [];
    // 用 NodeList 遍历时，对未通过认证的 disabled checkbox 用 :checked 也会被命中；
    // 这里直接在循环里跳过 data-locked=1 的项，保证"保存"的 check[] 永远是用户主动
    // 勾选/取消的结果（不带兜底逻辑）。同时也防 DOM 被注入脏值。
    document.querySelectorAll('.cert-badge-checkbox').forEach(function(cb){
        if (cb.checked && cb.dataset && cb.dataset.locked !== '1') checked.push(String(cb.value));
    });
    if (checked.length > 3) { toast('最多只能勾选 3 个', 'warning'); return; }
    var btn = saveBadgesBtn;
    btn.disabled = true;
    postJSON(url('user/update'), {
        // 即使用户一个都没勾选也强制带上该字段（postJSON 在空对象上仍然会发 JSON 体），
        // 避免 input() 默认值被当成"未提交"走兜底逻辑。
        show_cert_badges: checked,
        _token: document.querySelector('#certBadgesForm input[name="_token"]').value
    }, function(res){
        btn.disabled = false;
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) {
            // 保存后强制刷新当前页：让 server 端 cert_badge_html 用最新 DB 值重新渲染，
            // 立即看到效果；如果不刷新，浏览器缓存的 HTML / Server 的 OPcache 字节码
            // 会让你以为"前端空勾选但后端全显示"。
            setTimeout(function(){ window.location.reload(); }, 500);
        }
    }, function(err){
        btn.disabled = false;
        toast(err && err.message ? err.message : '保存失败', 'error');
    });
});
</script>

        <h3 style="font-size:16px;margin-bottom:16px;">修改密码</h3>
        <form id="passwordForm" onsubmit="return false;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">原密码</label>
                <input type="password" name="old_password" class="form-control" required>
            </div>
            <div class="form-row-inline">
                <div class="form-group">
                    <label class="form-label">新密码</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">确认新密码</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary" id="pwdBtn">修改密码</button>
            </div>
        </form>
        </fieldset>
    </div>
</div>
<script>
function sendOldEmailCode() {
    var email = '<?= e($user['email']) ?>';
    if (!email) { toast('原邮箱为空，无法发送', 'warning'); return; }
    var btn = document.getElementById('sendOldCodeBtn');
    btn.disabled = true;
    postJSON(url('email/sendCode'), {email: email, purpose: 'change_email_old', _token: getCsrfToken()}, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) {
            var n = 60;
            var timer = setInterval(function() { btn.textContent = n + '秒后重发'; n--; if (n < 0) { clearInterval(timer); btn.disabled = false; btn.textContent = '发送原邮箱验证码'; } }, 1000);
        } else { btn.disabled = false; }
    });
}
function sendNewEmailCode() {
    var email = document.getElementById('profileEmail').value.trim();
    if (!email) { toast('请先输入新邮箱', 'warning'); return; }
    var btn = document.getElementById('sendNewCodeBtn');
    btn.disabled = true;
    postJSON(url('email/sendCode'), {email: email, purpose: 'change_email_new', _token: getCsrfToken()}, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) {
            var n = 60;
            var timer = setInterval(function() { btn.textContent = n + '秒后重发'; n--; if (n < 0) { clearInterval(timer); btn.disabled = false; btn.textContent = '发送新邮箱验证码'; } }, 1000);
        } else { btn.disabled = false; }
    });
}
document.getElementById('avatarInput').addEventListener('change', function() {
    if (!this.files[0]) return;
    var fd = new FormData();
    fd.append('avatar', this.files[0]);
    fd.append('_token', document.querySelector('#profileForm input[name="_token"]').value);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', url('user/updateAvatar'));
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function() {
        if (xhr.status === 200) {
            var res = JSON.parse(xhr.responseText);
            if (res.code === 0) {
                var prev = document.getElementById('avatarPreview');
                prev.innerHTML = '<img src="' + (window.location.pathname.replace(/\/index\.php.*/, '') + '/public/' + res.data.avatar) + '" style="width:100%;height:100%;object-fit:cover;">';
                toast('头像已更新', 'success');
            } else toast(res.message || '上传失败', 'error');
        }
    };
    xhr.send(fd);
});
document.getElementById('profileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    // 前端可行性拦截：昵称若实时校验为不可用则阻止提交（后端 update() 仍会兜底）
    var nk = document.getElementById('editNickname');
    if (nk && nk.dataset.nameValid === '0') { toast('昵称不可用，请修改后重试', 'error'); return; }
    var fd = new FormData(this); var data = {};
    fd.forEach(function(v, k) { data[k] = v; });
    postJSON(url('user/update'), data, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
    });
});
document.getElementById('emailForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData(this); var data = {};
    fd.forEach(function(v, k) { data[k] = v; });
    var btn = document.getElementById('saveEmailBtn');
    btn.disabled = true;
    postJSON(url('user/update'), data, function(res) {
        btn.disabled = false;
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) {
            setTimeout(function() { window.location.reload(); }, 600);
        }
    });
});
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData(this); var data = {};
    fd.forEach(function(v, k) { data[k] = v; });
    postJSON(url('user/password'), data, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) this.reset();
    });
});

</script>
