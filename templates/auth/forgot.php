<?php /** 找回密码页 */ $title = '找回密码'; ?>
<div class="auth-wrap">
    <h2>找回密码</h2>
    <form id="forgotForm" onsubmit="return false;">
        <?= csrf_field() ?>
        <div class="form-group">
            <label class="form-label">注册邮箱 <span class="required">*</span></label>
            <input type="email" name="email" class="form-control" required placeholder="请输入注册时的邮箱" id="forgotEmail">
        </div>
        <div class="form-group">
            <label class="form-label">邮箱验证码 <span class="required">*</span></label>
            <div style="display:flex;gap:10px;">
                <input type="text" name="email_code" class="form-control" required placeholder="请输入验证码" maxlength="6">
                <button type="button" class="btn btn-ghost" id="forgotCodeBtn" onclick="sendForgotCode()">获取验证码</button>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">新密码 <span class="required">*</span></label>
            <input type="password" name="password" class="form-control" required placeholder="至少6位">
        </div>
        <div class="form-group">
            <label class="form-label">确认新密码 <span class="required">*</span></label>
            <input type="password" name="confirm_password" class="form-control" required placeholder="再次输入新密码">
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg" id="forgotBtn">重置密码</button>
        <p class="text-center mt-16 text-muted" style="font-size:13px;">
            想起密码了？<a href="<?= url('auth/login') ?>">返回登录</a>
        </p>
    </form>
</div>
<script>
function sendForgotCode() {
    var email = document.getElementById('forgotEmail').value.trim();
    if (!email) { toast('请先输入邮箱', 'warning'); return; }
    var btn = document.getElementById('forgotCodeBtn');
    btn.disabled = true;
    postJSON(url('email/sendCode'), {email: email, purpose: 'forgot', _token: getCsrfToken()}, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) {
            var n = 60;
            var timer = setInterval(function() {
                btn.textContent = n + '秒后重发'; n--;
                if (n < 0) { clearInterval(timer); btn.disabled = false; btn.textContent = '获取验证码'; }
            }, 1000);
        } else { btn.disabled = false; }
    }, function(res) {
        toast(res.message || '网络错误，请稍后重试', 'error');
        btn.disabled = false;
    });
}
document.getElementById('forgotForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('forgotBtn');
    var fd = new FormData(this); var data = {};
    fd.forEach(function(v, k) { data[k] = v; });
    btn.disabled = true; btn.textContent = '处理中...';
    postJSON(url('auth/doReset'), data, function(res) {
        if (res.code === 0) { toast(res.message, 'success'); setTimeout(function() { window.location.href = res.data.redirect; }, 1000); }
        else { toast(res.message || '重置失败', 'error'); btn.disabled = false; btn.textContent = '重置密码'; }
    }, function(res) { toast(res.message || '网络错误', 'error'); btn.disabled = false; btn.textContent = '重置密码'; });
});
</script>
