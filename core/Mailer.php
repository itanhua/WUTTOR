<?php
/**
 * 邮件发送类（SMTP 完整实现 + mail() 回退）
 * send()/sendWith() 成功返回 true，失败返回错误字符串（方便前端展示与日志记录）
 */
class Mailer
{
    /**
     * 发送邮件（读取数据库中的 SMTP 配置）
     */
    public static function send($to, $subject, $body)
    {
        return self::sendWith($to, $subject, $body, self::getSettings());
    }

    /**
     * 发送邮件（使用指定配置，供后台"测试发送"使用）
     */
    public static function sendWith($to, $subject, $body, $settings)
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return '收件邮箱格式不正确';

        $fromName = trim($settings['mail_from_name'] ?? '');
        if ($fromName === '') $fromName = Config::get('site.title', '论坛');

        // 发件邮箱：优先用户配置；配置了 SMTP 认证账号时，若未单独填发件邮箱则用认证账号
        $fromEmail = trim($settings['mail_from_email'] ?? '');
        $smtpUser = trim($settings['smtp_user'] ?? '');
        if ($fromEmail === '') {
            if (!empty($smtpUser) && filter_var($smtpUser, FILTER_VALIDATE_EMAIL)) {
                $fromEmail = $smtpUser;
            } else {
                $fromEmail = 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            }
        }

        // 信封发件人（MAIL FROM）：QQ/163 等邮箱强制要求与 AUTH 认证账号一致，否则返回 553
        $envelopeFrom = (!empty($smtpUser) && filter_var($smtpUser, FILTER_VALIDATE_EMAIL)) ? $smtpUser : $fromEmail;

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . self::encodeName($fromName) . ' <' . $fromEmail . '>';
        $headers[] = 'Reply-To: ' . $fromEmail;
        $headers[] = 'X-Mailer: PHP/' . phpversion();

        // —— 以下标准头对海外邮箱（Gmail / Outlook）送达率至关重要 ——
        // 缺失会被收件方判为垃圾或直接拒收。
        $headers[] = 'Date: ' . date('r');
        // Message-ID 的域尽量与发件人邮箱域名一致，增强可信度
        $midDomain = (($at = strrpos($fromEmail, '@')) !== false)
            ? substr($fromEmail, $at + 1)
            : ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $headers[] = 'Message-ID: <' . uniqid('', true) . '.' . bin2hex(random_bytes(8)) . '@' . $midDomain . '>';
        // 信封发件人（Return-Path）应与 SMTP 认证账号一致，避免被收件方判为伪造
        $headers[] = 'Return-Path: <' . $envelopeFrom . '>';

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:sans-serif;background:#f5f5f5;padding:20px;">';
        $html .= '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:8px;padding:32px;">';
        $html .= '<h2 style="color:#ea6f5a;margin:0 0 16px;">' . e($fromName) . '</h2>';
        $html .= '<p style="color:#333;font-size:14px;line-height:1.8;">' . nl2br(e($body)) . '</p>';
        $html .= '<hr style="border:none;border-top:1px solid #eee;margin:24px 0;">';
        $html .= '<p style="color:#999;font-size:12px;">此邮件由系统自动发送，请勿直接回复。<br>若非本人操作，请忽略此邮件。</p>';
        $html .= '</div></body></html>';

        $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        // 配置了 SMTP 则走 SMTP，否则回退 mail()
        if (!empty($settings['smtp_host'])) {
            return self::sendSmtp($to, $subject, $html, $headers, $settings, $envelopeFrom, $fromName);
        }

