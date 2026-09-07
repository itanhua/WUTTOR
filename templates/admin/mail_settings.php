<?php /** 邮箱设置 */ $title = '邮箱设置'; $site = Config::get('site', []); ?>
<div class="card" style="margin-bottom:16px;">
    <div class="card-header">邮箱验证设置</div>
    <div class="card-body">
        <form id="emailForm" onsubmit="return false;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">是否开启邮箱验证（注册/找回密码/改邮箱）</label>
                <select name="email_verify_enabled" class="form-control" style="width:auto;">
                    <option value="0" <?= ($settings['email_verify_enabled'] ?? 0) == 0 ? 'selected' : '' ?>>关闭</option>
                    <option value="1" <?= ($settings['email_verify_enabled'] ?? 0) == 1 ? 'selected' : '' ?>>开启</option>
                </select>
            </div>
            <div class="form-row-inline">
                <div class="form-group"><label class="form-label">发件人名称</label><input type="text" name="mail_from_name" class="form-control" value="<?= e($settings['mail_from_name'] ?? '') ?>"></div>
                <div class="form-group"><label class="form-label">发件人邮箱</label><input type="email" name="mail_from_email" class="form-control" value="<?= e($settings['mail_from_email'] ?? '') ?>"></div>
            </div>
            <p class="form-hint">不配置 SMTP 时将使用服务器 mail() 函数发信（需服务器支持）；配置 SMTP 使用指定邮箱发送</p>
            <div class="mail-tip" style="margin:6px 0 14px;padding:10px 12px;border-left:3px solid #ea6f5a;background:#fff7f5;border-radius:4px;font-size:12.5px;line-height:1.7;color:#8a4a3a;">
                <b>海外邮箱（Gmail / Outlook / Hotmail 等）收不到验证码？</b><br>
                1. 若 SMTP 用的是 <b>QQ / 163 / 阿里云</b> 等国内邮箱，发往海外的邮件常被直接拒收或进垃圾箱（国内 IP 信誉低）。<b>建议改用对海外友好的事务邮件服务</b>：SendGrid / Amazon SES / Mailgun。<br>
                2. 务必为「发件人邮箱」所用域名在 DNS 上配置 <b>SPF 与 DKIM</b> 解析，否则 Gmail/Outlook 会因校验失败拒收。<br>
                3. 「发件人邮箱」建议与 SMTP 账号<b>同一域名</b>（或留空自动用 SMTP 账号），否则 Gmail 会判伪造而拒收。<br>
                4. 改完可用下方「发送测试邮件」发一封到你的 Gmail，失败会显示具体 SMTP 错误原因（也会记入 php_error.log）。
            </div>
            <div class="form-row-inline">
                <div class="form-group"><label class="form-label">SMTP 主机</label><input type="text" name="smtp_host" class="form-control" value="<?= e($settings['smtp_host'] ?? '') ?>" placeholder="如 smtp.qq.com"></div>
                <div class="form-group"><label class="form-label">SMTP 端口</label><input type="number" name="smtp_port" class="form-control" value="<?= e($settings['smtp_port'] ?? 25) ?>"></div>
            </div>
            <div class="form-row-inline">
                <div class="form-group"><label class="form-label">SMTP 用户名</label><input type="text" name="smtp_user" class="form-control" value="<?= e($settings['smtp_user'] ?? '') ?>"></div>
                <div class="form-group"><label class="form-label">SMTP 密码/授权码</label><input type="password" name="smtp_pass" class="form-control" value="<?= e($settings['smtp_pass'] ?? '') ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">加密方式</label>
                <select name="smtp_secure" class="form-control" style="width:auto;">
                    <option value="" <?= ($settings['smtp_secure'] ?? '') == '' ? 'selected' : '' ?>>无</option>
                    <option value="ssl" <?= ($settings['smtp_secure'] ?? '') == 'ssl' ? 'selected' : '' ?>>SSL</option>
                    <option value="tls" <?= ($settings['smtp_secure'] ?? '') == 'tls' ? 'selected' : '' ?>>TLS</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">保存邮箱设置</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">发送测试邮件</div>
    <div class="card-body">
        <form id="testMailForm" onsubmit="return false;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">测试收件邮箱</label>
                <input type="email" name="to" class="form-control" required placeholder="输入一个可以正常收信的邮箱地址" value="<?= e($settings['mail_from_email'] ?? '') ?>">
                <p class="form-hint">点击下方按钮，将使用当前表单中的 SMTP 配置发送一封测试邮件；发送失败时会显示具体错误原因。</p>
            </div>
            <button type="submit" class="btn btn-primary" id="testMailBtn">发送测试邮件</button>
            <span id="testMailResult" style="margin-left:12px;font-size:13px;"></span>
        </form>
    </div>
</div>
<script>
document.getElementById('emailForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData(this); var data = {};
    fd.forEach(function(v, k) { data[k] = v; });
    postJSON(url('admin/saveMailSettings'), data, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) setTimeout(function() { location.reload(); }, 800);
    });
});
document.getElementById('testMailForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('testMailBtn');
    var result = document.getElementById('testMailResult');
    btn.disabled = true; btn.textContent = '发送中...'; result.textContent = '';
    var fd = new FormData(this); var data = {};
    fd.forEach(function(v, k) { data[k] = v; });
    var ef = document.getElementById('emailForm');
    new FormData(ef).forEach(function(v, k) { data[k] = v; });
    postJSON(url('admin/testMail'), data, function(res) {
        if (res.code === 0) {
            result.textContent = '✓ ' + res.message;
            result.style.color = '#2e7d32';
        } else {
            result.textContent = '✗ ' + (res.message || '发送失败');
            result.style.color = '#d32f2f';
        }
        btn.disabled = false; btn.textContent = '发送测试邮件';
    }, function(res) {
        result.textContent = '✗ ' + ((res && res.message) || '请求失败');
        result.style.color = '#d32f2f';
        btn.disabled = false; btn.textContent = '发送测试邮件';
    });
});
</script>