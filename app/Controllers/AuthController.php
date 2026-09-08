<?php
/**
 * 认证授权控制器
 */
class AuthController extends Controller
{
    protected $middleware = [
        ['middleware' => 'guest', 'only' => ['login', 'register', 'doLogin', 'doRegister', 'forgot']],
        ['middleware' => 'csrf', 'only' => ['doLogin', 'doRegister', 'doReset']],
        ['middleware' => 'auth', 'only' => ['logout']],
    ];

    /**
     * 登录页
     */
    public function login()
    {
        View::setLayout('auth');
        // 回跳目标优先级：URL 上的 ?redirect= → HTTP_REFERER（站点内页面）→ 空（回首页）
        //
        // ✦ 为什么必须有 referrer 兜底：
        //   中间件（core/Controller.php 'auth'）跳转时才会在 URL 上挂 ?redirect=；
        //   但用户最常见的路径是「浏览某公开页 → 点右上角『登录』」，而这个链接是裸的
        //   /auth/login，URL 上根本没有 redirect —— 于是 hidden 不渲染、doLogin 拿不到值，
        //   登录后只能回首页。用 referrer 兜底才能让这种场景也回到登录前那一页。
        $rawRedirect = $_GET['redirect'] ?? $_POST['redirect'] ?? json_input()['redirect'] ?? '';
        $redirectUrl = $this->validateRedirect($rawRedirect);
        if ($redirectUrl === '') {
            $redirectUrl = $this->safeReferer();
        }
        // 存进 session：doLogin 是 ajax POST 到干净的 /auth/doLogin，
        // 那时 URL 上没有 redirect、referrer 又变成登录页自身，只有 session 还能兜住。
        // 每次进入登录页都无条件刷新（没目标就删），避免残留几天前的旧跳转目标。
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        if ($redirectUrl !== '') {
            $_SESSION['login_redirect'] = $redirectUrl;
        } else {
            unset($_SESSION['login_redirect']);
        }
        $this->view('auth/login', ['redirectUrl' => $redirectUrl]);
    }

