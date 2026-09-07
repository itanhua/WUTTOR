<?php /** 注册页 */ $title = '注册'; ?>
<?php
// 实时从 settings 表读一次，避免依赖 controller 注入的值（防止注入失败时页面静默）
$_regMode = function_exists('register_mode') ? register_mode() : 'open';
?>
<div class="auth-wrap">
    <h2>注册</h2>
    <div class="auth-tabs">
        <a href="<?= url('auth/login') ?>">登录</a>
        <a href="<?= url('auth/register') ?>" class="active">注册</a>
    </div>

    <?php if (($_regMode) === 'closed'): ?>
        <div style="padding:18px;border:1px solid #f0f0f0;border-radius:8px;background:#fafafa;color:#888;text-align:center;line-height:1.8;">
            站点当前已关闭注册，暂不接受新用户。<br>如有疑问请联系管理员。
        </div>
        <p class="text-center mt-16" style="font-size:13px;">
            已有账号？<a href="<?= url('auth/login') ?>">立即登录</a>
        </p>
    <?php else: ?>
    <form id="registerForm" onsubmit="return false;">
        <?= csrf_field() ?>
        <?php if (($_regMode) === 'invite'): ?>
        <div class="form-group" style="background:#fff7f6;border:1px solid #ffd9d3;border-radius:8px;padding:14px 14px 4px;">
            <label class="form-label" style="color:#ea6f5a;font-weight:600;">
                邀请码 <span class="required">*</span>
                <span style="font-size:12px;font-weight:normal;color:#ea6f5a;margin-left:6px;">必填</span>
            </label>
            <input type="text" name="invite_code" id="inviteCodeInput" class="form-control" required value="<?= e($inviteCode ?? '') ?>" placeholder="请输入邀请码（10 位字母数字）" autocomplete="off" style="letter-spacing:.5px;">
            <?php if (!empty($inviteCode) && empty($inviteValid)): ?>
            <p class="form-hint" style="color:#f5222d;margin-top:6px;">
                <strong>该邀请码无效、已停用、已用完或已过期，</strong>请向邀请人确认后重新填写。
            </p>
            <?php elseif (!empty($inviteValid) && !empty($inviteInviter)): ?>
            <p class="form-hint" style="color:#52c41a;margin-top:6px;">
                ✓ 您正通过 <strong><?= e($inviteInviter) ?></strong> 的邀请注册。
            </p>
            <?php else: ?>
            <p class="form-hint" style="color:#888;margin-top:6px;">
                当前为「邀请注册」模式，未填邀请码或邀请码无效将无法完成注册。
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="form-group">
            <label class="form-label">用户名 <span class="required">*</span></label>
            <input type="text" name="username" id="regUsername" class="form-control" required maxlength="15" placeholder="3-15 个字节（中文/字母/数字）" oninput="checkNameAvailability(this,'username')" onblur="checkNameAvailability(this,'username')">
            <p class="form-hint name-hint" id="regUsernameHint" style="display:none;margin-top:6px;padding:0;font-size:13px;line-height:1.4;"></p>
        </div>
        <div class="form-group">
            <label class="form-label">昵称</label>
            <input type="text" name="nickname" id="regNickname" class="form-control" maxlength="15" placeholder="3-15 个字节，留空则使用用户名" oninput="checkNameAvailability(this,'nickname')" onblur="checkNameAvailability(this,'nickname')">
            <p class="form-hint name-hint" id="regNicknameHint" style="display:none;margin-top:6px;padding:0;font-size:13px;line-height:1.4;"></p>
        </div>
        <div class="form-group">
            <label class="form-label">邮箱 <span class="required">*</span></label>
            <input type="email" name="email" class="form-control" required placeholder="用于找回密码" id="regEmail">
        </div>
        <?php
        $emailVerifyOn = false;
        try { $r = Model::table('settings')->where('key_name','email_verify_enabled')->first(); $emailVerifyOn = $r && $r['value']=='1'; } catch(Exception $e) {}
        if ($emailVerifyOn):
        ?>
        <div class="form-group">
            <label class="form-label">邮箱验证码 <span class="required">*</span></label>
            <div style="display:flex;gap:10px;">
                <input type="text" name="email_code" class="form-control" required placeholder="请输入验证码" maxlength="6">
                <button type="button" class="btn btn-ghost" id="sendCodeBtn" onclick="sendRegCode()">获取验证码</button>
            </div>
        </div>
        <?php endif; ?>
        <div class="form-group">
            <label class="form-label">密码 <span class="required">*</span></label>
            <input type="password" name="password" class="form-control" required placeholder="至少6位">
        </div>
        <div class="form-group">
            <label class="form-label">确认密码 <span class="required">*</span></label>
            <input type="password" name="confirm_password" class="form-control" required placeholder="再次输入密码">
        </div>
        <?php if (function_exists('captcha_scene_on') && captcha_scene_on('register')): ?>
        <div class="form-group">
            <div id="captchaMountRegister" class="captcha-mount"></div>
        </div>
        <script>
        // 页面加载即渲染验证码（确认已生效、可见），提交时复用同一实例
        document.addEventListener('DOMContentLoaded', function () {
            var m = document.getElementById('captchaMountRegister');
            if (m && window.Captcha) Captcha.ensure('register', m);
        });
        </script>
        <?php endif; ?>
        <script src="<?= asset('js/captcha.js') ?>"></script>
        <button type="submit" class="btn btn-primary btn-block btn-lg" id="registerBtn">注册</button>
        <p class="text-center mt-16 text-muted" style="font-size:13px;">
            已有账号？<a href="<?= url('auth/login') ?>">立即登录</a> · <a href="<?= url('auth/forgot') ?>">忘记密码</a>
        </p>
    </form>
    <?php endif; ?>