        // mail() 回退
        if (!function_exists('mail')) return '服务器未启用 mail() 函数，请在后台配置 SMTP';
        $ok = @mail($to, $subject, $html, implode("\r\n", $headers));
        if (!$ok) return '服务器 mail() 函数发送失败（多数虚拟主机未配置本地邮件服务），请在后台配置 SMTP 发件邮箱';
        return true;
    }

    /**
     * SMTP 发送（逐步校验响应码，任何一步失败立即返回具体错误）
     */
    protected static function sendSmtp($to, $subject, $body, $headers, $settings, $envelopeFrom, $fromName)
    {
        $host = trim($settings['smtp_host']);
        $port = (int)($settings['smtp_port'] ?? 25);
        $user = trim($settings['smtp_user'] ?? '');
        $pass = (string)($settings['smtp_pass'] ?? '');
        $secure = trim($settings['smtp_secure'] ?? ''); // ssl / tls / 空

        // ssl 模式默认端口 465，tls/无 默认 587/25 的常见约定
        if (($settings['smtp_port'] ?? '') === '' || (int)($settings['smtp_port'] ?? 0) === 0) {
            $port = ($secure === 'ssl') ? 465 : (($secure === 'tls') ? 587 : 25);
        }

        // 建立连接
        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            if ($secure === 'ssl' && !extension_loaded('openssl')) {
                return 'SSL 连接需要 PHP 的 openssl 扩展，请先开启该扩展，或改用 TLS / 无加密';
            }
            return 'SMTP 连接失败：' . ($errstr ?: '无法连接 ' . $host . ':' . $port) . '（' . $errno . '）';
        }
        stream_set_timeout($fp, 15);

        // 读取一行（含多行响应拼接），并校验期望响应码
        $readResp = function () use ($fp) {
            $lines = [];
            while (($line = fgets($fp, 515)) !== false) {
                $line = rtrim($line, "\r\n");
                $lines[] = $line;
                // 多行响应：第 4 个字符为 '-' 表示还有后续行
                if (strlen($line) < 4 || $line[3] !== '-') break;
            }
            return implode("\n", $lines);
        };

        $step = function ($resp, $code, $label) {
            if (!preg_match('/^' . $code . '/', $resp)) {
                return $label . '失败：' . $resp;
            }
            return null;
        };

        // 1. 服务器问候
        $resp = $readResp();
        if (($err = $step($resp, '220', 'SMTP 服务器握手'))) return $err;

        // EHLO 主机名：优先用服务器真实主机名（FQDN），缺失再回退 HTTP_HOST / localhost。
        // 部分收件服务器对 EHLO localhost 会降权，真实主机名更利于海外送达。
        $ehloHost = gethostname();
        if (!$ehloHost || $ehloHost === 'localhost' || $ehloHost === '') {
            $ehloHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        }

        // 2. EHLO
        fwrite($fp, "EHLO {$ehloHost}\r\n");
        $resp = $readResp();
        if (($err = $step($resp, '250', 'EHLO 握手'))) return $err;

        // 3. STARTTLS（可选）
        if ($secure === 'tls') {
            fwrite($fp, "STARTTLS\r\n");
            $resp = $readResp();
            if (($err = $step($resp, '220', 'STARTTLS 协商'))) return $err;
            if (!extension_loaded('openssl')) return 'TLS 加密需要 PHP 的 openssl 扩展，请先开启该扩展，或改用 SSL / 无加密';
            $crypto = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto) return 'STARTTLS 加密握手失败，请检查端口与加密方式是否匹配';
            fwrite($fp, "EHLO {$ehloHost}\r\n");
            $resp = $readResp();
            if (($err = $step($resp, '250', 'EHLO(TLS)'))) return $err;
        }

        // 4. 认证（配置了用户名才认证）
        if ($user !== '') {
            fwrite($fp, "AUTH LOGIN\r\n");
            $resp = $readResp();
            if (($err = $step($resp, '334', 'SMTP 认证'))) return $err;
            fwrite($fp, base64_encode($user) . "\r\n");
            $resp = $readResp();
            if (($err = $step($resp, '334', 'SMTP 认证(用户名)'))) return $err;
            fwrite($fp, base64_encode($pass) . "\r\n");
            $resp = $readResp();
            if (($err = $step($resp, '235', 'SMTP 认证'))) return $err . '（请检查用户名/授权码是否正确）';
        }

        // 5. MAIL FROM（使用认证账号作为信封发件人，QQ/163 强制要求与 AUTH 一致）
        fwrite($fp, "MAIL FROM:<{$envelopeFrom}>\r\n");
        $resp = $readResp();
        if (($err = $step($resp, '250', 'MAIL FROM'))) {
            return $err . '（QQ/163 等邮箱要求发件人必须是认证账号本身，请检查"SMTP 用户名"与邮箱是否一致）';
        }

        // 6. RCPT TO
        fwrite($fp, "RCPT TO:<{$to}>\r\n");
        $resp = $readResp();
        if (($err = $step($resp, '250', 'RCPT TO'))) return $err;

        // 7. DATA 正文
        fwrite($fp, "DATA\r\n");
        $resp = $readResp();
        if (($err = $step($resp, '354', 'DATA'))) return $err;

        $data = "Subject: {$subject}\r\nTo: {$to}\r\n" . implode("\r\n", $headers) . "\r\n\r\n" . $body;
        // SMTP 点填充：正文行首的 "." 需转义为 ".."
        $data = preg_replace('/^\./m', '..', $data);
        fwrite($fp, $data . "\r\n.\r\n");
        $resp = $readResp();
        if (($err = $step($resp, '250', '邮件投递'))) return $err;

        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    }

    /**
     * 读取数据库中的邮件配置
     */
    protected static function getSettings()
    {
        $rows = Model::query("SELECT key_name, value FROM settings");
        $map = [];
        foreach ($rows as $r) $map[$r['key_name']] = $r['value'];
        return $map;
    }

    protected static function encodeName($name)
    {
        return '=?UTF-8?B?' . base64_encode($name) . '?=';
    }
}