    /**
     * 取「安全」的 HTTP_REFERER 作为回跳目标。
     * 仅当 referrer 是本站、且不是登录/注册/登出等认证页自身时返回（避免死循环回跳登录页）。
     * 任何不满足条件的情况都返回空字符串，由调用方回落到首页。
     */
    private function safeReferer()
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref === '') return '';
        $parts = @parse_url($ref);
        if (empty($parts['host']) || empty($parts['path'])) return '';
        // 必须同 host（含端口），防止开放重定向
        $reqHost = $_SERVER['HTTP_HOST'] ?? '';
        $refHost = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (strcasecmp($refHost, $reqHost) !== 0) return '';
        $path = $parts['path'];
        // 排除认证相关路径，避免登录后又被弹回登录页
        if (preg_match('#/(auth/|login|logout|register)#i', $path)) return '';
        return $this->validateRedirect($path . (isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : ''));
    }

    /**
     * 注册页
     */
    public function register()
    {
        View::setLayout('auth');
        $mode = register_mode();
        // 显式读取（不依赖 input() 抽象），确保从 ?code= 这种 URL 参数一定能进入
        $rawCode = $_GET['code'] ?? $_POST['code'] ?? json_input()['code'] ?? '';
        $inviteCode = trim((string)$rawCode);
        $inviteValid = false;
        $inviteInviter = '';
        if ($mode === 'invite' && $inviteCode !== '') {
            $inv = Model::table('invites')->where('code', $inviteCode)->first();
            $now = date('Y-m-d H:i:s');
            if ($inv && (int)$inv['status'] === 1 && (int)$inv['used_count'] < (int)$inv['max_uses']
                && (empty($inv['expires_at']) || $inv['expires_at'] >= $now)) {
                $inviteValid = true;
                $inviter = Model::table('users')->where('id', (int)$inv['inviter_id'])->first();
                $inviteInviter = $inviter ? ($inviter['nickname'] ?: $inviter['username']) : '';
            }
        }
        $this->view('auth/register', [
            'registerMode'  => $mode,
            'inviteCode'    => $inviteCode,
            'inviteValid'   => $inviteValid,
            'inviteInviter' => $inviteInviter,
        ]);
    }

    /**
     * 执行登录
     */
    public function doLogin()
    {
        csrf_check();
        // 滑块验证码校验（后台「系统管理 → 验证码」开启登录场景时生效）
        list($capOk, $capMsg) = captcha_guard('login');
        if (!$capOk) { $this->error($capMsg); }
        $account = trim(input('account'));
        $password = input('password');

        if (!$account || !$password) {
            $this->error('请输入账号和密码');
        }

        // 支持用户名 / 邮箱 / UID 登录（UID 需为纯数字，避免把字母账号误当成 id）
        $pdo = Database::pdo();
        if (is_numeric($account) && (int)$account > 0) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$account]);
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1');
            $stmt->execute([$account, $account]);
        }
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->error('账号或密码错误');
        }

        Auth::login($user['id']);
        // 勾选「记住我」时签发持久化登录 token（重启浏览器后仍保持登录）
        if (input('remember')) {
            Auth::issueRememberToken($user['id']);
        }
        // 登录前页面回跳，四级兜底，任何一环断掉都不会"擅自回首页"：
        //   1. POST/JSON 表单里的 hidden redirect（模板正常渲染时走这条）
        //   2. session（登录页渲染时存下的，最可靠——不依赖前端，ajax POST 也带得住）
        //   3. URL 上的 ?redirect=（非 ajax 直连提交时才可能有）
        //   4. 首页
        // —— 注意：ajax POST 到 /auth/doLogin 时 URL 上没有 redirect，
        //    且 referrer 已变成登录页自身，所以 session 是唯一稳定的兜底。
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        $rawRedirect = (string)input('redirect', '');
        $back = $this->validateRedirect($rawRedirect);
        $source = 'post';
        if ($back === '' && !empty($_SESSION['login_redirect'])) {
            $back = $this->validateRedirect((string)$_SESSION['login_redirect']);
            $source = 'session';
        }
        if ($back === '') {
            $back = $this->validateRedirect((string)($_GET['redirect'] ?? ''));
            $source = 'get';
        }
        if ($back === '') { $source = 'home'; }
        // 用掉即清，避免下次登录被上次的旧目标带跑
        unset($_SESSION['login_redirect']);
        // 调试日志：抓 redirect 丢失/被拒的真实原因，部署后查看 storage/logs/login_redirect.log
        @file_put_contents(
            STORAGE_PATH . '/logs/login_redirect.log',
            '[' . date('Y-m-d H:i:s') . '] raw=' . json_encode($rawRedirect) . ' -> back=' . json_encode($back) . ' (src=' . $source . ' ref=' . ($_SERVER['HTTP_REFERER'] ?? '') . ' req=' . ($_SERVER['REQUEST_URI'] ?? '?') . ' ip=' . ($_SERVER['REMOTE_ADDR'] ?? '?') . ')' . PHP_EOL,
            FILE_APPEND
        );
        $this->success(['redirect' => $back ?: url('home/index')], '登录成功');
    }

    /**
     * 校验回跳 redirect 是否同站且安全
     * - 以单斜杠 / 开头的相对路径：直接接受；
     * - 以 // 开头的 protocol-relative URL（如 //evil.com/x）：拒绝，防止跳出；
     * - 绝对 URL：host 必须与当前 REQUEST 的 host 相同，否则拒绝。
     * 注意：单引号字符串里 \\ 解析为一个 \ 字面字符；字符类里 \\ 表示一个字面 \。
     */
    private function validateRedirect($val)
    {
        if (!is_string($val)) return '';
        $val = trim($val);
        if ($val === '') return '';
        if (strlen($val) > 1024) return ''; // 防止被滥用为存储型 XSS / 长 URL 攻击
        // 相对路径：以 / 开头（非 // —— 阻止 protocol-relative）
        if ($val[0] === '/') {
            if (isset($val[1]) && $val[1] === '/') return '';
            return $val;
        }
        // 绝对 URL：必须 http(s) 且同 host
        if (preg_match('#^https?://([^/?#]+)([^?#]*)$#i', $val, $m)) {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if ($host !== '' && strtolower($m[1]) === strtolower($host)) {
                return $m[2] === '' ? '/' : $m[2];
            }
        }
        return '';
    }

    /**
     * 执行注册
     */
    public function doRegister()
    {
        csrf_check();
        // 滑块验证码校验（后台「系统管理 → 验证码」开启注册场景时生效）
        list($capOk, $capMsg) = captcha_guard('register');
        if (!$capOk) { $this->error($capMsg); }
        $username = trim(input('username'));
        $email = trim(input('email'));
        $password = input('password');
        $confirm = input('confirm_password');
        $nickname = trim(input('nickname'));
        if ($nickname === '') $nickname = $username;

        // 注册模式控制
        $mode = register_mode();
        $invite = null;
        if ($mode === 'closed') {
            $this->error('站点已关闭注册');
        }
        if ($mode === 'invite') {
            $code = trim((string)input('invite_code', ''));
            if ($code === '') $this->error('请输入邀请码');
            $invite = Model::table('invites')->where('code', $code)->first();
            $now = date('Y-m-d H:i:s');
            if (!$invite) $this->error('邀请码不存在');
            if ((int)$invite['status'] !== 1) $this->error('邀请码已停用');
            if ((int)$invite['used_count'] >= (int)$invite['max_uses']) $this->error('邀请码已达使用上限');
            if (!empty($invite['expires_at']) && $invite['expires_at'] < $now) $this->error('邀请码已过期');
        }

        // 校验
        // ⚠️ 长度按「加权字节数」计：1 汉字=2 字节；1 字母/数字=1 字节（name_strlen）。
        //    历史上用 mb_strlen 把 1 汉字算 1 字节太松；用 strlen 数字节 1 汉字=3 字节又会
        //    把「2 个汉字(6 字节)」错判为「3 字节通过下限」。
        if (name_strlen($username) < 3 || name_strlen($username) > 15) {
            $this->error('用户名长度需为 3-15 个字节');
        }
        if (!is_valid_name_chars($username)) {
            $this->error('用户名只能包含中文、字母和数字');
        }
        if (is_pure_number($username)) {
            $this->error('用户名不可为纯数字');
        }
        if (has_sensitive($username, 'all', 'exact')) {
            $this->error('用户名含敏感词/限制词');
        }
        // 昵称同样按加权字节数限制 3-15（留空时已回落为用户名，规则天然一致）
        if (name_strlen($nickname) < 3 || name_strlen($nickname) > 15) {
            $this->error('昵称长度需为 3-15 个字节');
        }
        if (!is_valid_name_chars($nickname)) {
            $this->error('昵称只能包含中文、字母和数字');
        }
        if (is_pure_number($nickname)) {
            $this->error('昵称不可为纯数字');
        }
        if (has_sensitive($nickname, 'all', 'exact')) {
            $this->error('昵称含敏感词/限制词');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('邮箱格式不正确');
        }
        if (strlen($password) < 6) {
            $this->error('密码至少 6 位');
        }
        if ($password !== $confirm) {
            $this->error('两次输入的密码不一致');
        }

        // 查重
        $exists = Model::table('users')->where('username', $username)->first();
        if ($exists) $this->error('用户名已被占用');
        $exists = Model::table('users')->where('nickname', $nickname)->first();
        if ($exists) $this->error('昵称已被占用');
        $exists = Model::table('users')->where('email', $email)->first();
        if ($exists) $this->error('邮箱已被注册');

        // 邮箱验证码校验（若开启）
        $emailVerified = 0;
        $emailVerifyOn = EmailController::getSetting('email_verify_enabled', '0') == '1';
        if ($emailVerifyOn) {
            $emailCode = trim(input('email_code'));
            if (!$emailCode) $this->error('请输入邮箱验证码');
            if (!EmailController::verifyCode($email, $emailCode, 'register')) {
                $this->error('邮箱验证码错误或已过期');
            }
            $emailVerified = 1;
        }

        $now = date('Y-m-d H:i:s');

        // —— 新注册用户 uid 规则 ——
        // 以当前所有用户的最大 uid 为基准 +1（不查空、只取 MAX，不复用被删除的 uid）。
        // 这样即便手动把某个存量用户的 uid 改大，新注册也会顺着 +1，编号不会回退/冲突，
        // 便于后期为"预留 uid"做编号价值提升。
        $userData = [
            'username' => $username,
            'email' => $email,
            'email_verified' => $emailVerified,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'nickname' => $nickname,
            'role' => 'user',
            'is_certified' => 0,
            'status' => 1,
            'invited_by' => $invite ? (int)$invite['inviter_id'] : null,
            'invite_code' => $invite ? $invite['code'] : null,
            'last_login_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $userId = allocate_user_id($userData);

        // 积分：新用户注册奖励 Token + Byte（幂等，source_id = 新用户 id；规则停用/缺失自动跳过）
        PointService::earnAction($userId, 'register', $userId, '新用户注册');

        // 邀请码：绑定新用户并增加已使用次数
        if ($invite) {
            Model::table('invites')->where('id', (int)$invite['id'])->update([
                'used_count' => (int)$invite['used_count'] + 1,
                'updated_at' => $now,
            ]);
            // 积分：邀请人获得「邀请成功」奖励（幂等，source_id = 新用户 id）
            PointService::earnAction((int)$invite['inviter_id'], 'invite_success', $userId, '邀请用户注册成功');
        }

        Auth::login($userId);
        $this->success(['redirect' => url('home/index')], '注册成功，欢迎加入');
    }

    /**
     * 检查用户名/昵称可用性（前端实时校验用）
     * 只读接口：无需登录、无需 CSRF
     */
    public function checkName()
    {
        $resp = function ($available, $reason) {
            return ['code' => 0, 'data' => ['available' => $available, 'reason' => $reason]];
        };
        // 整个函数体包 try/catch：任何子调用（DB / has_sensitive / 查重）抛错都不应让接口返回 500，
        // 而是降级为"校验服务暂不可用"（available=true 让用户能继续注册），同时把异常信息写入
        // php_error.log 供后续排障；这样即便真实根因未修，注册流程也不会被阻断。
        try {
        $field = trim((string)input('field', ''));
        $value = trim((string)input('value', ''));
        $exceptIdRaw = input('id');
        $exceptId = null;
        if ($exceptIdRaw !== null && $exceptIdRaw !== '' && (int)$exceptIdRaw > 0) {
            $exceptId = (int)$exceptIdRaw;
        }
        // loose=1 时：跳过 username 长度校验（用于后台编辑用户场景，其它规则仍生效）。
        // 其他场景（注册 / 个人资料）默认不传 loose，保持 3-15 长度约束不变。
        $looseRaw = strtolower(trim((string)input('loose', '')));
        $loose = in_array($looseRaw, ['1', 'true', 'yes', 'on'], true);
        if (!in_array($field, ['username', 'nickname'], true)) {
            $this->json($resp(false, '字段不支持'));
            return;
        }
        if ($value === '') {
            $this->json($resp(false, '不能为空'));
            return;
        }
        if ($field === 'username') {
            // 按加权字节数计：1汉字=2；1字母/数字=1。与 doRegister / helpers.name_strlen 一致。
            if (!$loose && (name_strlen($value) < 3 || name_strlen($value) > 15)) {
                $this->json($resp(false, '长度需 3-15 个字节')); return;
            }
            if (!is_valid_name_chars($value)) {
                $this->json($resp(false, '只能包含中文/字母/数字')); return;
            }
            if (is_pure_number($value)) {
                $this->json($resp(false, '不可为纯数字')); return;
            }
            if (has_sensitive($value, 'all', 'exact')) {
                $this->json($resp(false, '含敏感词/限制词')); return;
            }
            $taken = username_taken($value, $exceptId);
            $this->json($resp(!$taken, $taken ? '已被占用' : ''));
            return;
        }
        // nickname
        // 注册场景（没有 exceptId，即不是在编辑某个既有账号）按加权字节数强制 3-15，与 doRegister 一致；
        // 编辑既有用户（个人资料页 / 后台改资料，都会传 id）同样强制 3-15 加权，不再放宽。
        if (name_strlen($value) < 3 || name_strlen($value) > 15) {
            $this->json($resp(false, '长度需 3-15 个字节')); return;
        }
        if (!is_valid_name_chars($value)) {
            $this->json($resp(false, '只能包含中文/字母/数字')); return;
        }
        if (is_pure_number($value)) {
            $this->json($resp(false, '不可为纯数字')); return;
        }
        if (has_sensitive($value, 'all', 'exact')) {
            $this->json($resp(false, '含敏感词/限制词')); return;
        }
        $taken = nickname_taken($value, $exceptId);
        $this->json($resp(!$taken, $taken ? '已被占用' : ''));
        } catch (Throwable $e) {
            // 写 php_error.log + 业务日志，便于排障
            @error_log('[checkName] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString() . "\n");
            @file_put_contents(
                STORAGE_PATH . '/logs/app_error.log',
                '[' . date('Y-m-d H:i:s') . '] checkName :: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n",
                FILE_APPEND
            );
            // 降级为"校验服务暂不可用"：available=true 让用户能继续完成注册
            // reason 里带上真实异常 message，方便用户截图反馈我们
            $short = $e->getMessage();
            if (mb_strlen($short) > 80) $short = mb_substr($short, 0, 80) . '...';
            $this->json($resp(true, '校验服务暂不可用（已记录）：' . $short));
        }
    }

    /**
     * 登出
     */
    public function logout()
    {
        Auth::logout();
        flash('已退出登录', 'info');
        redirect(url('home/index'));
    }

    /**
     * 当前用户信息（API）
     */
    public function current()
    {
        if (!Auth::check()) {
            $this->json(['code' => 401, 'message' => '未登录'], 401);
        }
        $u = Auth::user();
        unset($u['password_hash']);
        $this->success($u);
    }

    /**
     * 找回密码页
     */
    public function forgot()
    {
        $this->view('auth/forgot');
    }

    /**
     * 执行密码重置
     */
    public function doReset()
    {
        csrf_check();
        $email = trim(input('email'));
        $code = trim(input('email_code'));
        $password = input('password');
        $confirm = input('confirm_password');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $this->error('邮箱格式不正确');
        if (!$code) $this->error('请输入验证码');
        if (strlen($password) < 6) $this->error('密码至少6位');
        if ($password !== $confirm) $this->error('两次密码不一致');

        $user = Model::table('users')->where('email', $email)->first();
        if (!$user) $this->error('该邮箱未注册');

        if (!EmailController::verifyCode($email, $code, 'forgot')) {
            $this->error('验证码错误或已过期');
        }

        Model::table('users')->where('id', $user['id'])->update([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        // 改密码后吊销所有「记住我」token，避免旧 token 仍可登录
        Auth::clearRememberToken($user['id']);
        $this->success(['redirect' => url('auth/login')], '密码已重置，请登录');
    }
}
