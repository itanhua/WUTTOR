<?php /** 登录页 */ $title = '登录'; ?>
<div class="auth-wrap">
    <h2>登录</h2>
    <div class="auth-tabs">
        <a href="<?= url('auth/login') ?>" class="active">登录</a>
        <a href="<?= url('auth/register') ?>">注册</a>
    </div>
    <form id="loginForm" onsubmit="return false;">
        <?= csrf_field() ?>
        <?php if (!empty($redirectUrl)): ?>
        <input type="hidden" name="redirect" value="<?= e($redirectUrl) ?>">
        <?php endif; ?>
        <div class="form-group">
            <label class="form-label">用户名 / 邮箱</label>
            <input type="text" name="account" class="form-control" required placeholder="请输入用户名或邮箱">
        </div>
        <div class="form-group">
            <label class="form-label">密码</label>
            <input type="password" name="password" class="form-control" required placeholder="请输入密码">
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
            <label class="form-label" style="margin:0;font-weight:normal;cursor:pointer;display:flex;align-items:center;gap:6px;">
                <input type="checkbox" name="remember" value="1"> 记住我（30 天内免登录）
            </label>
        </div>
        <?php if (function_exists('captcha_scene_on') && captcha_scene_on('login')): ?>
        <div class="form-group">
            <div id="captchaMountLogin" class="captcha-mount"></div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var m = document.getElementById('captchaMountLogin');
            if (m && window.Captcha) Captcha.ensure('login', m);
        });
        </script>
        <?php endif; ?>
        <script src="<?= asset('js/captcha.js') ?>"></script>
        <button type="submit" class="btn btn-primary btn-block btn-lg" id="loginBtn">登录</button>
        <p class="text-center mt-16 text-muted" style="font-size:13px;">
            还没有账号？<a href="<?= url('auth/register') ?>">立即注册</a> · <a href="<?= url('auth/forgot') ?>">忘记密码</a>
        </p>
    </form>
</div>
<script>
document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('loginBtn');
    btn.disabled = true;
    btn.textContent = '登录中...';
    var mount = document.getElementById('captchaMountLogin');
    Captcha.ensure('login', mount).then(function(sol) {
        var fd = new FormData(document.getElementById('loginForm'));
        var data = {};
        fd.forEach(function(v, k) { data[k] = v; });
        // 兜底：hidden 没渲染时（用户从公开页点右上角「登录」进来，URL 上没有 ?redirect=，
        // 后端也就没渲染 hidden），用 document.referrer 补上——它就是用户点登录前的那一页。
        // 只接受同源、且不是认证页自身的 referrer，避免死循环弹回登录页。
        if (!data.redirect) {
            try {
                var ref = document.referrer;
                if (ref && ref.indexOf(location.origin) === 0) {
                    var u = new URL(ref);
                    if (!/^\/auth\/(login|register|logout|doLogin|doRegister)$/.test(u.pathname)) {
                        data.redirect = u.pathname + u.search;
                    }
                }
            } catch (err) { /* referrer 异常则忽略，交给后端 session 兜底 */ }
        }
        if (sol) { data.captcha_token = sol.token; data.captcha_x = sol.x; }
        postJSON(url('auth/doLogin'), data, function(res) {
            if (res.code === 0) {
                toast('登录成功', 'success');
                setTimeout(function() { window.location.href = res.data.redirect; }, 600);
            } else {
                toast(res.message || '登录失败', 'error');
                btn.disabled = false;
                btn.textContent = '登录';
            }
        }, function(res) {
            toast(res.message || '网络错误', 'error');
            btn.disabled = false;
            btn.textContent = '登录';
        });
    }).catch(function(msg) {
        toast(msg || '请完成滑块验证', 'error');
        btn.disabled = false;
        btn.textContent = '登录';
        if (mount) { mount.removeAttribute('data-solved'); mount._capPromise = null; }
    });
});
</script>
