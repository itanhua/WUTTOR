<?php
/**
 * 邮箱验证码控制器
 */
class EmailController extends Controller
{
    protected $middleware = [];

    /**
     * 发送验证码（公开接口，根据 purpose 控制权限）
     * POST: email, purpose(register/forgot/change_email)
     */
    public function sendCode()
    {
        csrf_check();
        $email = trim(input('email'));
        $purpose = trim(input('purpose'));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $this->error('邮箱格式不正确');
        if (!in_array($purpose, ['register', 'forgot', 'change_email', 'change_email_old', 'change_email_new'])) $this->error('无效的验证目的');

        // change_email 系列需要登录
        if (in_array($purpose, ['change_email', 'change_email_old', 'change_email_new']) && !Auth::check()) $this->error('请先登录');

        // 场景校验
        if ($purpose === 'register') {
            $exists = Model::table('users')->where('email', $email)->first();
            if ($exists) $this->error('该邮箱已注册');
        } elseif ($purpose === 'forgot') {
            $exists = Model::table('users')->where('email', $email)->first();
            if (!$exists) $this->error('该邮箱未注册');
        } elseif ($purpose === 'change_email') {
            // 新邮箱不能与已有邮箱重复（除自己外）
            $exists = Model::table('users')->where('email', $email)->where('id', '!=', Auth::id())->first();
            if ($exists) $this->error('该邮箱已被其他账号使用');
        } elseif ($purpose === 'change_email_old') {
            // 原邮箱：必须发给当前登录用户的原邮箱
            $u = Auth::user();
            if (empty($u['email'])) $this->error('原邮箱为空，无法发送验证码');
            if ($email !== $u['email']) $this->error('原邮箱不匹配');
        } elseif ($purpose === 'change_email_new') {
            // 新邮箱不能与已有邮箱重复（除自己外）
            $exists = Model::table('users')->where('email', $email)->where('id', '!=', Auth::id())->first();
            if ($exists) $this->error('该邮箱已被其他账号使用');
        }

        // 频率限制：60秒内只能发一次
        $recent = Model::table('email_codes')
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->where('created_at', '>', date('Y-m-d H:i:s', time() - 60))
            ->first();
        if ($recent) $this->error('发送过于频繁，请60秒后再试');

        // 检查系统是否开启邮箱验证
        $enabled = self::getSetting('email_verify_enabled', '0');
        if ($enabled != '1') $this->error('邮箱验证功能未开启');

        // 生成6位验证码
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $now = time();
        $expires = date('Y-m-d H:i:s', $now + 600); // 10分钟有效

        Model::table('email_codes')->insert([
            'email' => $email,
            'code' => $code,
            'purpose' => $purpose,
            'expires_at' => $expires,
            'used' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $subject = '【' . Config::get('site.title', '论坛') . '】验证码';
        $siteTitle = Config::get('site.title', '论坛');
        $bodyMap = [
            'register' => '您的注册验证码为：' . $code . '，10分钟内有效。',
            'forgot' => '您正在找回密码，验证码为：' . $code . '，10分钟内有效。如非本人操作请忽略。',
            'change_email' => '您正在修改绑定邮箱，验证码为：' . $code . '，10分钟内有效。',
            'change_email_old' => '您正在修改 ' . $siteTitle . ' 的绑定邮箱，原邮箱验证码为：' . $code . '，10分钟内有效。如非本人操作请忽略。',
            'change_email_new' => '您正在修改 ' . $siteTitle . ' 的绑定邮箱，新邮箱验证码为：' . $code . '，10分钟内有效。如非本人操作请忽略。',
        ];
        $body = $bodyMap[$purpose] ?? '您的验证码为：' . $code . '，10分钟内有效。';

        $sent = Mailer::send($email, $subject, $body);
        if ($sent !== true) {
            // 透传具体错误（SMTP 配置问题等），方便用户排查
            $msg = is_string($sent) && $sent !== '' ? $sent : '未知错误';
            // 落日志：海外邮箱收不到时，可查 php_error.log 看是 SMTP 报错还是投递被拒
            error_log('[Mail] 验证码发送失败 -> ' . $email . ' : ' . $msg);
            $this->error('邮件发送失败：' . $msg);
        }

        $this->success(null, '验证码已发送至邮箱，请查收');
    }

    /**
     * 校验验证码（静态辅助，供其他控制器调用）
     */
    public static function verifyCode($email, $code, $purpose)
    {
        $record = Model::table('email_codes')
            ->where('email', $email)
            ->where('code', $code)
            ->where('purpose', $purpose)
            ->where('used', 0)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->orderBy('created_at', 'DESC')
            ->first();
        if (!$record) return false;
        Model::table('email_codes')->where('id', $record['id'])->update(['used' => 1]);
        return true;
    }

    public static function getSetting($key, $default = '')
    {
        $row = Model::table('settings')->where('key_name', $key)->first();
        return $row ? $row['value'] : $default;
    }
}