</div>
<?php if (($_regMode) !== 'closed'): ?>
<script>
// 从 URL 的 ?code= 自动预填邀请码（覆盖微信/QQ/邮件里点开链接时 PHP 端 $_GET 可能丢失的极端情况）
(function () {
    var m = /[?&]code=([^&]+)/.exec(window.location.search);
    if (!m) return;
    var code = decodeURIComponent(m[1]);
    function fill() {
        var input = document.getElementById('inviteCodeInput');
        if (!input) return false;
        input.value = code;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    }
    if (!fill()) {
        document.addEventListener('DOMContentLoaded', fill);
    }
})();

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
            // 优先按 JSON 解析（即便 r.ok=false，PHP 异常时也会返回 JSON 500 含 message 字段）
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
                // PHP 端返回了 code != 0 的 JSON 错误（如 catch 分支的「校验服务暂不可用」）
                hint.style.color = '#f5222d';
                hint.textContent = '✗ ' + out.res.message;
                input.style.borderColor = '#f5222d';
                // 后端说"暂不可用"时，不要把 input 标为不可用，避免用户卡住
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
        }).catch(function(err) {
            hint.style.color = '#f5222d';
            hint.textContent = '✗ 网络错误，请稍后重试';
            input.style.borderColor = '#f5222d';
        });
    }, 350);
}

// 加权字节计数已移除（取消常驻红色提示；保留后端实时校验 + maxlength 拦截即可）

function sendRegCode() {
    var email = document.getElementById('regEmail').value.trim();
    if (!email) { toast('请先输入邮箱', 'warning'); return; }
    var btn = document.getElementById('sendCodeBtn');
    btn.disabled = true;
    postJSON(url('email/sendCode'), {email: email, purpose: 'register', _token: getCsrfToken()}, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) {
            var n = 60;
            var timer = setInterval(function() {
                btn.textContent = n + '秒后重发';
                n--;
                if (n < 0) { clearInterval(timer); btn.disabled = false; btn.textContent = '获取验证码'; }
            }, 1000);
        } else { btn.disabled = false; }
    }, function(res) {
        toast(res.message || '网络错误，请稍后重试', 'error');
        btn.disabled = false;
    });
}
document.getElementById('registerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var un = document.getElementById('regUsername');
    var nk = document.getElementById('regNickname');
    if (un && un.dataset.nameValid === '0') { toast('用户名不可用，请修改后重试', 'error'); return; }
    if (nk && nk.dataset.nameValid === '0') { toast('昵称不可用，请修改后重试', 'error'); return; }
    var btn = document.getElementById('registerBtn');
    btn.disabled = true;
    btn.textContent = '注册中...';
    var mount = document.getElementById('captchaMountRegister');
    Captcha.ensure('register', mount).then(function(sol) {
        var fd = new FormData(document.getElementById('registerForm'));
        var data = {};
        fd.forEach(function(v, k) { data[k] = v; });
        if (sol) { data.captcha_token = sol.token; data.captcha_x = sol.x; }
        postJSON(url('auth/doRegister'), data, function(res) {
            if (res.code === 0) {
                toast('注册成功，欢迎加入', 'success');
                setTimeout(function() { window.location.href = res.data.redirect; }, 800);
            } else {
                toast(res.message || '注册失败', 'error');
                btn.disabled = false;
                btn.textContent = '注册';
            }
        }, function(res) {
            toast(res.message || '网络错误', 'error');
            btn.disabled = false;
            btn.textContent = '注册';
        });
    }).catch(function(msg) {
        toast(msg || '请完成滑块验证', 'error');
        btn.disabled = false;
        btn.textContent = '注册';
        // 后端校验失败（如用户名被占用）后，清空 solved 标记，允许用户重新拖动验证
        if (mount) { mount.removeAttribute('data-solved'); mount._capPromise = null; }
    });
});
</script>
<?php endif; ?>
