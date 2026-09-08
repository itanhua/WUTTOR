<?php
/**
 * 后台管理控制器
 */
class AdminController extends Controller
{
    /**
     * 系统预留默认认证图标（兜底）：圆形背景 + ✓
     * 当库中未配置"系统预留"时，清除后可一键恢复为此内置默认。
     */
    const DEFAULT_CERT_BADGE_SVG = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="11" fill="#ea6f5a"/><path d="M6.5 12.4l3.4 3.4 7.2-7.6" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    protected $middleware = [
        ['middleware' => ['auth', 'admin']],
        ['middleware' => 'csrf', 'only' => [
            'storeCategory', 'updateCategory', 'deleteCategory',
            'banUser', 'unbanUser', 'deleteUser', 'assignModerator', 'revokeModerator',
            'approveCert', 'rejectCert', 'batchCert', 'batchDeletePosts', 'batchDeleteComments',
            'handleReport', 'saveSettings', 'saveMailSettings', 'saveNavSettings', 'addSensitiveWord', 'editSensitiveWord', 'deleteSensitiveWord',
            'createRole', 'updateRolePermissions', 'updateRole', 'deleteRole',
            'addCertItem', 'updateCertItem', 'deleteCertItem', 'saveCertSettings', 'saveCertGroupIcon', 'restoreCertGroupIcon',
            'addCertGroup', 'renameCertGroup', 'deleteCertGroup',
            'testMail', 'updateUser',
            'sendSystemNotification',
            'saveFooterSettings',
            'importSensitiveWords',
            'savePermalinkSettings',
            'doAddUser',
            'saveCaptcha',
            'restorePost', 'purgePost', 'restoreComment', 'purgeComment', 'emptyTrash',
            'saveCurrency', 'deleteCurrency', 'mirrorCurrencyRules', 'normalizeCurrencyCodes',
            'uploadCurrencyIcon', 'clearCurrencyIcon',
            'saveUserLevels', 'addUserLevel', 'deleteUserLevel',
            'rechargeGenerate', 'rechargeCardInvalidate', 'rechargeCardDelete', 'rechargeCardBatchDelete', 'rechargeCampaignAdd', 'rechargeCampaignToggle', 'rechargeCampaignDelete', 'rechargeConfigSave',
            'saveSpecialTheme',
            // 表情管理（站长专属）：启停/删除/保存
            'emojiPackSave', 'emojiPackToggle', 'emojiPackDelete',
            'emojiItemSave', 'emojiItemToggle', 'emojiItemDelete',
            // 版式设置（帖子列表 default=列表 / card=卡片）
            'saveLayout',
            // 卡片版式首页内容（轮播图 + 推荐位）
            'saveHomeCarousel',
        ]],
    ];

    public function __construct()
    {
        View::setLayout('admin');
    }

    /* ========== 仪表盘 ========== */
    public function index()
    {
        $stats = [
            'users' => Model::scalar('SELECT COUNT(*) FROM users'),
            'posts' => Model::scalar('SELECT COUNT(*) FROM posts WHERE status = 1'),
            'comments' => Model::scalar('SELECT COUNT(*) FROM comments WHERE status = 1'),
            'pending_certs' => Model::scalar('SELECT COUNT(*) FROM certifications WHERE status = 0'),
            'pending_reports' => Model::scalar('SELECT COUNT(*) FROM reports WHERE status = 0'),
            'today_users' => Model::scalar('SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()'),
            'today_posts' => Model::scalar('SELECT COUNT(*) FROM posts WHERE DATE(created_at) = CURDATE() AND status = 1'),
            'certified_users' => Model::scalar('SELECT COUNT(*) FROM users WHERE is_certified = 1'),
        ];

        // 近7天注册趋势
        $trend = Model::query("SELECT DATE(created_at) AS d, COUNT(*) AS c FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY d");

        // 热门帖子
        $hotPosts = Model::query("SELECT id, title, view_count, comment_count FROM posts WHERE status = 1 ORDER BY view_count DESC LIMIT 10");

        $this->view('admin/index', ['stats' => $stats, 'trend' => $trend, 'hotPosts' => $hotPosts]);
    }

    /* ========== 用户管理 ========== */
    public function users()
    {
        if (!can('user.manage')) { flash('无权限：未授予「用户管理」', 'error'); redirect(url('admin/index')); return; }
        $page = (int)input('page', 1);
        $keyword = trim(input('q', ''));
        $role = input('role', '');
        $certStatus = input('cert', '');

        $sql = "SELECT id, username, email, phone, avatar, nickname, role, is_certified, status, created_at, last_login_at FROM users WHERE 1=1";
        $params = [];
        if ($keyword) {
            $sql .= " AND (username LIKE ? OR email LIKE ? OR nickname LIKE ?)";
            $kw = '%' . $keyword . '%';
            $params[] = $kw; $params[] = $kw; $params[] = $kw;
        }
        if ($role) { $sql .= " AND role = ?"; $params[] = $role; }
        if ($certStatus === 'yes') $sql .= " AND is_certified = 1";
        if ($certStatus === 'no') $sql .= " AND is_certified = 0";
        $sql .= " ORDER BY created_at DESC";

        $countSql = preg_replace('/SELECT.*FROM/i', 'SELECT COUNT(*) AS cnt FROM', $sql, 1);
        $stmt = Database::pdo()->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetch()['cnt'];

        $perPage = 20;
        $sql .= ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        // 角色权限联动：与「角色权限」模块共用同一份 roles / role_permissions / permissions 数据，
        // 编辑用户弹窗里的「用户角色」下拉直接调用这里的角色名称，选角色后联动展示其权限。
        $roles = Model::table('roles')->orderBy('id', 'ASC')->get();
        $rolePerms = [];
        foreach ($roles as $r) {
            $perms = Model::query("SELECT permission_id FROM role_permissions WHERE role_id = ?", [$r['id']]);
            $rolePerms[$r['id']] = array_column($perms, 'permission_id');
        }
        $permissions = Model::table('permissions')->orderBy('module', 'ASC')->orderBy('id', 'ASC')->get();

        $this->view('admin/users', [
            'users' => $users, 'total' => $total, 'page' => $page, 'perPage' => $perPage,
            'keyword' => $keyword, 'role' => $role, 'certStatus' => $certStatus,
            'roles' => $roles, 'rolePerms' => $rolePerms, 'permissions' => $permissions,
        ]);
    }

    public function banUser($id = null)
    {
        if (!can('user.manage')) $this->error('无权限：未授予「用户管理」');
        $id = (int)($id ?? input('id'));
        $user = Model::table('users')->where('id', $id)->first();
        if (!$user) $this->error('用户不存在');
        if ($user['role'] === 'webmaster') $this->error('不能禁用站长');
        if ($user['role'] === 'super_admin' && !is_webmaster()) $this->error('不能禁用超级管理员');
        // 兜底：被禁者若是最后一个正常 super_admin（且操作者非站长），禁止
        if ($user['role'] === 'super_admin' && (int)$user['status'] === 1 && !is_webmaster()) {
            $cnt = Model::query('SELECT COUNT(*) AS c FROM users WHERE role = ? AND status = 1', ['super_admin']);
            if ((int)($cnt[0]['c'] ?? 0) <= 1) $this->error('系统至少需要保留一名正常状态的超级管理员');
        }
        Model::table('users')->where('id', $id)->update(['status' => 0]);
        $this->success(null, '已禁用用户');
    }

    public function unbanUser($id = null)
    {
        if (!can('user.manage')) $this->error('无权限：未授予「用户管理」');
        $id = (int)($id ?? input('id'));
        $user = Model::table('users')->where('id', $id)->first();
        if (!$user) $this->error('用户不存在');
        Model::table('users')->where('id', $id)->update(['status' => 1]);
        $this->success(null, '已启用用户');
    }

    public function deleteUser($id = null)
    {
        if (!can('user.delete')) $this->error('无权限：未授予「删除用户」');
        $id = (int)($id ?? input('id'));
        $user = Model::table('users')->where('id', $id)->first();
        if (!$user) $this->error('用户不存在');
        if ($user['role'] === 'webmaster') $this->error('不能删除站长');
        if ($user['role'] === 'super_admin' && !is_webmaster()) $this->error('不能删除超级管理员');
        Model::table('users')->where('id', $id)->delete();
        $this->success(null, '用户已删除');
    }

    public function assignModerator($id = null)
    {
        if (!can('user.manage')) $this->error('无权限：未授予「用户管理」');
        $id = (int)($id ?? input('id'));
        $catId = (int)input('category_id');
        $user = Model::table('users')->where('id', $id)->first();
        if (!$user) $this->error('用户不存在');
        if ($user['role'] === 'webmaster' && !is_webmaster()) $this->error('不能修改站长角色');
        // 如果用户本身已拥有 ≥ moderator 的更高角色（管理员/超级管理员/站长），
        // 保留其原 role 仅插入板块版主关联，避免降级；系统以 category_moderators 多对多为真实版主权限依据。
        $currentLevel = Auth::roleLevel($user['role']);
        if ($currentLevel < Auth::roleLevel('moderator')) {
            Model::table('users')->where('id', $id)->update(['role' => 'moderator']);
        }
        if ($catId) {
            Model::execute(
                'INSERT IGNORE INTO category_moderators (category_id, user_id, created_at) VALUES (?, ?, ?)',
                [$catId, $id, date('Y-m-d H:i:s')]
            );
        }
        // 即使没有传 category_id，也允许该用户在所有板块担任版主（保留原角色）
        $this->success(null, $currentLevel >= Auth::roleLevel('moderator') ? '已加入版主（原角色权限保留为最高）' : '已设为版主');
    }

    public function revokeModerator($id = null)
    {
        if (!can('user.manage')) $this->error('无权限：未授予「用户管理」');
        $id = (int)($id ?? input('id'));
        $user = Model::table('users')->where('id', $id)->first();
        if (!$user) $this->error('用户不存在');
        if ($user['role'] === 'webmaster' && !is_webmaster()) $this->error('不能修改站长角色');
        // 仅当被撤销者 users.role 真实为 moderator 时才把 role 回退到 user；
        // 对 admin / super_admin / webmaster 等更高角色，仅删除板块版主关联，role 维持不变。
        if ($user['role'] === 'moderator') {
            Model::table('users')->where('id', $id)->update(['role' => 'user']);
        }
        Model::table('category_moderators')->where('user_id', $id)->delete();
        $this->success(null, '已撤销版主身份');
    }

    /**
     * 后台「添加用户」入口（仅站长）。复用编辑弹窗：通过 allocate_user_id 强制新 uid = max+1。
     *
     * 字段：username、nickname（必填，沿用注册规则）、password（必填，≥6 位）、
     *      email（必填）、role、可选 is_certified/cert_groups[]/status。
     * 与注册页共用的校验：用户名/昵称字符集 + 敏感词 + 查重、邮箱格式 + 唯一性。
     *
     * 不允许前端传 id：避免绕过 max+1 规则；新 uid 一律由后端计算。
     */
    public function doAddUser()
    {
        if (!is_webmaster()) $this->error('仅站长可添加用户');
        $rawInput = array_merge($_GET, $_POST, json_input());
        $username = trim((string)($rawInput['username'] ?? ''));
        $nickname = trim((string)($rawInput['nickname'] ?? ''));
        $email    = trim((string)($rawInput['email'] ?? ''));
        $password = (string)($rawInput['password'] ?? '');
        $role     = trim((string)($rawInput['role'] ?? 'user'));

        // === 校验 ===
        if (!is_valid_name_chars($username)) $this->error('用户名只能包含中文、字母和数字');
        if (is_pure_number($username)) $this->error('用户名不可为纯数字');
        if (has_sensitive($username, 'all', 'exact')) $this->error('用户名含敏感词/限制词');
        // 后台添加不限制 username 长度（避免有人想加特殊用途长名空间账号），但仍不允许纯数字/敏感词
        if ($username === '') $this->error('用户名不能为空');

        if ($nickname === '') {
            $nickname = $username;
        } else {
            if (mb_strlen($nickname) > 50) $this->error('昵称长度不能超过 50 个字符');
            if (!is_valid_name_chars($nickname)) $this->error('昵称只能包含中文、字母和数字');
            if (is_pure_number($nickname)) $this->error('昵称不可为纯数字');
            if (has_sensitive($nickname, 'all', 'exact')) $this->error('昵称含敏感词/限制词');
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $this->error('邮箱格式不正确');
        if (strlen($password) < 6) $this->error('密码至少 6 位');
        if (mb_strlen($password) > 64) $this->error('密码长度不能超过 64 个字符');

        // 查重
        if (Model::table('users')->where('username', $username)->first()) $this->error('该用户名或昵称已被占用');
        if (Model::table('users')->where('nickname', $nickname)->first()) $this->error('该昵称或用户名已被占用');
        if (Model::table('users')->where('email', $email)->first()) $this->error('该邮箱已被占用');

        // 角色白名单 + 唯一站长保护：
        //   白名单来源：roles 表所有非 webmaster/guest 的角色（与编辑弹窗「用户角色」下拉同步）；
        //   这样站长在「角色权限」模块自定义的角色（牛逼/技能/职业等）也能被正常指派。
        //   webmaster 仍走下方兜底：禁止在添加用户时直接生成新站长（系统全局唯一）。
        $allowedRoles = Model::query(
            'SELECT code FROM roles WHERE code NOT IN (?, ?)',
            ['webmaster', 'guest']
        );
        $allowedRoles = array_column($allowedRoles, 'code');
        if (!in_array($role, $allowedRoles, true)) $this->error('非法的用户角色');
        // 即使前端误传 role=webmaster 也强制纠正——杜绝误加新站长
        if ($role === 'webmaster') $role = 'user';

        // 头像（可选）+ 账号认证状态（可选 cert_groups[]，传了就写入）
        $avatar = trim((string)($rawInput['avatar'] ?? ''));
        $emailVerified = !empty($rawInput['email_verified']) ? 1 : 0;
        $status = isset($rawInput['status']) ? (((int)$rawInput['status']) === 1 ? 1 : 0) : 1;

        $rawCertGroups = $rawInput['cert_groups'] ?? null;
        $hasCertGroups = is_array($rawCertGroups);

        $bio = isset($rawInput['bio']) ? (string)$rawInput['bio'] : null;
        if ($bio !== null && mb_strlen($bio) > 500) $this->error('签名档不能超过 500 个字符');

        $now = date('Y-m-d H:i:s');
        $userData = [
            'username' => $username,
            'email' => $email,
            'email_verified' => $emailVerified,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'nickname' => $nickname,
            'avatar' => $avatar === '' ? null : $avatar,
            'bio' => $bio,
            'role' => $role,
            'is_certified' => 0,
            'status' => $status,
            'invited_by' => null,
            'invite_code' => null,
            'last_login_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $userId = allocate_user_id($userData);

        // 默认组（id=1）认证同步：仅当 cert_groups 传了 id=1 时把 is_certified=1
        if ($hasCertGroups) {
            $cleanPicked = array_values(array_unique(array_filter(array_map('intval', $rawCertGroups), function ($v) { return $v > 0; })));
            if (in_array(1, $cleanPicked, true)) {
                Model::table('users')->where('id', $userId)->update([
                    'is_certified' => 1,
                    'certified_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            // 其它组按勾选列表逐项插入（沿用 updateUser 的 cert_groups 处理方式）
            $this->syncUserCertGroups($userId);
        }

        $this->success(['id' => $userId], '用户已创建（uid = ' . $userId . '）');
    }

    /**
     * 后台编辑用户（GET：返回 JSON 数据，供前端弹窗填充）
     */
    public function editUser($id = null)
    {
        if (!can('user.manage')) $this->error('无权限：未授予「用户管理」');
        $id = (int)($id ?? input('id'));
        $user = Model::table('users')->where('id', $id)->first();
        if (!$user) $this->error('用户不存在');
        // 脱敏：不返回密码哈希
        $hash = $user['password_hash'] ?? '';
        unset($user['password_hash']);
        // 是否有密码（密码列是否非空）—— 供前端展示"已设置密码"标记
        $user['has_password'] = !empty($hash);



        // 附带：所有启用认证项目组 + 该用户的认证状态（1=已通过 0=未通过）。
        // 后台用户编辑弹窗以此动态渲染勾选列表；新增/删除项目组后这里自动跟随增减。
        $certGroups = [];
        try {
            $groups = Model::table('certification_groups')->where('status', 1)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->get();
            $passed = [];
            $rows = Model::table('certifications')->where('user_id', $id)->where('status', 1)->get();
            foreach ($rows as $r) $passed[(int)$r['group_id']] = true;
            // 兼容旧数据：users.is_certified=1 → 默认组（id=1）算通过
            if ((int)$user['is_certified'] === 1) $passed[1] = true;
            foreach ($groups as $g) {
                $gid = (int)$g['id'];
                $certGroups[] = [
                    'id' => $gid,
                    'name' => $g['name'],
                    'sort_order' => (int)$g['sort_order'],
                    'is_certified' => isset($passed[$gid]) ? 1 : 0,
                    'is_default' => $gid === 1 ? 1 : 0,
                ];
            }
        } catch (Exception $e) {}
        $user['cert_groups'] = $certGroups;

        // 附带：当前系统唯一站长的账户信息，供弹窗顶部"唯一站长"提示横幅使用。
        // 系统全局有且仅有一个站长（保证权限集中可追溯），名字仅作展示。
        try {
            $wm = Model::query('SELECT id, username, nickname FROM users WHERE role = ? ORDER BY id ASC LIMIT 1', ['webmaster']);
            $user['current_webmaster'] = $wm ? ['id' => (int)$wm[0]['id'], 'username' => $wm[0]['username'], 'nickname' => $wm[0]['nickname']] : null;
        } catch (Exception $e) {
            $user['current_webmaster'] = null;
        }

        $this->success($user);
    }

    /**
     * 后台保存用户编辑（用户名、头像、邮箱、邮箱验证状态、认证状态、签名档、角色、封禁）
     */
    public function updateUser()
    {
        if (!can('user.manage')) $this->error('无权限：未授予「用户管理」');
        $id = (int)input('id');
        $user = Model::table('users')->where('id', $id)->first();
        if (!$user) $this->error('用户不存在');

        $data = ['updated_at' => date('Y-m-d H:i:s')];

        // 用户名（唯一）：交叉查重 username ∪ nickname，自身 id 豁免；纯数字 / 敏感词同注册页规则
        // 后台编辑用户名**不强制长度**：去掉 2-50 长度校验，只保留查重。
        // （注册页 / 个人资料页的 3-20 位长度约束未变，仍由注册流程与 AuthController::checkName 负责。）
        $username = trim(input('username', ''));
        if ($username !== '' && $username !== $user['username']) {
            if (!is_valid_name_chars($username)) $this->error('用户名只能包含中文、字母和数字');
            if (is_pure_number($username)) $this->error('用户名不可为纯数字');
            if (has_sensitive($username, 'all', 'exact')) $this->error('用户名含敏感词/限制词');
            // 交叉查重：用户名碰撞检查现有 username 列或 nickname 列
            if (username_taken($username, $id)) $this->error('该用户名或昵称已被占用');
            $data['username'] = $username;
        }

        // 昵称（可选）：后台管理员可主动清空昵称。
        // 注意：input() 会把"字段缺失"也归一化为空串（见 input() 内 if($value===null)$value=''），
        // 无法区分"未提交"与"主动清空"。因此直接检测原始输入里 nickname 键是否存在——
        // 表单始终会提交该键（即使为空），故：键存在且为空=主动清空；键存在且有值=走校验后写入。
        $rawInput = array_merge($_GET, $_POST, json_input());
        if (array_key_exists('nickname', $rawInput)) {
            $nickname = trim((string)($rawInput['nickname'] ?? ''));
            if ($nickname === '') {
                // 主动清空昵称（管理员操作，允许）
                $data['nickname'] = null;
            } else {
                if (mb_strlen($nickname) > 50) $this->error('昵称长度不能超过 50 个字符');
                // 仅当真正变更时校验（不改动保留原值不报错）
                if ($nickname !== $user['nickname']) {
                    if (!is_valid_name_chars($nickname)) $this->error('昵称只能包含中文、字母和数字');
                    if (is_pure_number($nickname)) $this->error('昵称不可为纯数字');
                    if (has_sensitive($nickname, 'all', 'exact')) $this->error('昵称含敏感词/限制词');
                    // 交叉查重：昵称碰撞检查现有 username 列或 nickname 列
                    if (nickname_taken($nickname, $id)) $this->error('该昵称或用户名已被占用');
                }
                $data['nickname'] = $nickname;
            }
        }

        // 头像（URL 或留空）
        $avatar = trim(input('avatar', ''));
        $data['avatar'] = $avatar === '' ? null : $avatar;

        // 密码（仅站长可改；可主动清空视为"重置为空密码"——密码字段空字符串视为清空）
        // 逻辑与"邮箱"完全平行：站长可以重置任意用户的密码；其它管理身份即使前端误传也拒绝。
        // 注意：传空字符串 password = 视为"未改密码"，避免误点保存把现有密码清掉。
        $rawPassword = input('password', null);
        if ($rawPassword !== null && $rawPassword !== '') {
            if (!is_webmaster()) {
                $this->error('密码仅站长可修改');
            }
            if (strlen((string)$rawPassword) < 6) {
                $this->error('密码至少 6 位');
            }
            if (mb_strlen((string)$rawPassword) > 64) {
                $this->error('密码长度不能超过 64 个字符');
            }
            $data['password_hash'] = password_hash((string)$rawPassword, PASSWORD_DEFAULT);
        }

        // UID（仅站长可改）：允许把存量用户的 uid 改大/改小；UPDATE 主键安全，
        // 因为本应用的所有 user_id 外键列都**未**加 FK 约束（仅用 BIGINT 引用），不会级联报错。
        // 校验：必须是正整数 + 当前表里不存在 + ≥ 现有 users.id 的非零个数（避免把活跃用户改成 0/重复编号）。
        $rawUid = input('uid', null);
        if ($rawUid !== null && $rawUid !== '') {
            if (!is_webmaster()) {
                $this->error('UID 仅站长可修改');
            }
            if (!is_numeric($rawUid) || (int)$rawUid <= 0) {
                $this->error('UID 必须为正整数');
            }
            $newUid = (int)$rawUid;
            if ($newUid !== (int)$id) {
                $exists = Model::table('users')->where('id', $newUid)->first();
                if ($exists) {
                    $this->error('该 UID 已被占用');
                }
                // 站长不能改自己的 UID：登录态会立即失效（session 仍指向旧 id），
                // 改完要重新登录，且前端用户中心首页的 self_id 也会指向错位对象。
                $selfId = (int)(Auth::id() ?? 0);
                if ($selfId > 0 && $selfId === (int)$id) {
                    $this->error('站长账号的 UID 不可在此修改，否则当前登录态将立即失效');
                }
                // 站内不允许把现存用户改成"小于现存最小 uid"的负向编号——会破坏后续 "MAX+1" 规则
                // 并让"max+1"突然降到 1 这种尴尬场景。最低只能保持当前所有 uid 里的最小值。
                // 例：现存 [1,2,5,100]，改到 3 OK，改到 1 OK，改到 0 / -3 拒绝。
                $minRow = Model::query("SELECT COALESCE(MIN(id), 1) AS m FROM users");
                $minId = (int)($minRow[0]['m'] ?? 1);
                if ($newUid < $minId) {
                    $this->error("UID 不能小于当前最小 UID（{$minId}），否则会破坏后续 max+1 规则");
                }
                $data['id'] = $newUid;
            }
        }

        // 邮箱（唯一）：仅站长（webmaster）可改；其他管理身份即使前端误传也直接拒绝
        $email = trim(input('email', ''));
        if ($email !== '' && $email !== $user['email']) {
            if (!is_webmaster()) {
                $this->error('邮箱仅站长可修改');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $this->error('邮箱格式不正确');
            $dup = Model::table('users')->where('email', $email)->where('id', '!=', $id)->first();
            if ($dup) $this->error('该邮箱已被占用');
            $data['email'] = $email;
        }

        // 邮箱验证状态：仅站长可改
        // ⚠️ input() 内部会把 null 转成 ''（见 helpers.php line ~1009 兼容 PHP 8.1+ 的 null→'' 处理），
        // 因此 input('email_verified') !== null 这种写法永远为 true，会导致非站长在保存时误报"仅站长可修改"。
        // 必须用 has_input() 检查前端是否真的发送了该字段。
        if (has_input('email_verified')) {
            if (!is_webmaster()) {
                $this->error('邮箱验证状态仅站长可修改');
            }
            $data['email_verified'] = (int)input('email_verified') ? 1 : 0;
        }

        // 账号认证状态：默认走"按认证项目组勾选"（cert_groups[]）。仅在旧版"单选下拉"模式下处理 is_certified。
        // 整段仅站长可写：非站长操作者禁止修改认证状态，前端 hidden + 后端校验双保。
        // ⚠️ 同上，input() 把 null 转成 ''，这里必须用 has_input() 判断是否真的发送了。
        if (!is_webmaster() && (has_input('cert_groups') || has_input('is_certified'))) {
            $this->error('账号认证状态仅站长可修改');
        }
        $rawCertGroups = input('cert_groups', null);
        $hasCertGroups = is_array($rawCertGroups);
        if (!$hasCertGroups && has_input('is_certified')) {
            $cert = (int)input('is_certified') ? 1 : 0;
            $data['is_certified'] = $cert;
            if ($cert && !$user['is_certified']) $data['certified_at'] = date('Y-m-d H:i:s');
            if (!$cert) $data['certified_at'] = null;
        }

        // 签名档
        $bio = input('bio', null);
        if ($bio !== null) {
            if (mb_strlen($bio) > 500) $this->error('签名档不能超过 500 个字符');
            $data['bio'] = $bio;
        }

        // 用户角色：
        // 权限矩阵（操作者 vs 被编辑者）：
        //   站长 → 任意用户：可改任意 role（含把 super_admin 改为 admin/版主/普通）。
        //   站长 → 站长（自己）：role 不可改（防止把自己降级后系统锁死）。
        //   非站长 → 任意用户：role 字段不可见不可编辑，updateUser 必须拒绝写入；
        //     前端 wmBlock 整块隐藏 + submit handler 不发 role 字段，
        //     后端对 input('role') !== null 时强制做身份校验（双保险），防止绕过前端 Postman 改人角色。
        // 兜底保护：禁止把"最后一个 super_admin"降为非 super_admin，避免无人能恢复后台。
        if (input('role') !== null && input('role') !== '') {
            if (!is_webmaster()) $this->error('仅站长可修改用户角色');
            $role = trim(input('role'));
            // 白名单来源：roles 表所有非 guest 的角色（与编辑弹窗「用户角色」下拉同步），
            // 支持站长在「角色权限」模块自定义的角色（牛逼/技能/职业等）。
            // webmaster 单独追加进白名单（编辑弹窗会把 webmaster option 标记为 self-only，但后端兜底接受这个值）。
            $allowed = Model::query(
                'SELECT code FROM roles WHERE code <> ?',
                ['guest']
            );
            $allowed = array_column($allowed, 'code');
            if (!in_array('webmaster', $allowed, true)) $allowed[] = 'webmaster';
            if (!in_array($role, $allowed, true)) $this->error('非法的用户角色');
            $selfId = (int)(Auth::id() ?? 0);
            $isSelf = $selfId > 0 && $id === $selfId;

            // 站长自己的 role 不可改
            if (is_webmaster() && $isSelf) {
                $this->error('站长的用户角色不可在此修改');
            }
            // super_admin 不能编辑站长的 role
            if ($user['role'] === 'webmaster' && !is_webmaster()) {
                $this->error('站长角色只能由站长本人或同等及以上权限修改');
            }
            // 仅站长可以把别人设为站长；防止 super_admin 误设置站长后不可更改
            if ($role === 'webmaster' && !is_webmaster()) {
                $this->error('只有站长才能授予「站长」角色');
            }
            // === 系统全局唯一站长规则 ===
            // 1) 站长本人（is_webmaster && $isSelf）已在上面整体拦截，下面不再重复。
            // 2) 站长（is_webmaster）把别人改成站长 → 禁止。站长权限高于一切，
            //    派生站长会形成多头管理，违反"单一权限源"原则。
            if ($role === 'webmaster' && is_webmaster() && !$isSelf) {
                $this->error('系统有且仅有一个站长，你已是站长，不可把其他用户设为站长');
            }
            // 3) 兜底：除当前被编辑者本人外，全表已存在另一个 webmaster → 一律拒绝。
            //    覆盖「通过 SQL 直接 INSERT 的运维事故」或「迁移工具漏改」导致的重复。
            if ($role === 'webmaster') {
                $others = Model::query(
                    'SELECT COUNT(*) AS c FROM users WHERE role = ? AND id <> ?',
                    ['webmaster', $id]
                );
                if ((int)($others[0]['c'] ?? 0) > 0) {
                    $this->error('系统全局只能有一个站长，已存在其他站长账号');
                }
            }
            // super_admin 试图把自己的 role 改成非 super_admin（最后一道防自降级）
            if ($isSelf && !is_webmaster() && $user['role'] === 'super_admin' && $role !== 'super_admin') {
                $this->error('不能把自己从超级管理员降为其他角色，否则系统将无法恢复');
            }
            // 兜底：被降级的对象若是「最后一个 super_admin」，禁止（除站长外，避免全降完后锁死）
            if ($user['role'] === 'super_admin' && $role !== 'super_admin' && !is_webmaster()) {
                $cnt = Model::query('SELECT COUNT(*) AS c FROM users WHERE role = ? AND status = 1', ['super_admin']);
                $remaining = (int)($cnt[0]['c'] ?? 0);
                if ($remaining <= 1) {
                    $this->error('系统至少需要保留一名超级管理员');
                }
            }
            $data['role'] = $role;
            // 若取消版主，清理版主关联
            if ($role !== 'moderator' && $user['role'] === 'moderator') {
                Model::table('category_moderators')->where('user_id', $id)->delete();
            }
        }

        // 封禁状态（status: 1 正常 / 0 封禁）
        // 权限矩阵：
        //   站长 → 任意用户：可改 status（含封禁其他 super_admin）。
        //   站长 → 站长（自己）：status 不可改（与 role 同理，防止自封后无法恢复）。
        //   super_admin → 非站长：可改 status；不能封禁站长。
        // 兜底：不能封禁自己；不能把"最后一个正常 super_admin"封禁（除站长）。
        if (input('status') !== null) {
            $status = (int)input('status');
            $selfId = (int)(Auth::id() ?? 0);
            $isSelf = $selfId > 0 && $id === $selfId;
            if ($isSelf && $status === 0 && !is_webmaster()) {
                $this->error('不能封禁自己，否则将无法再次登录');
            }
            // 站长自己也禁止自封
            if ($isSelf && is_webmaster() && $status === 0) {
                $this->error('站长账号状态不可在此修改');
            }
            if ($user['role'] === 'webmaster' && !is_webmaster()) {
                $this->error('站长账号状态只能由站长本人或同等及以上权限修改');
            }
            // 兜底：被封禁的目标若是「最后一个正常 super_admin」，禁止（除站长）
            if ($status === 0 && $user['role'] === 'super_admin' && (int)$user['status'] === 1 && !is_webmaster()) {
                $cnt = Model::query('SELECT COUNT(*) AS c FROM users WHERE role = ? AND status = 1', ['super_admin']);
                $remaining = (int)($cnt[0]['c'] ?? 0);
                if ($remaining <= 1) {
                    $this->error('系统至少需要保留一名正常状态的超级管理员');
                }
            }
            $data['status'] = $status === 1 ? 1 : 0;
        }

        Model::table('users')->where('id', $id)->update($data);
        // 若这一轮把 $data['id'] 改成新 uid（仅站长），更新后真实主键已变。
        // 后续 cert_groups 等操作必须用新 id，否则会查不到（用户改成新 uid 后
        // cert_groups 仍用旧 id 写，等于"幽灵用户"——典型脏数据来源）。
        if (isset($data['id']) && (int)$data['id'] !== (int)$id) {
            $id = (int)$data['id'];
        }

        // === 账号认证状态：按"认证项目组"逐项勾选 ===
        // POST cert_groups[] = [group_id,...] 时才处理。空数组 = 全部不勾选。
        if ($hasCertGroups) {
            $this->syncUserCertGroups($id);
        }

        $this->success(null, '用户资料已更新');
    }

    /* ========== 板块管理（仅站长） ========== */
    public function categories()
    {
        if (!is_webmaster()) { flash('仅站长可访问「板块管理」', 'error'); redirect(url('admin/index')); return; }
        $cats = Model::query(
            "SELECT c.*, (SELECT COUNT(*) FROM posts WHERE category_id = c.id AND status = 1) AS actual_count FROM categories c ORDER BY c.sort_order ASC"
        );
        $moderators = Model::table('users')->whereIn('role', ['moderator', 'admin', 'super_admin'])->where('status', 1)->get();
        $catMods = Model::query(
            'SELECT cm.category_id, cm.user_id, u.username, u.nickname FROM category_moderators cm LEFT JOIN users u ON cm.user_id = u.id ORDER BY cm.created_at ASC'
        );
        $modsByCat = [];
        foreach ($catMods as $cm) {
            $modsByCat[$cm['category_id']][] = $cm;
        }
        foreach ($cats as &$c) {
            $c['moderators'] = $modsByCat[$c['id']] ?? [];
        }
        unset($c);
        $this->view('admin/categories', ['cats' => $cats, 'moderators' => $moderators]);
    }

    public function storeCategory()
    {
        if (!is_webmaster()) $this->error('仅站长可管理板块');
        $name = trim(input('name'));
        $desc = trim(input('description'));
        $icon = fa_icon_class(trim(input('icon'))); // 归一化为 FA4 写法（fa fa-xxx）
        $color = trim(input('color', ''));
        $certRequired = (int)input('is_certification_required');
        $sortOrder = (int)input('sort_order', 0);

        if (!$name) $this->error('板块名称不能为空');
        $now = date('Y-m-d H:i:s');
        $newId = Model::table('categories')->insert([
            'name' => $name, 'description' => $desc, 'rule' => trim(input('rule', '')) ?: null, 'icon' => $icon, 'color' => $color ?: null,
            'is_certification_required' => $certRequired, 'sort_order' => $sortOrder,
            'browse_roles' => $this->normalizeRoleCsv(input('browse_roles')),
            'publish_roles' => $this->normalizeRoleCsv(input('publish_roles')),
            'post_count' => 0, 'status' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        // 写入多版主（手动输入用户名，逗号/空格分隔）
        $modIds = $this->resolveModeratorIdsByNames(input('moderator_names', ''));
        $this->saveCategoryModerators($newId, $modIds);
        $this->success(null, '板块已创建');
    }

    public function updateCategory($id = null)
    {
        if (!is_webmaster()) $this->error('仅站长可管理板块');
        $id = (int)($id ?? input('id'));
        $cat = Model::table('categories')->where('id', $id)->first();
        if (!$cat) $this->error('板块不存在');
        $data = ['updated_at' => date('Y-m-d H:i:s')];
        foreach (['name', 'description', 'rule', 'color'] as $f) {
            $v = input($f);
            if ($v !== null) $data[$f] = trim($v);
        }
        // icon 单独归一化（兼容 FA6 误填，统一存 FA4 写法）
        $iconRaw = input('icon');
        if ($iconRaw !== null) $data['icon'] = fa_icon_class(trim($iconRaw));
        if (empty($data['color'])) $data['color'] = null;
        if (empty($data['rule'])) $data['rule'] = null;
        $data['is_certification_required'] = (int)input('is_certification_required', 0);
        $data['browse_roles'] = $this->normalizeRoleCsv(input('browse_roles'));
        $data['publish_roles'] = $this->normalizeRoleCsv(input('publish_roles'));
        $data['sort_order'] = (int)input('sort_order', 0);
        $data['status'] = (int)input('status', 1);
        Model::table('categories')->where('id', $id)->update($data);
        $modIds = $this->resolveModeratorIdsByNames(input('moderator_names', ''));
        $this->saveCategoryModerators($id, $modIds);
        $this->success(null, '板块已更新');
    }

    /**
     * 同步多版主关联：传入用户 id 数组，先清空再插入（仅插入合法且未禁用用户）
     */
    protected function saveCategoryModerators($categoryId, $userIds)
    {
        $categoryId = (int)$categoryId;
        Model::table('category_moderators')->where('category_id', $categoryId)->delete();
        if (empty($userIds)) return;
        $userIds = array_unique(array_map('intval', (array)$userIds));
        $userIds = array_filter($userIds, function ($uid) { return $uid > 0; });
        foreach ($userIds as $uid) {
            Model::execute(
                'INSERT IGNORE INTO category_moderators (category_id, user_id, created_at) VALUES (?, ?, ?)',
                [$categoryId, $uid, date('Y-m-d H:i:s')]
            );
        }
    }

    /**
     * 将角色多选数组归一为逗号分隔字符串；空数组/空值返回 null（表示不限）
     */
    protected function normalizeRoleCsv($roles)
    {
        if (is_array($roles)) {
            $clean = array_filter(array_map('trim', $roles), function ($r) { return $r !== ''; });
            return $clean ? implode(',', array_unique($clean)) : null;
        }
        $clean = array_filter(array_map('trim', explode(',', (string)$roles)), function ($r) { return $r !== ''; });
        return $clean ? implode(',', array_unique($clean)) : null;
    }

    /**
     * 将手动输入的版主用户名（逗号/空格分隔）解析为 user_id 数组。
     * 用户名不存在的会直接忽略并记录日志。
     */
    protected function resolveModeratorIdsByNames($namesStr)
    {
        if (!is_string($namesStr) || trim($namesStr) === '') return [];
        // 兼容中英文逗号、顿号、分号、空白作为分隔符
        $parts = preg_split('/[\s,，、;；]+/u', $namesStr);
        $names = array_filter(array_map('trim', $parts), function ($n) { return $n !== ''; });
        if (empty($names)) return [];
        $rows = Model::query(
            'SELECT id, username FROM users WHERE username IN (' . implode(',', array_fill(0, count($names), '?')) . ') AND status = 1',
            array_values($names)
        );
        $ids = array_column($rows, 'id');
        $found = array_column($rows, 'username');
        $missing = array_diff($names, $found);
        if (!empty($missing)) {
            error_log('[category_moderators] 未找到用户名：' . implode(', ', $missing));
        }
        return $ids;
    }

    public function deleteCategory($id = null)
    {
        if (!is_webmaster()) $this->error('仅站长可管理板块');
        if (!is_high_admin()) $this->error('无权限');
        $id = (int)($id ?? input('id'));
        $postCount = Model::table('posts')->where('category_id', $id)->where('status', 1)->count();
        if ($postCount > 0) $this->error('该板块下还有 ' . $postCount . ' 篇帖子，请先移动或删除帖子');
        Model::table('categories')->where('id', $id)->delete();
        $this->success(null, '板块已删除');
    }

    /* ========== 帖子管理 ========== */
    public function posts()
    {
        $page = (int)input('page', 1);
        $cat = (int)input('cat', 0);
        $keyword = trim(input('q', ''));

        $builder = Model::table('posts');
        if ($cat) $builder->where('category_id', $cat);
        if ($keyword) $builder->whereLike('title', '%' . $keyword . '%');
        $result = $builder->orderBy('created_at', 'DESC')->paginate($page, 20);

        foreach ($result['data'] as &$p) {
            $p['author'] = Model::table('users')->select('id', 'username', 'nickname')->where('id', $p['user_id'])->first();
            $p['category'] = Model::table('categories')->select('id', 'name')->where('id', $p['category_id'])->first();
        }
        $cats = Model::table('categories')->where('status', 1)->get();
        $this->view('admin/posts', ['posts' => $result['data'], 'pagination' => $result, 'page' => $page, 'cats' => $cats, 'cat' => $cat, 'keyword' => $keyword]);
    }

    public function batchDeletePosts()
    {
        if (!can('post.delete_section')) $this->error('无权限：未授予「本版删除」');
        $ids = input('ids', []);
        if (empty($ids)) $this->error('请选择帖子');
        $count = 0;
        foreach ((array)$ids as $pid) {
            $pid = (int)$pid;
            if ($pid <= 0) continue;
            Model::table('posts')->where('id', $pid)->update(['status' => 0, 'deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => Auth::id()]);
            // 违规批删：回收该帖下的全部积分（token + byte 一起退）
            \PointService::refundPostPoints($pid);
            $count++;
        }
        $this->success(['count' => $count], '已批量删除 ' . $count . ' 篇');
    }

    /* ========== 评论管理 ========== */
    public function comments()
    {
        $page = (int)input('page', 1);
        $result = Model::query(
            "SELECT c.*, u.nickname, p.title AS post_title FROM comments c LEFT JOIN users u ON c.user_id = u.id LEFT JOIN posts p ON c.post_id = p.id WHERE c.status = 1 ORDER BY c.created_at DESC LIMIT ? OFFSET ?",
            [20, ($page - 1) * 20]
        );
        $total = Model::scalar('SELECT COUNT(*) FROM comments WHERE status = 1');
        $this->view('admin/comments', ['comments' => $result, 'total' => $total, 'page' => $page]);
    }

    public function batchDeleteComments()
    {
        if (!can('comment.delete_section')) $this->error('无权限：未授予「删除评论」');
        $ids = input('ids', []);
        if (empty($ids)) $this->error('请选择评论');
        $count = 0;
        foreach ((array)$ids as $cid) {
            $cid = (int)$cid;
            if ($cid <= 0) continue;
            Model::table('comments')->where('id', $cid)->update(['status' => 0, 'deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => Auth::id()]);
            // 违规批删：仅回收该评论产生的积分（token + byte 一起退）
            \PointService::refundBySource('comment_create', $cid);
            \PointService::refundBySource('reply_create', $cid);
            \PointService::refundBySource('post_liked', $cid);
            $count++;
        }
        $this->success(['count' => $count], '已批量删除 ' . $count . ' 条');
    }

    /* ========== 认证审核 ========== */
    public function certifications()
    {
        $page = (int)input('page', 1);
        $status = input('status', '');
        $sql = "SELECT cert.*, u.username, u.nickname, u.avatar, g.name AS group_name, g.sort_order AS group_sort
                FROM certifications cert
                LEFT JOIN users u ON cert.user_id = u.id
                LEFT JOIN certification_groups g ON cert.group_id = g.id";
        $params = [];
        if ($status !== '') {
            $sql .= " WHERE cert.status = ?";
            $params[] = (int)$status;
        }
        // 按"认证项目 → 用户" 稳定排序（让每个用户的每个项目单独一行）
        $sql .= " ORDER BY g.sort_order ASC, g.id ASC, cert.user_id ASC, cert.id DESC";
        $countSql = "SELECT COUNT(*) AS cnt FROM certifications";
        if ($status !== '') $countSql .= " WHERE status = " . (int)$status;
        $total = (int)Model::query($countSql)[0]['cnt'] ?? 0;

        $perPage = 20;
        $sql .= ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $certs = $stmt->fetchAll();

        $this->view('admin/certifications', ['certs' => $certs, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'status' => $status]);
    }

    public function certificationDetail($id = null)
    {
        $id = (int)($id ?? input('id'));
        $cert = Model::query(
            "SELECT cert.*, u.username, u.nickname, u.avatar, u.email, u.created_at AS user_created, rev.nickname AS reviewer_name, g.name AS group_name
             FROM certifications cert
             LEFT JOIN users u ON cert.user_id = u.id
             LEFT JOIN users rev ON cert.reviewer_id = rev.id
             LEFT JOIN certification_groups g ON cert.group_id = g.id
             WHERE cert.id = ?",
            [$id]
        );
        $cert = $cert[0] ?? null;
        if (!$cert) { View::render('errors/404', [], 404); return; }
        // 解密身份证号（如有）
        $cert['id_card_plain'] = !empty($cert['id_card']) ? decrypt_value($cert['id_card']) : '';

        // 动态取本组认证项配置 + 该次申请填写的 form_data
        $groupId = (int)($cert['group_id'] ?? 1);
        $items = [];
        try {
            $items = Model::table('certification_items')->where('group_id', $groupId)->where('status', 1)->orderBy('sort_order', 'ASC')->get();
        } catch (Exception $e) {}
        $formData = !empty($cert['form_data']) ? (json_decode($cert['form_data'], true) ?: []) : [];
        // 兼容老数据：把固定列也并入 formData（不修改原值，仅作为展示用）
        foreach (['real_name','phone','extra_note'] as $k) {
            if (!isset($formData[$k]) && !empty($cert[$k])) $formData[$k] = $cert[$k];
        }
        if (!isset($formData['id_card']) && !empty($cert['id_card_plain'])) $formData['id_card'] = $cert['id_card_plain'];

        $this->view('admin/cert_detail', ['cert' => $cert, 'items' => $items, 'formData' => $formData, 'groupId' => $groupId]);
    }

    public function approveCert($id = null)
    {
        if (!can('certification.review')) $this->error('无权限：未授予「审核认证」');
        $id = (int)($id ?? input('id'));
        $cert = Model::table('certifications')->where('id', $id)->where('status', 0)->first();
        if (!$cert) $this->error('申请不存在或已处理');

        $groupId = (int)($cert['group_id'] ?? 1);
        $groupName = '实名认证';
        try {
            $g = Model::table('certification_groups')->where('id', $groupId)->first();
            if ($g && !empty($g['name'])) $groupName = $g['name'];
        } catch (Exception $e) {}

        $now = date('Y-m-d H:i:s');
        Model::table('certifications')->where('id', $id)->update([
            'status' => 1, 'reviewer_id' => Auth::id(), 'reviewed_at' => $now, 'updated_at' => $now,
        ]);
        // 默认组（实名认证）通过时，标记用户为已认证 + 升级角色；其它组不升级角色
        if ($groupId === 1) {
            Model::table('users')->where('id', $cert['user_id'])->update([
                'is_certified' => 1, 'certified_at' => $now,
            ]);
            $user = Model::table('users')->where('id', $cert['user_id'])->first();
            if ($user && $user['role'] === 'user') {
                Model::table('users')->where('id', $cert['user_id'])->update(['role' => 'certified_user']);
            }
        }
        // 积分：通过认证审核获取 Token + Byte（幂等，source_id = 认证申请 id）
        PointService::earnAction($cert['user_id'], 'certified', $id, '通过认证审核');
        // 通知（带组名）
        Model::table('notifications')->insert([
            'user_id' => $cert['user_id'], 'type' => 'certification',
            'content' => '恭喜，您的「' . $groupName . '」已通过审核', 'link' => url('certification/index', ['group_id' => $groupId]),
            'is_read' => 0, 'created_at' => $now,
        ]);
        $this->success(null, '「' . $groupName . '」已通过');
    }

    public function rejectCert($id = null)
    {
        if (!can('certification.review')) $this->error('无权限：未授予「审核认证」');
        $id = (int)($id ?? input('id'));
        $reason = trim(input('reject_reason'));
        if (!$reason) $this->error('请填写驳回原因');
        $cert = Model::table('certifications')->where('id', $id)->where('status', 0)->first();
        if (!$cert) $this->error('申请不存在或已处理');

        $groupId = (int)($cert['group_id'] ?? 1);
        $groupName = '实名认证';
        try {
            $g = Model::table('certification_groups')->where('id', $groupId)->first();
            if ($g && !empty($g['name'])) $groupName = $g['name'];
        } catch (Exception $e) {}

        $now = date('Y-m-d H:i:s');
        Model::table('certifications')->where('id', $id)->update([
            'status' => 2, 'reject_reason' => $reason, 'reviewer_id' => Auth::id(),
            'reviewed_at' => $now, 'updated_at' => $now,
        ]);
        Model::table('notifications')->insert([
            'user_id' => $cert['user_id'], 'type' => 'certification',
            'content' => '您的「' . $groupName . '」未通过，原因：' . $reason, 'link' => url('certification/index', ['group_id' => $groupId]),
            'is_read' => 0, 'created_at' => $now,
        ]);
        $this->success(null, '已驳回「' . $groupName . '」并通知用户');
    }

    public function batchCert()
    {
        if (!can('certification.review')) $this->error('无权限：未授予「审核认证」');
        $ids = input('ids', []);
        $action = input('action', 'approve');
        if (empty($ids)) $this->error('请选择申请');
        $count = 0;
        foreach ((array)$ids as $cid) {
            $cert = Model::table('certifications')->where('id', (int)$cid)->where('status', 0)->first();
            if (!$cert) continue;
            if ($action === 'approve') {
                $this->approveCert((int)$cid);
            } else {
                $_POST['reject_reason'] = input('reject_reason', '批量驳回');
                $this->rejectCert((int)$cid);
            }
            $count++;
        }
        $this->success(['count' => $count], '已批量处理 ' . $count . ' 条');
    }

    /* ========== 举报管理 ========== */
    public function reports()
    {
        $page = (int)input('page', 1);
        $status = input('status', '');
        $sql = "SELECT r.*, u.nickname AS reporter_name, ru.nickname AS handler_name FROM reports r LEFT JOIN users u ON r.reporter_id = u.id LEFT JOIN users ru ON r.handler_id = ru.id";
        $params = [];
        if ($status !== '') {
            $sql .= " WHERE r.status = ?";
            $params[] = (int)$status;
        }
        $sql .= " ORDER BY r.created_at DESC";
        $perPage = 20;
        $countSql = preg_replace('/SELECT.*FROM/i', 'SELECT COUNT(*) AS cnt FROM', $sql, 1);
        $stmt = Database::pdo()->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetch()['cnt'];
        $sql .= ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $reports = $stmt->fetchAll();
        foreach ($reports as &$r) {
            if ($r['target_type'] === 'post') {
                $r['view_url'] = url('post/show', ['id' => $r['target_id']]);
                $r['anchor'] = '';
            } else {
                $postId = Model::scalar('SELECT post_id FROM comments WHERE id = ?', [$r['target_id']]);
                $r['view_url'] = $postId ? url('post/show', ['id' => $postId]) : '';
                $r['anchor'] = 'comment-' . $r['target_id'];
            }
        }
        unset($r);
        $this->view('admin/reports', ['reports' => $reports, 'total' => $total, 'page' => $page, 'status' => $status]);
    }

    public function handleReport($id = null)
    {
        $id = (int)($id ?? input('id'));
        $result = trim(input('handle_result'));
        $action = input('action', 'resolved'); // resolved / dismissed
        $report = Model::table('reports')->where('id', $id)->first();
        if (!$report) $this->error('举报不存在');
        $status = $action === 'resolved' ? 1 : 2;
        Model::table('reports')->where('id', $id)->update([
            'status' => $status, 'handler_id' => Auth::id(), 'handle_result' => $result,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        // 如果处理为已解决，可删除目标内容
        if ($action === 'resolved' && $result) {
            $table = $report['target_type'] === 'post' ? 'posts' : 'comments';
            Model::table($table)->where('id', $report['target_id'])->update(['status' => 0, 'deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => Auth::id()]);
            // 违规处理：回收积分（token + byte 一起退）
            if ($report['target_type'] === 'post') {
                \PointService::refundPostPoints((int)$report['target_id']);
            } else {
                \PointService::refundBySource('comment_create', (int)$report['target_id']);
                \PointService::refundBySource('reply_create', (int)$report['target_id']);
                \PointService::refundBySource('post_liked', (int)$report['target_id']);
            }
        }

        // 通知举报者：resolved=已删除并反馈原因；dismissed=驳回/不成立
        if (!empty($report['reporter_id'])) {
            $targetLabel = $report['target_type'] === 'post' ? '帖子' : '评论';
            $content = $action === 'resolved'
                ? '您举报的' . $targetLabel . '已被管理员处理，处理说明：' . ($result ?: '违规')
                : '您的举报已审核不成立，处理说明：' . ($result ?: '经核实未违规');
            // 评论类举报需去 comments 表里查回 post_id 才能精准跳转回原帖
            $postIdForLink = null;
            if ($report['target_type'] === 'comment') {
                $crow = Model::table('comments')->select('post_id')->where('id', (int)$report['target_id'])->first();
                $postIdForLink = $crow ? (int)$crow['post_id'] : null;
            }
            $link = $report['target_type'] === 'post'
                ? url('post/show', ['id' => (int)$report['target_id']])
                : ($postIdForLink ? url('post/show', ['id' => $postIdForLink]) . '#comment-' . (int)$report['target_id'] : '');
            try {
                Model::table('notifications')->insert([
                    'user_id' => (int)$report['reporter_id'],
                    'type'    => 'report',
                    'content' => mb_substr($content, 0, 250),
                    'link'    => $link,
                    'is_read' => 0,
                    'is_system_notification' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $e) { /* 通知失败不影响举报处理 */ }
        }

        $this->success(null, '举报已处理');
    }

    /* ========== 回收站（内容管理） ========== */
    public function recycleBin()
    {
        if (!is_high_admin()) { flash('仅管理员可访问回收站', 'error'); redirect(url('admin/index')); return; }
        // 打开回收站即触发清理：超过 7 天的自动物理清空
        $this->cleanupExpiredTrash();

        $perPage = 20;
        $postPage = max(1, (int)($_GET['post_page'] ?? 1));
        $commentPage = max(1, (int)($_GET['comment_page'] ?? 1));

        // 帖子：分页 + JOIN users 拿作者昵称 + JOIN users2 拿操作人昵称
        $postTotal = (int)Model::scalar("SELECT COUNT(*) FROM posts WHERE status = 0 AND deleted_at IS NOT NULL");
        $postOffset = ($postPage - 1) * $perPage;
        $posts = Model::query(
            "SELECT p.id, p.title, p.user_id, p.category_id, p.deleted_at, p.deleted_by,
                    u.nickname AS author_name,
                    u2.nickname AS operator_name, u2.username AS operator_username,
                    c.name AS category_name
             FROM posts p
             LEFT JOIN users u ON p.user_id = u.id
             LEFT JOIN users u2 ON p.deleted_by = u2.id
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.status = 0 AND p.deleted_at IS NOT NULL
             ORDER BY p.deleted_at DESC
             LIMIT {$perPage} OFFSET {$postOffset}"
        );

        // 评论：分页 + JOIN users 拿评论者昵称 + JOIN users2 拿操作人昵称
        $commentTotal = (int)Model::scalar("SELECT COUNT(*) FROM comments WHERE status = 0 AND deleted_at IS NOT NULL");
        $commentOffset = ($commentPage - 1) * $perPage;
        $comments = Model::query(
            "SELECT cm.id, cm.post_id, cm.content, cm.user_id, cm.deleted_at, cm.deleted_by,
                    u.nickname AS author_name,
                    u2.nickname AS operator_name, u2.username AS operator_username,
                    p.title AS post_title
             FROM comments cm
             LEFT JOIN users u ON cm.user_id = u.id
             LEFT JOIN users u2 ON cm.deleted_by = u2.id
             LEFT JOIN posts p ON cm.post_id = p.id
             WHERE cm.status = 0 AND cm.deleted_at IS NOT NULL
             ORDER BY cm.deleted_at DESC
             LIMIT {$perPage} OFFSET {$commentOffset}"
        );

        // 简易翻页渲染器（保留 tab 类型 query 不被覆盖）
        $pageUrl = function($key, $n) {
            $qs = $_GET;
            $qs[$key] = $n;
            return '?' . http_build_query($qs);
        };
        $renderPager = function($total, $page, $key) use ($perPage, $pageUrl) {
            if ($total <= $perPage) return '';
            $pages = (int)ceil($total / $perPage);
            $html = '<div class="pager" style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap;align-items:center;font-size:13px;">';
            $html .= '<span class="text-muted">共 ' . $total . ' 条 · 第 ' . $page . '/' . $pages . ' 页</span>';
            if ($page > 1) {
                $html .= '<a href="' . $pageUrl($key, 1) . '" style="padding:2px 8px;border:1px solid #eee;border-radius:4px;color:#666;">« 首页</a>';
                $html .= '<a href="' . $pageUrl($key, $page - 1) . '" style="padding:2px 8px;border:1px solid #eee;border-radius:4px;color:#666;">‹ 上一页</a>';
            }
            $start = max(1, $page - 2);
            $end = min($pages, $page + 2);
            for ($i = $start; $i <= $end; $i++) {
                if ($i === $page) {
                    $html .= '<span style="padding:2px 10px;background:#ea6f5a;color:#fff;border-radius:4px;">' . $i . '</span>';
                } else {
                    $html .= '<a href="' . $pageUrl($key, $i) . '" style="padding:2px 10px;border:1px solid #eee;border-radius:4px;color:#666;">' . $i . '</a>';
                }
            }
            if ($page < $pages) {
                $html .= '<a href="' . $pageUrl($key, $page + 1) . '" style="padding:2px 8px;border:1px solid #eee;border-radius:4px;color:#666;">下一页 ›</a>';
                $html .= '<a href="' . $pageUrl($key, $pages) . '" style="padding:2px 8px;border:1px solid #eee;border-radius:4px;color:#666;">末页 »</a>';
            }
            $html .= '</div>';
            return $html;
        };

        $this->view('admin/recycle_bin', [
            'posts' => $posts,
            'comments' => $comments,
            'postPage' => $postPage,
            'postTotal' => $postTotal,
            'postPages' => (int)ceil(max($postTotal, 1) / $perPage),
            'commentPage' => $commentPage,
            'commentTotal' => $commentTotal,
            'commentPages' => (int)ceil(max($commentTotal, 1) / $perPage),
            'renderPager' => $renderPager,
        ]);
    }

    private function cleanupExpiredTrash()
    {
        $expire = date('Y-m-d H:i:s', time() - 7 * 86400);
        // 物理清空前回收积分：被清理的视为"违规内容最终处置"，与运营 emptyTrash 行为一致
        $expiredPosts = Model::query("SELECT id FROM posts WHERE status = 0 AND deleted_at IS NOT NULL AND deleted_at < ?", [$expire]);
        foreach ($expiredPosts as $p) {
            \PointService::refundPostPoints((int)$p['id']);
        }
        $expiredComments = Model::query("SELECT id FROM comments WHERE status = 0 AND deleted_at IS NOT NULL AND deleted_at < ?", [$expire]);
        foreach ($expiredComments as $c) {
            $cid = (int)$c['id'];
            \PointService::refundBySource('comment_create', $cid);
            \PointService::refundBySource('reply_create', $cid);
            \PointService::refundBySource('post_liked', $cid);
        }
        Model::execute("DELETE FROM posts WHERE status = 0 AND deleted_at IS NOT NULL AND deleted_at < ?", [$expire]);
        Model::execute("DELETE FROM comments WHERE status = 0 AND deleted_at IS NOT NULL AND deleted_at < ?", [$expire]);
    }

    public function restorePost($id = null)
    {
        if (!is_high_admin()) $this->error('无权限');
        $id = (int)($id ?? input('id'));
        Model::table('posts')->where('id', $id)->update(['status' => 1, 'deleted_at' => null, 'updated_at' => date('Y-m-d H:i:s')]);
        Model::query("UPDATE comments SET status = 1, deleted_at = NULL WHERE post_id = ? AND status = 0 AND deleted_at IS NOT NULL", [$id]);
        // 恢复帖子 → 把之前删除时回收的 token+byte 退还给帖子作者 + 评论者 + 被赞者（幂等）
        \PointService::refundReversePostPoints($id);
        $this->success(null, '帖子已恢复，关联积分已退还');
    }

    public function purgePost($id = null)
    {
        if (!is_high_admin()) $this->error('无权限');
        $id = (int)($id ?? input('id'));
        // 物理删除前回收积分（彻底删仍是内容被删除，违规语境）
        \PointService::refundPostPoints($id);
        Model::execute("DELETE FROM comments WHERE post_id = ? AND status = 0 AND deleted_at IS NOT NULL", [$id]);
        Model::execute("DELETE FROM posts WHERE id = ?", [$id]);
        $this->success(null, '帖子已彻底删除');
    }

    public function restoreComment($id = null)
    {
        if (!is_high_admin()) $this->error('无权限');
        $id = (int)($id ?? input('id'));
        Model::table('comments')->where('id', $id)->update(['status' => 1, 'deleted_at' => null, 'updated_at' => date('Y-m-d H:i:s')]);
        // 恢复评论 → 把之前删除时回收的 3 个 source 积分退还给原作者/被赞者（幂等）
        \PointService::refundReverseCommentPoints($id);
        $this->success(null, '评论已恢复，关联积分已退还');
    }

    public function purgeComment($id = null)
    {
        if (!is_high_admin()) $this->error('无权限');
        $id = (int)($id ?? input('id'));
        // 物理删除前回收积分
        \PointService::refundBySource('comment_create', $id);
        \PointService::refundBySource('reply_create', $id);
        \PointService::refundBySource('post_liked', $id);
        Model::execute("DELETE FROM comments WHERE id = ?", [$id]);
        $this->success(null, '评论已彻底删除');
    }

    public function emptyTrash()
    {
        if (!is_high_admin()) $this->error('无权限');
        // 清空回收站前对每一项都做积分回收（运营"清空"等同于批量违规删除）
        $trashPosts = Model::query("SELECT id FROM posts WHERE status = 0 AND deleted_at IS NOT NULL");
        foreach ($trashPosts as $tp) {
            \PointService::refundPostPoints((int)$tp['id']);
        }
        $trashComments = Model::query("SELECT id FROM comments WHERE status = 0 AND deleted_at IS NOT NULL");
        foreach ($trashComments as $tc) {
            \PointService::refundBySource('comment_create', (int)$tc['id']);
            \PointService::refundBySource('reply_create', (int)$tc['id']);
            \PointService::refundBySource('post_liked', (int)$tc['id']);
        }
        $this->cleanupExpiredTrash();
        Model::execute("DELETE FROM posts WHERE status = 0 AND deleted_at IS NOT NULL");
        Model::execute("DELETE FROM comments WHERE status = 0 AND deleted_at IS NOT NULL");
        $this->success(null, '回收站已清空');
    }

    /* ========== 角色权限（仅站长） ========== */
    public function roles()
    {
        if (!is_webmaster()) { flash('仅站长可访问「角色权限」', 'error'); redirect(url('admin/index')); return; }
        $roles = Model::table('roles')->get();
        $permissions = Model::table('permissions')->get();
        // 各角色已分配的权限
        $rolePerms = [];
        foreach ($roles as $r) {
            $perms = Model::query("SELECT permission_id FROM role_permissions WHERE role_id = ?", [$r['id']]);
            $rolePerms[$r['id']] = array_column($perms, 'permission_id');
        }
        // ✦ 角色列表排序（?sort=level|name|code|created_desc|created_asc，默认 level）
        //   level：按硬编码权限等级（webmaster > super_admin > admin > moderator > certified_user > user > guest > 自定义）
        $sort = (string)input('sort', 'level');
        $levelOrder = [
            'webmaster' => 0, 'super_admin' => 1, 'admin' => 2,
            'moderator' => 3, 'certified_user' => 4, 'user' => 5, 'guest' => 6,
        ];
        $levelOf = function ($code) use ($levelOrder) {
            return isset($levelOrder[$code]) ? $levelOrder[$code] : 99; // 自定义角色排在内置之后
        };
        switch ($sort) {
            case 'name':
                usort($roles, function ($a, $b) { return strcmp((string)$a['name'], (string)$b['name']); });
                break;
            case 'code':
                usort($roles, function ($a, $b) { return strcmp((string)$a['code'], (string)$b['code']); });
                break;
            case 'created_desc':
                usort($roles, function ($a, $b) { return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')); });
                break;
            case 'created_asc':
                usort($roles, function ($a, $b) { return strcmp((string)($a['created_at'] ?? ''), (string)($b['created_at'] ?? '')); });
                break;
            case 'level':
            default:
                usort($roles, function ($a, $b) use ($levelOf) {
                    $la = $levelOf((string)$a['code']); $lb = $levelOf((string)$b['code']);
                    if ($la !== $lb) return $la - $lb;
                    return strcmp((string)$a['id'], (string)$b['id']); // 同级按 id 升序兜底
                });
                break;
        }
        $this->view('admin/roles', ['roles' => $roles, 'permissions' => $permissions, 'rolePerms' => $rolePerms, 'sort' => $sort]);
    }

    public function createRole()
    {
        if (!is_webmaster()) $this->error('仅站长可管理角色');
        $name = trim(input('name'));
        $code = trim(input('code'));
        $desc = trim(input('description'));
        if (!$name || !$code) $this->error('角色名称和编码不能为空');
        $exists = Model::table('roles')->where('code', $code)->first();
        if ($exists) $this->error('角色编码已存在');
        Model::table('roles')->insert([
            'name' => $name, 'code' => $code, 'description' => $desc, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->success(null, '角色已创建');
    }

    public function updateRolePermissions($id = null)
    {
        if (!is_webmaster()) $this->error('仅站长可管理角色');
        $id = (int)($id ?? input('id'));
        $role = Model::table('roles')->where('id', $id)->first();
        if (!$role) $this->error('角色不存在');
        // 站长（webmaster）权限为系统固定：强制写入所有权限，前端勾选即使被绕过也无效
        if ($role['code'] === 'webmaster') {
            $allPermIds = array_column(Model::table('permissions')->get(), 'id');
            Model::execute('DELETE FROM role_permissions WHERE role_id = ?', [$id]);
            foreach ($allPermIds as $pid) {
                Model::execute('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$id, (int)$pid]);
            }
            $this->success(null, '站长权限已固定为「全部开启」');
        }
        $permIds = input('permission_ids', []);
        $permIds = is_array($permIds) ? array_map('intval', $permIds) : [];
        // 兜底：拒绝写入已被下沉为「站长专属入口」的权限项（category.manage / system.manage），
        // 防止前端绕过。前端矩阵已不再展示这两项。
        $forbiddenCodes = ['category.manage', 'system.manage'];
        if (!empty($permIds)) {
            $place = implode(',', array_fill(0, count($forbiddenCodes), '?'));
            $forbidden = Model::query("SELECT id FROM permissions WHERE code IN ($place)", $forbiddenCodes);
            $forbiddenIds = array_map('intval', array_column($forbidden, 'id'));
            $permIds = array_values(array_diff($permIds, $forbiddenIds));
        }
        Model::execute('DELETE FROM role_permissions WHERE role_id = ?', [$id]);
        foreach ($permIds as $pid) {
            Model::execute('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$id, (int)$pid]);
        }
        $this->success(null, '权限已更新');
    }

    /* ========== 系统设置（仅站长） ========== */
    public function settings()
    {
        if (!is_webmaster()) { flash('仅站长可访问「系统设置」', 'error'); redirect(url('admin/index')); return; }
        $settings = Model::query("SELECT * FROM settings");
        $settingsMap = [];
        foreach ($settings as $s) $settingsMap[$s['key_name']] = $s['value'];

        // 角色列表（后台「邀请权限」模块按角色勾选）
        $roles = [];
        try {
            $roles = Model::table('roles')->orderBy('id', 'ASC')->get();
        } catch (Exception $e) {
            $roles = [];
        }

        // FA 图标对照表已升级为独立的「图标库」菜单（admin/icons），此处不再注入。
        $this->view('admin/settings', [
            'settings'   => $settingsMap,
            'roles'      => $roles,
        ]);
    }

    /**
     * Font Awesome 图标库（独立二级菜单；仅站长）
     *
     * 数据来源：Font Awesome 6.7.2 免费版（含 solid + regular + brands）经脚本生成的
     *   data/fa6/free_icons_categorized.json
     *   data/fa6/category_list.json
     * 模板：templates/admin/icons.php
     * 搜索/分类锚点/点击复制 FA6 类名由前端 JS 完成；此处仅注入数据集。
     */
    public function icons()
    {
        if (!is_webmaster()) { flash('仅站长可访问「图标库」', 'error'); redirect(url('admin/index')); return; }

        $dataFile     = ROOT_PATH . '/data/fa6/free_icons_categorized.json';
        $categoryFile = ROOT_PATH . '/data/fa6/category_list.json';

        $icons = [];
        $cats  = [];
        if (is_file($dataFile)) {
            $icons = json_decode((string)file_get_contents($dataFile), true) ?: [];
        }
        if (is_file($categoryFile)) {
            $cats = json_decode((string)file_get_contents($categoryFile), true) ?: [];
        }
        // 兜底：若数据文件缺失，用空集合并提示
        $dataMissing = !is_file($dataFile) || !is_file($categoryFile);

        $this->view('admin/icons', [
            'icons'       => $icons,
            'categories'  => $cats,
            'dataMissing' => $dataMissing,
        ]);
    }

    /**
     * 邮箱设置页（独立二级菜单；仅站长）
     */
    public function mailSettings()
    {
        if (!is_webmaster()) { flash('仅站长可访问「邮箱设置」', 'error'); redirect(url('admin/index')); return; }
        $settings = Model::query("SELECT * FROM settings");
        $settingsMap = [];
        foreach ($settings as $s) $settingsMap[$s['key_name']] = $s['value'];
        $this->view('admin/mail_settings', ['settings' => $settingsMap]);
    }

    /**
     * 特殊主题（仅站长）
     * ----------------------------------------------------------------
     * 把"拍卖帖"等按板块 + 角色组维度做发布权限控制的功能，统一收纳到此处。
     * 当前内置主题：auction（拍卖帖）/pay/event/bounty/poll/debate/interview。
     * 权限矩阵：每个 板块 × 角色组 两个布尔开关 ——
     *   - cat_allow[板块id]  ：是否允许在该板块发布该主题
     *   - role_allow[角色code]：是否允许该用户角色发布该主题
     * 任一开关未勾选 → 该维度禁止发布。
     * 自动同步：读取 categories / roles 真实列表渲染矩阵；保存时剔除已删除的
     * 板块/角色，新增的板块/角色自动出现在列表并以「默认允许」勾选。
     *
     * 2026-08-29 重构：每个主题单独一个 tab，各自保存。新增 ?tab= 切 tab，
     *   新增 saveSpecialTheme(?theme=xxx) 单主题保存接口（替换老的 saveSpecialThemes）。
     */
    public function specialThemes()
    {
        if (!is_webmaster()) { flash('仅站长可访问「特殊主题」', 'error'); redirect(url('admin/index')); return; }

        $cats  = Model::table('categories')->where('status', 1)->orderBy('sort_order', 'ASC')->get();
        $roles = Model::table('roles')->orderBy('id', 'ASC')->get();

        // 读取已存配置（按主题分组）
        $stored = Model::table('settings')->where('key_name', 'special_themes')->first();
        $cfg = $stored ? json_decode($stored['value'] ?? '', true) : [];
        if (!is_array($cfg)) $cfg = [];

        // 默认每个主题的所有板块 / 角色都允许（首次使用即全开，向后兼容"原来全员可发拍卖"）
        $themes = [
            'auction'   => '拍卖帖',
            'pay'       => '付费主题',
            'event'     => '活动主题',
            'bounty'    => '悬赏主题',
            'poll'      => '投票主题',
            'debate'    => '辩论主题',
            'interview' => '采访主题',
            'lottery'   => '抽奖帖',
        ];

        // 当前 tab（默认第一个主题）
        $themeKeys = array_keys($themes);
        $currentTab = isset($_GET['tab']) ? trim((string)$_GET['tab']) : $themeKeys[0];
        if (!in_array($currentTab, $themeKeys, true)) $currentTab = $themeKeys[0];

        $matrix = [];
        foreach ($themes as $tk => $tname) {
            $tCfg = isset($cfg[$tk]) && is_array($cfg[$tk]) ? $cfg[$tk] : [];
            $catAllow  = isset($tCfg['cat_allow'])  && is_array($tCfg['cat_allow'])  ? $tCfg['cat_allow']  : [];
            $roleAllow = isset($tCfg['role_allow']) && is_array($tCfg['role_allow']) ? $tCfg['role_allow'] : [];
            $matrix[$tk] = [
                'name'       => $tname,
                'cat_allow'  => $catAllow,
                'role_allow' => $roleAllow,
            ];
        }

        // 兼容迁移：若历史上在 settings 存过 auction 独立配置（auction_cat_allow / auction_role_allow），并入
        foreach (['auction'] as $tk) {
            foreach (['auction_cat_allow' => 'cat_allow', 'auction_role_allow' => 'role_allow'] as $oldKey => $newKey) {
                $row = Model::table('settings')->where('key_name', $oldKey)->first();
                if ($row && $row['value'] !== '') {
                    $dec = json_decode($row['value'], true);
                    if (is_array($dec)) {
                        foreach ($dec as $k => $v) $matrix[$tk][$newKey][$k] = $v;
                    }
                }
            }
        }

        $this->view('admin/special_themes', [
            'cats'       => $cats,
            'roles'      => $roles,
            'themes'     => $themes,
            'matrix'     => $matrix,
            'currentTab' => $currentTab,
        ]);
    }

    /**
     * 保存单个特殊主题的权限矩阵（每 tab 一个保存按钮。
     * 仅更新 special_themes JSON 中指定 theme_key 的 cat_allow/role_allow，
     * 其他主题保持不动 —— 这样每 tab 独立保存是安全的）。
     */
    public function saveSpecialTheme()
    {
        if (!is_webmaster()) $this->error('仅站长可保存「特殊主题」');
        if (!is_post()) $this->error('非法请求');

        $themes = [
            'auction'   => '拍卖帖',
            'pay'       => '付费主题',
            'event'     => '活动主题',
            'bounty'    => '悬赏主题',
            'poll'      => '投票主题',
            'debate'    => '辩论主题',
            'interview' => '采访主题',
            'lottery'   => '抽奖帖',
        ];
        $tk = input('theme', '');
        if (!isset($themes[$tk])) {
            $this->error('未知的主题：' . $tk);
        }
        $tname = $themes[$tk];

        $catAllow  = input($tk . '_cat_allow', []);
        $roleAllow = input($tk . '_role_allow', []);
        // 仅保留值为 "1"/"on"/true 的键；表单未勾选不产生键
        $catAllow  = is_array($catAllow)  ? array_filter($catAllow,  function ($v) { return $v == '1' || $v === 'on' || $v === true; }) : [];
        $roleAllow = is_array($roleAllow) ? array_filter($roleAllow, function ($v) { return $v == '1' || $v === 'on' || $v === true; }) : [];
        // 转字符串键，输出 (object){} 形态，与旧数据保持一致（避免被 JSON 编码成空数组 []，下一次解析时类型变化导致 is_array 误判）
        $newBlock = [
            'cat_allow'  => (object)array_combine(array_map('strval', array_keys($catAllow)),  array_fill(0, count($catAllow), 1)),
            'role_allow' => (object)array_combine(array_map('strval', array_keys($roleAllow)), array_fill(0, count($roleAllow), 1)),
        ];

        // 读旧 JSON（保持其他主题的数据不动）
        $stored = Model::table('settings')->where('key_name', 'special_themes')->first();
        $cfg = $stored ? json_decode($stored['value'] ?? '', true) : [];
        if (!is_array($cfg)) $cfg = [];
        $cfg[$tk] = $newBlock;

        $this->saveSetting('special_themes', json_encode($cfg, JSON_UNESCAPED_UNICODE), '特殊主题（拍卖帖等）按板块与角色组的发布权限矩阵');

        // 兼容旧独立键：迁移完成后置空，避免重复
        foreach (['auction_cat_allow', 'auction_role_allow'] as $oldKey) {
            Model::table('settings')->where('key_name', $oldKey)->delete();
        }

        $this->success(['theme' => $tk, 'name' => $tname], '「' . $tname . '」权限已保存');
    }

    /**
     * 导航设置页（独立二级菜单；仅站长）
     */
    public function navSettings()
    {
        if (!is_webmaster()) { flash('仅站长可访问「导航设置」', 'error'); redirect(url('admin/index')); return; }
        $settings = Model::query("SELECT * FROM settings");
        $settingsMap = [];
        foreach ($settings as $s) $settingsMap[$s['key_name']] = $s['value'];
        $this->view('admin/nav_settings', ['settings' => $settingsMap]);
    }

    /**
     * 保存站点信息设置（仅处理站点信息表单提交的字段，避免覆盖其他设置）
     * SEO 字段语义：
     * - site_title         站点主标题（≤60 字，<title> 主要部分 / 邮件签名）
     * - site_subtitle      站点副标题（slogan，做 description 兜底）
     * - site_description   Meta Description（80–160 字，搜索结果摘要，与 title 互补不重复）
     * - site_keywords      Meta Keywords（3–8 个关键词，英文逗号分隔）
     * - site_logo          顶部导航 Logo
     * - site_favicon       浏览器 favicon + 苹果主屏图标 apple-touch-icon（同一张）
     */
    public function saveSettings()
    {
        if (!is_webmaster()) $this->error('仅站长可保存系统设置');
        $certLimitDays = (int)input('cert_limit_days', 7);
        $siteDesc    = trim((string)input('site_description', ''));
        $siteKw      = trim((string)input('site_keywords', ''));
        $siteLogo    = trim((string)input('site_logo', ''));
        $siteFavicon = trim((string)input('site_favicon', ''));

        // 注册模式：open=开放 / closed=关闭 / invite=邀请（三态）
        $registerMode = trim((string)input('register_mode', 'open'));
        if (!in_array($registerMode, ['open', 'closed', 'invite'], true)) {
            $registerMode = 'open';
        }

        // 邀请权限：允许发起邀请的角色 code 列表
        $inviteRoles = input('invite_allowed_roles', []);
        if (!is_array($inviteRoles)) {
            $inviteRoles = $inviteRoles === '' ? [] : explode(',', $inviteRoles);
        }
        $inviteRoles = implode(',', array_filter(array_map('trim', $inviteRoles)));

        // 兼容旧逻辑：注册模式非「关闭」即视为允许注册（保留 allow_register 字段）
        $allowRegister = $registerMode === 'closed' ? 0 : 1;

        $this->saveSetting('cert_limit_days', $certLimitDays, '认证申请频率限制（天）');
        $this->saveSetting('register_mode', $registerMode, '注册模式（open开放/closed关闭/invite邀请）');
        $this->saveSetting('invite_allowed_roles', $inviteRoles, '允许邀请新用户的角色');
        $this->saveSetting('invite_enabled', $registerMode === 'invite' ? 1 : 0, '是否开启邀请注册');
        $this->saveSetting('site_description', $siteDesc, '站点描述（Meta Description）');
        $this->saveSetting('site_keywords',    $siteKw,   'SEO 关键词（Meta Keywords）');
        $this->saveSetting('allow_register', $allowRegister, '是否允许注册');
        $this->saveSetting('site_logo',    $siteLogo,    '网站Logo URL');
        $this->saveSetting('site_favicon', $siteFavicon, '浏览器图标 / 苹果主屏图标 URL');

        // 图片上传 / 附件上传 权限已迁移到「角色权限 → 角色列表与权限分配」
        // （image.upload / attachment.upload 两个权限码），不再走 settings。

        // 更新站点标题/副标题/Logo/favicon/SEO 到配置文件 config/site.php
        $title    = trim((string)input('site_title', ''));
        $subtitle = trim((string)input('site_subtitle', ''));
        if ($title) {
            $siteConfig = Config::get('site', []);
            $siteConfig['title']       = $title;
            $siteConfig['subtitle']    = $subtitle;
            // description 不再用 title + subtitle 拼凑（SEO 反模式），由后台单独维护
            $siteConfig['description'] = $siteDesc;
            $siteConfig['keywords']    = $siteKw;
            $siteConfig['logo']        = $siteLogo;
            $siteConfig['favicon']     = $siteFavicon;
            $php = "<?php\nreturn " . var_export($siteConfig, true) . ";\n";
            file_put_contents(CONFIG_PATH . '/site.php', $php);
        }
        $this->success(null, '设置已保存');
    }

    /**
     * 保存版式设置（帖子列表版式：default=列表式 / card=卡片式）。
     * 写入 settings 表 post_layout，前台通过 setting('post_layout','default') 即时读取生效。
     */
    public function saveLayout()
    {
        if (!is_webmaster()) {
            $this->error('仅站长可保存版式设置');
            return;
        }
        if (!is_post()) {
            $this->error('非法请求');
            return;
        }
        $mode = trim((string)input('post_layout', 'default'));
        if (!in_array($mode, ['default', 'card'], true)) {
            $mode = 'default';
        }
        $this->saveSetting('post_layout', $mode, '帖子列表版式（default=列表 / card=卡片）');
        $this->success(null, '版式设置已保存');
    }

    /**
     * 保存邮箱设置（仅处理邮箱表单提交的字段）
     */
    public function saveMailSettings()
    {
        if (!is_webmaster()) $this->error('仅站长可保存邮箱设置');
        $this->saveSetting('email_verify_enabled', (int)input('email_verify_enabled', 0), '是否开启邮箱验证');
        $this->saveSetting('mail_from_name', trim(input('mail_from_name', '')), '发件人名称');
        $this->saveSetting('mail_from_email', trim(input('mail_from_email', '')), '发件人邮箱');
        $this->saveSetting('smtp_host', trim(input('smtp_host', '')), 'SMTP主机');
        $this->saveSetting('smtp_port', (int)input('smtp_port', 25), 'SMTP端口');
        $this->saveSetting('smtp_user', trim(input('smtp_user', '')), 'SMTP用户名');
        $this->saveSetting('smtp_pass', trim(input('smtp_pass', '')), 'SMTP密码');
        $this->saveSetting('smtp_secure', trim(input('smtp_secure', '')), 'SMTP加密方式');
        $this->success(null, '邮箱设置已保存');
    }

    /**
     * 保存导航设置（仅处理导航表单提交的字段）
     */
    public function saveNavSettings()
    {
        if (!is_webmaster()) $this->error('仅站长可保存导航设置');

        // 侧边导航标题（可自定义）
        $sideNavTitle = trim(input('side_nav_title', ''));
        $this->saveSetting('side_nav_title', $sideNavTitle, '侧边导航标题');

        // 导航菜单（顶部 + 侧边），每项固定为 {name, icon, url}
        $navMenus = ['top' => [], 'side' => []];
        foreach (['top', 'side'] as $pos) {
            $rows = input('nav_' . $pos, []);
            if (!is_array($rows)) $rows = [];
            foreach ($rows as $r) {
                $name = trim((string)($r['name'] ?? ''));
                $icon = trim((string)($r['icon'] ?? ''));
                $url  = trim((string)($r['url']  ?? ''));
                if ($name === '' || $url === '') continue; // 必填项为空则忽略
                // 服务端归一防御：即便前端 JS _navCliClass() 出错或被绕过，
                // 服务端仍把 icon 强制走 fa_icon_class() → 输出 FA6 标准类名，杜绝 fa-- 双横杠。
                // fa_icon_class() 内部已有「入口自愈」逻辑，会把 fa--xxx 折叠为 fa-xxx。
                if (function_exists('fa_icon_class')) $icon = fa_icon_class($icon);
                $navMenus[$pos][] = [
                    'name' => $name,
                    'icon' => $icon,
                    'url'  => $url,
                ];
            }
        }
        $this->saveSetting('nav_menus', json_encode($navMenus, JSON_UNESCAPED_UNICODE), '导航菜单（顶部 + 侧边）');

        // 卡片导航（扁平分组式分组二级导航，仅前台版式=卡片时生效）
        // 结构：{primary: [{name, icon, url}]×2, groups: [{title, icon, items: [{name, icon, url}]}]}
        $cardNav = ['groups' => [], 'primary' => []];
        // 主入口（首页/发布，默认预留可修改）
        $cardPrimary = input('card_primary', []);
        if (is_string($cardPrimary)) $cardPrimary = json_decode($cardPrimary, true);
        if (is_array($cardPrimary)) {
            foreach ($cardPrimary as $r) {
                if (!is_array($r)) continue;
                $name = trim((string)($r['name'] ?? ''));
                $icon = trim((string)($r['icon'] ?? ''));
                $url  = trim((string)($r['url']  ?? ''));
                if ($name === '' || $url === '') continue;
                if (function_exists('fa_icon_class')) $icon = fa_icon_class($icon);
                $cardNav['primary'][] = ['name' => $name, 'icon' => $icon, 'url' => $url];
            }
        }
        $cardGroups = input('card_groups', []);
        if (is_string($cardGroups)) $cardGroups = json_decode($cardGroups, true);
        if (is_array($cardGroups)) {
            foreach ($cardGroups as $g) {
                if (!is_array($g)) continue;
                $gTitle = trim((string)($g['title'] ?? ''));
                if ($gTitle === '') continue; // 分组标题必填
                $gIcon = trim((string)($g['icon'] ?? ''));
                if (function_exists('fa_icon_class')) $gIcon = fa_icon_class($gIcon);
                $items = [];
                foreach (($g['items'] ?? []) as $r) {
                    if (!is_array($r)) continue;
                    $name = trim((string)($r['name'] ?? ''));
                    $icon = trim((string)($r['icon'] ?? ''));
                    $url  = trim((string)($r['url']  ?? ''));
                    if ($name === '' || $url === '') continue;
                    if (function_exists('fa_icon_class')) $icon = fa_icon_class($icon);
                    $items[] = ['name' => $name, 'icon' => $icon, 'url' => $url];
                }
                $cardNav['groups'][] = ['title' => $gTitle, 'icon' => $gIcon, 'items' => $items];
            }
        }
        $this->saveSetting('card_nav', json_encode($cardNav, JSON_UNESCAPED_UNICODE), '卡片导航（扁平分组式分组，仅卡片版式生效）');
        $this->success(null, '导航设置已保存');
    }

    /**
     * 保存卡片版式首页内容（轮播图 slides + 推荐位 recommends，各 {image, url}）。
     * 仅前台版式=卡片时渲染；写 settings 表 home_carousel。
     */
    public function saveHomeCarousel()
    {
        if (!is_webmaster()) $this->error('仅站长可保存首页轮播设置');
        if (!is_post()) { $this->error('非法请求'); return; }
        $norm = function ($rows) {
            if (!is_array($rows)) return [];
            $out = [];
            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                $img = trim((string)($r['image'] ?? ''));
                $url = trim((string)($r['url'] ?? ''));
                if ($img === '') continue; // 图片必填
                // 只接受 uploads/ 相对路径或 http(s) 绝对 URL
                if (strpos($img, 'uploads/') !== 0 && !preg_match('#^https?://#i', $img)) continue;
                $out[] = ['image' => $img, 'url' => $url];
            }
            return $out;
        };
        $data = [
            'slides'     => array_slice($norm(input('slides', [])), 0, 8),      // 轮播图最多 8 张
            'recommends' => array_slice($norm(input('recommends', [])), 0, 6),  // 推荐位固定最多 6 个
        ];
        $this->saveSetting('home_carousel', json_encode($data, JSON_UNESCAPED_UNICODE), '首页轮播图+推荐位（仅卡片版式生效）');
        $this->success(['slides' => count($data['slides']), 'recommends' => count($data['recommends'])], '首页轮播设置已保存');
    }

    protected function saveSetting($key, $value, $desc = '')
    {
        $existing = Model::table('settings')->where('key_name', $key)->first();
        if ($existing) {
            Model::table('settings')->where('key_name', $key)->update(['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            Model::table('settings')->insert([
                'key_name' => $key, 'value' => $value, 'description' => $desc, 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * 保存验证码设置（系统管理 → 验证码）。
     * 写入 settings 表：captcha_enabled / captcha_type / captcha_scenes(JSON)。
     * 频率限制 / 失败锁定 / 攻击日志相关字段（captcha_rate_limit / captcha_lockout / captcha_log）
     * 已删除（2026-08-27 用户反馈：实际作用不大）。
     */
    public function saveCaptcha()
    {
        if (!is_webmaster()) { $this->error('仅站长可保存「验证码设置」'); return; }
        if (!is_post()) { $this->error('非法请求'); return; }

        $enabled = input('captcha_enabled') ? '1' : '0';
        $type = input('captcha_type') ?: 'slider';

        $scenes = [];
        foreach (['register', 'login', 'post', 'comment'] as $s) {
            if (input('scene_' . $s)) $scenes[$s] = 1;
        }
        if (empty($scenes)) $scenes = ['register' => 1]; // 至少开一个，避免全关等于未配置

        $this->saveSetting('captcha_enabled', $enabled, '验证码总开关');
        $this->saveSetting('captcha_type', $type, '验证码类型（slider=滑块拼图）');
        $this->saveSetting('captcha_scenes', json_encode($scenes, JSON_UNESCAPED_UNICODE), '各场景开关（注册/登录/发帖/评论）');

        $this->success(null, '验证码设置已保存');
    }

    /**
     * ========== 积分系统（仅站长）==========
     * 后台「系统管理 → 积分系统」：查看并编辑积分规则（数值/单日上限/启停）+ 风控配置 + 会员等级配置。
     * 通过 ?tab=points|levels 切换 tab，?tab=levels 显示等级配置区块。
     */
    public function pointsSystem()
    {
        if (!is_webmaster()) { flash('仅站长可访问「积分系统」', 'error'); redirect(url('admin/index')); return; }
        extract($this->loadPointsSystemBaseData());
        $this->view('admin/points_system', [
            'rules'         => $rules,
            'risk'          => $risk,
            'levels'        => $levels,
            'currencies'    => $currencies,
            'rewardCfg'     => $rewardCfg,
            'currencyCodes' => array_values(array_unique($currencyCodes)),
        ]);
    }

    /**
     * 积分系统 + 充值中心 共享的基础数据（规则/币种/等级/风控/打赏配置）。
     * 4 个充值子页都要复用，省去重复查询；积分系统主入口也走这里保持一致性。
     */
    private function loadPointsSystemBaseData()
    {
        $rules = Model::table('point_rules')->orderBy('type')->orderBy('id')->get();
        $currencyCodes = [];
        foreach (\PointService::currencies(false) as $cur) {
            $currencyCodes[] = (string)$cur['code'];
        }
        foreach ($rules as &$rr) {
            $rr['base_action'] = self::extractRuleBaseAction($rr['code'], $currencyCodes);
        }
        unset($rr);
        $risk = \PointService::riskConfig();
        $levels = [];
        try {
            $levels = Model::table('user_levels')->orderBy('level')->get();
        } catch (\Throwable $e) {
            $levels = [];
        }
        $currencies = [];
        try {
            $currencies = \PointService::currencies(false);
        } catch (\Throwable $e) {
            $currencies = [];
        }
        $rewardCfg = \PointService::rewardConfig();
        return compact('rules', 'currencyCodes', 'risk', 'levels', 'currencies', 'rewardCfg');
    }

    /**
     * 批量保存会员等级配置（编辑现有等级）
     * 校验：level 数字唯一 / 名称非空 / byte_required 非负 / bonus_factor >= 1
     * 保存后清空 PointService 静态缓存，确保下次读库刷新
     */
    public function saveUserLevels()
    {
        if (!is_webmaster()) $this->error('仅站长可保存会员等级配置');
        if (!is_post()) $this->error('非法请求');

        $levels  = (array)input('level', []);
        $names   = (array)input('name', []);
        $bytes   = (array)input('byte_required', []);
        $factors = (array)input('bonus_factor', []);
        $privs   = (array)input('privilege_text', []);
        $enabled = (array)input('enabled', []);

        $ids = array_keys($names);
        if (empty($ids)) $this->success(null, '无可保存的等级');

        // 1. 检查等级数字唯一
        $lvInts = [];
        foreach ($ids as $id) {
            $lv = isset($levels[$id]) ? (int)$levels[$id] : 0;
            if ($lv <= 0) $this->error('等级数字必须为正整数');
            $lvInts[$id] = $lv;
        }
        if (count(array_unique($lvInts)) !== count($lvInts)) {
            $this->error('存在重复的等级数字');
        }

        // 2. 名称必填
        foreach ($ids as $id) {
            $nm = isset($names[$id]) ? trim((string)$names[$id]) : '';
            if ($nm === '') $this->error('等级名称不能为空');
        }

        // 3. 逐行保存
        $now = date('Y-m-d H:i:s');
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;
            $byte = max(0, (int)($bytes[$id] ?? 0));
            $factor = (float)($factors[$id] ?? 1.0);
            if ($factor < 1) $factor = 1.0;
            if ($factor > 9.99) $factor = 9.99;
            $factorStr = number_format($factor, 2, '.', '');
            $nm = mb_substr(trim((string)($names[$id] ?? '')), 0, 32);
            $priv = mb_substr(trim((string)($privs[$id] ?? '')), 0, 255);
            $lvN = (int)($lvInts[$id] ?? 1);
            $en = isset($enabled[$id]) ? 1 : 0;
            Model::table('user_levels')->where('id', $id)->update([
                'level'          => $lvN,
                'name'           => $nm,
                'byte_required'  => $byte,
                'bonus_factor'   => $factorStr,
                'privilege_text' => $priv,
                'enabled'        => $en,
                'updated_at'     => $now,
            ]);
        }
        \PointService::clearLevelCache();
        $this->success(null, '会员等级配置已保存');
    }

    /**
     * 新增一行等级（默认 Lv.{max+1}，初始阈值 0 / 1.00 / 空特权 / 启用）
     */
    public function addUserLevel()
    {
        if (!is_webmaster()) $this->error('仅站长可新增等级');
        if (!is_post()) $this->error('非法请求');

        // 找当前最大 level
        $maxLv = (int)Model::scalar('SELECT MAX(level) FROM user_levels');
        $newLv = (int)input('suggested_level', $maxLv + 1);
        if ($newLv <= 0) $newLv = $maxLv + 1;
        // 防重复：冲突时再 +1
        while (Model::table('user_levels')->where('level', $newLv)->first()) {
            $newLv++;
            if ($newLv > 9999) { $this->error('等级数字已达上限'); return; }
        }
        $now = date('Y-m-d H:i:s');
        $id = Model::table('user_levels')->insert([
            'level'          => $newLv,
            'name'           => 'Lv.' . $newLv,
            'byte_required'  => 0,
            'bonus_factor'   => '1.00',
            'privilege_text' => '',
            'enabled'        => 1,
            'sort_order'     => $newLv,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
        \PointService::clearLevelCache();
        $this->success(['id' => $id, 'level' => $newLv], '已新增等级 Lv.' . $newLv);
    }

    /**
     * 删除一行等级
     * 说明：仅移除等级阶梯定义；用户表 users.level 字段保留数字（前台展示依赖该数字，
     *       若用户等级已超过现有最高定义，仍按 maxLevel 上限展示）。
     */
    public function deleteUserLevel($id = null)
    {
        if (!is_webmaster()) $this->error('仅站长可删除等级');
        $id = (int)($id ?? input('id'));
        if ($id <= 0) $this->error('无效的等级');
        // 至少保留 1 级
        $cnt = (int)Model::scalar('SELECT COUNT(*) FROM user_levels');
        if ($cnt <= 1) $this->error('至少保留 1 个等级阶梯');
        Model::table('user_levels')->where('id', $id)->delete();
        \PointService::clearLevelCache();
        $this->success(null, '等级已删除');
    }

    public function savePointsSystem()
    {
        if (!is_webmaster()) { $this->error('仅站长可保存「积分系统」配置'); return; }
        if (!is_post()) { $this->error('非法请求'); return; }

        // 0) 解析待保存数据（先在内存里组装，不直接写 DB，以便先校验后写）
        $amounts  = (array)input('amount', []);
        $minVals  = (array)input('amount_min', []);
        $maxVals  = (array)input('amount_max', []);
        $caps     = (array)input('daily_cap', []);
        $enabled  = (array)input('enabled', []);
        $curs     = (array)input('currency', []);
        $pending = [];
        foreach ($amounts as $id => $amt) {
            $id = (int)$id;
            if ($id <= 0) continue;
            $cur = isset($curs[$id]) ? trim((string)$curs[$id]) : '';
            $pending[$id] = [
                'amount'     => (int)$amt,
                'amount_min' => isset($minVals[$id]) ? max(0, (int)$minVals[$id]) : 0,
                'amount_max' => isset($maxVals[$id]) ? max(0, (int)$maxVals[$id]) : 0,
                'daily_cap'  => isset($caps[$id]) ? (int)$caps[$id] : 0,
                'currency'   => $cur,
                'enabled'    => isset($enabled[$id]) ? 1 : 0,
            ];
        }

        // 0.5) 冲突校验：同一个动作（base action）的同一种币种不允许出现两条启用且 amount>0 的规则。
        // 规则约定：comment_create_byte / comment_create_<curcode> / comment_create 都属于同一个 base action = comment_create。
        // 例：comment_create = X(10) + comment_create_byte = X(20) 是非法的；但 comment_create = X(10) + post_liked = X(20) 是合法的（不同 base action）。
        $allRules = Model::table('point_rules')->get();
        $currencyCodes = [];
        foreach (\PointService::currencies(false) as $cur) {
            $currencyCodes[] = (string)$cur['code'];
        }
        $byBase = [];
        $conflicts = [];
        foreach ($allRules as $r) {
            $id = (int)$r['id'];
            $cur = isset($pending[$id]['currency']) ? $pending[$id]['currency'] : ($r['currency'] ?: '');
            if ($cur === '' || !\PointService::currencyExists($cur)) $cur = 'token';
            $amt = isset($pending[$id]['amount']) ? $pending[$id]['amount'] : (int)$r['amount'];
            $en  = isset($pending[$id]['enabled']) ? $pending[$id]['enabled'] : (int)$r['enabled'];
            if ($en !== 1 || $amt <= 0) continue;
            $base = self::extractRuleBaseAction($r['code'], $currencyCodes);
            $key = $base . '|' . $cur;
            if (isset($byBase[$key])) {
                $conflicts[] = [
                    'base'     => $base,
                    'currency' => $cur,
                    'codes'    => [$byBase[$key], $r['code']],
                    'ids'      => [$byBase[$key . '#id'] ?? 0, $id],
                ];
            } else {
                $byBase[$key] = $r['code'];
                $byBase[$key . '#id'] = $id;
            }
        }
        if (!empty($conflicts)) {
            $first = $conflicts[0];
            $label = \PointService::currencyLabel($first['currency']);
            $msg = '同一动作「' . $first['base'] . '」不允许同时给「' . $label . '」配两条启用的规则（' . implode(' 与 ', $first['codes']) . '）。请禁用其中一条或改用不同币种。';
            $this->error($msg, ['conflicts' => $conflicts]);
            return;
        }

        // 1) 规则数值 / 随机区间 / 单日上限 / 启停 / 币种（写入 DB）
        foreach ($pending as $id => $p) {
            $cur = $p['currency'] !== '' ? $p['currency'] : 'token';
            if (!\PointService::currencyExists($cur)) $cur = 'token';
            Model::table('point_rules')->where('id', $id)->update([
                'amount'     => $p['amount'],
                'amount_min' => $p['amount_min'],
                'amount_max' => $p['amount_max'],
                'daily_cap'  => $p['daily_cap'],
                'currency'   => $cur,
                'enabled'    => $p['enabled'],
            ]);
        }

        // 2) 风控配置
        $this->saveSetting('risk_newbie_hours',  (int)input('risk_newbie_hours', 24), '新注册账号风控时长（小时）：该时间内单日积分获取上限减半');
        $this->saveSetting('risk_newbie_factor', (float)input('risk_newbie_factor', 0.5), '新号积分额度系数（单日上限乘以该值，0.5=减半）');
        $this->saveSetting('risk_burst_count',   (int)input('risk_burst_count', 10), '高频熔断阈值：同一来源在 burst_window 秒内达到该次数即暂停当日获取');
        $this->saveSetting('risk_burst_window',  (int)input('risk_burst_window', 60), '高频熔断时间窗（秒）');

        // 3) 打赏配置（reward_default / reward_max_amount / reward_daily_count / reward_interval）
        $this->saveSetting('reward_default_amount', (int)input('reward_default_amount', 5), '打赏弹窗默认金额（≥ 5）');
        $this->saveSetting('reward_max_amount',     (int)input('reward_max_amount', 0),     '单笔打赏上限（0=不限）');
        $this->saveSetting('reward_daily_count',    (int)input('reward_daily_count', 0),    '单日打赏次数上限（0=不限）');
        $this->saveSetting('reward_interval',       (int)input('reward_interval', 0),       '两次打赏最小间隔秒数（0=不限）');

        $this->success(null, '积分系统配置已保存');
    }

    /**
     * 从 rule code 中提取 base action。
     * 约定：comment_create_byte / comment_create_v / comment_create 共享同一 base action = comment_create；
     *       register_byte 的 base = register；其它无后缀的 code（如 post_liked、user_followed）base = code 本身。
     * 用于「同 base action 不允许多条规则共用同一币种」的冲突检测。
     *
     * 关键：必须用「已知 currency codes 列表」精确匹配后缀，不能用通用正则
     *      （否则会把 _create、_liked 等动作后缀也错误地匹配为币种后缀，导致 post_create 与 post_liked 误判为同一 base）。
     *
     * @param string $code          规则 code
     * @param array  $currencyCodes 已启用的所有币种 code 列表（含 byte）
     */
    private static function extractRuleBaseAction($code, array $currencyCodes)
    {
        $code = (string)$code;
        if ($code === '') return '';
        // 按后缀长度降序排列，长后缀优先匹配（避免短后缀吞掉长后缀）。
        $suffixes = $currencyCodes;
        usort($suffixes, function ($a, $b) { return strlen($b) - strlen($a); });
        foreach ($suffixes as $cur) {
            $cur = (string)$cur;
            if ($cur === '') continue;
            $suffix = '_' . $cur;
            $len = strlen($suffix);
            if (strlen($code) > $len && substr($code, -$len) === $suffix) {
                return substr($code, 0, -$len);
            }
        }
        // 没有任何「_<已知币种 code>」后缀 → 视为 base 本身
        return $code;
    }

    /**
     * 保存币种（批量：编辑已有 + 新增共用）
     * 前端按 data-cur-id 组织为 name/code/type/symbol/sort_order/enabled 数组提交；
     * id>0 为更新，id='new'（非数字）为新增（需 code 且不冲突 token/byte）。
     * 保存后清空 PointService 币种缓存。
     */
    public function saveCurrency()
    {
        if (!is_webmaster()) { $this->error('仅站长可配置币种'); return; }
        if (!is_post()) { $this->error('非法请求'); return; }

        $names = (array)input('name', []);
        if (empty($names)) { $this->success(null, '无可保存的币种'); return; }
        $codes = (array)input('code', []);
        $types = (array)input('type', []);
        $syms  = (array)input('symbol', []);
        $sorts = (array)input('sort_order', []);
        $enab  = (array)input('enabled', []);
        $now = date('Y-m-d H:i:s');

        foreach (array_keys($names) as $key) {
            $id = is_numeric($key) ? (int)$key : 0;
            $name = trim((string)($names[$key] ?? ''));
            if ($name === '') continue;
            $type = isset($types[$key]) && in_array($types[$key], ['consumable', 'growth', 'normal'], true) ? $types[$key] : 'consumable';
            $symbol = mb_substr(trim((string)($syms[$key] ?? '')), 0, 8);
            $sort = (int)($sorts[$key] ?? 0);
            $enabled = isset($enab[$key]) ? 1 : 0;
            $data = ['name' => $name, 'type' => $type, 'symbol' => $symbol, 'sort_order' => $sort, 'enabled' => $enabled, 'updated_at' => $now];

            if ($id > 0) {
                Model::table('currencies')->where('id', $id)->update($data);
            } else {
                // 强制小写化（和货币派生/积分过滤统一用同一套正则）
                $code = isset($codes[$key]) ? trim((string)$codes[$key]) : '';
                $code = strtolower($code);
                if ($code === '' || !preg_match('/^[a-z][a-z0-9_]{0,30}$/', $code)) continue;
                if (in_array($code, ['token', 'byte'], true)) continue;
                if (Model::table('currencies')->where('code', $code)->first()) continue;
                $data['code'] = $code;
                $data['is_system'] = 0;
                $data['created_at'] = $now;
                Model::table('currencies')->insert($data);
            }
        }
        \PointService::clearCurrencyCache();
        $this->success(null, '币种已保存');
    }

    /**
     * 删除自定义币种（系统内置 token/byte 不可删）
     */
    public function deleteCurrency($id = null)
    {
        if (!is_webmaster()) { $this->error('仅站长可删除币种'); return; }
        $id = (int)($id ?? input('id'));
        if ($id <= 0) $this->error('无效的币种');
        $c = Model::table('currencies')->where('id', $id)->first();
        if (!$c) $this->error('币种不存在');
        if ((int)$c['is_system'] === 1) $this->error('系统内置币种不可删除');
        Model::table('currencies')->where('id', $id)->delete();
        $code = (string)$c['code'];
        // 1) 清理该币种的派生规则：所有 code 以 "_<code>" 精确结尾的规则（如 post_create_v / comment_create_v）。
        //    用 REGEXP 锚定后缀，避免误删 post_liked / user_followed 等基础规则。
        $rulePattern = '_' . preg_quote($code, '/') . '$';
        $deletedRules = Model::execute('DELETE FROM point_rules WHERE code REGEXP ?', [$rulePattern]);
        // 2) 清理该币种在 user_balances 中的余额记录（用户已不可见，留着也无意义）
        $deletedBalances = Model::execute('DELETE FROM user_balances WHERE currency = ?', [$code]);
        // 注：points_log 历史流水保留（删了会破坏已发放积分的统计一致性）；余额已不可见故无影响
        \PointService::clearCurrencyCache();
        $this->success(null, '币种「' . $code . '」已删除（已清理 ' . $deletedRules . ' 条派生规则、' . $deletedBalances . ' 条余额记录，不保留脏数据）');
    }

    /**
     * 为指定币种一键派生所有已有 point_rules 规则的 xxx_{code} 副本
     * 用途：用户在「币种管理」新增一个自定义币种后，一次性把所有动作（register/post_create/...）复制出
     *      对应的「{动作}_{币种代码}」规则行（amount 默认 0，enabled 默认 0，币种选新币种），管理员
     *      到「积分规则配置」手动设置数值与上限后启用即可。
     * 跳过已存在的同名规则（幂等）；不影响已有 earned 流水。
     */
    public function mirrorCurrencyRules()
    {
        if (!is_webmaster()) { $this->error('仅站长可派生规则'); return; }
        if (!is_post()) { $this->error('非法请求'); return; }
        // 容错：历史数据里 code 可能含大写（saveCurrency 老正则放行过大写），派生时一律转小写
        $rawCode = (string)input('code', '');
        $code = strtolower(trim($rawCode));
        if (!preg_match('/^[a-z][a-z0-9_]{0,30}$/', $code)) {
            // 写日志方便排查：原始值与转小写后的值各打一份（避免控制台反射）
            error_log('[mirrorCurrencyRules] invalid code: raw=' . var_export($rawCode, true) . ' normalized=' . var_export($code, true));
            $this->error('币种 code 不合法（仅允许小写字母/数字/下划线，且必须以字母开头；请到「php_error.log」查看实际收到的值）');
            return;
        }
        if (!\PointService::currencyExists($code)) {
            $this->error('币种不存在');
            return;
        }
        // 读 point_rules.code 列（一维字符串数组），用于去重与组装派生 code
        $rows = Model::table('point_rules')->select('code')->get();
        $existing = array_map(function ($r) { return (string)$r['code']; }, $rows);
        $created = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($existing as $base) {
            $newCode = $base . '_' . $code;
            if (in_array($newCode, $existing, true)) {
                $skipped++;
                continue;
            }
            $rule = Model::table('point_rules')->where('code', $base)->first();
            if (!$rule) { $skipped++; continue; }
            Model::table('point_rules')->insert([
                'code'        => $newCode,
                'name'        => '[自动派生][' . $code . ']' . $rule['name'],
                'type'        => $rule['type'],
                'currency'    => $code,
                // ❦ 跟随基础规则：amount/daily_cap/amount_min/amount_max 用 base 的数值；enabled 用 base 的开关。
                // 这样一次派生就能直接前台生效（若不希望某动作对该币种发放，到规则列表取消勾选即可）。
                'amount'      => (int)$rule['amount'],
                'amount_min'  => (int)$rule['amount_min'],
                'amount_max'  => (int)$rule['amount_max'],
                'daily_cap'   => (int)$rule['daily_cap'],
                'enabled'     => (int)$rule['enabled'],
                'description' => '币种 [' . $code . '] 自动派生（已复制基础规则的数值与启停状态）',
                'created_at'  => $now,
            ]);
            $created++;
        }
        $this->success(['created' => $created, 'skipped' => $skipped], "已为币种 {$code} 派生 {$created} 条规则（已自动同步 base 的数值与启用状态；跳过 {$skipped} 条已存在）");
    }

    /**
     * 上传币种 SVG 图标。
     * 安全：
     *   1) 仅允许以 <svg ...>...</svg> 包住的内容；
     *   2) 用 DOMDocument 解析后清除 <script> / <foreignObject>；
     *   3) 清掉所有 on* 事件属性 + href="javascript:..."；
     *   4) 缺 viewBox 时补默认 0 0 24 24（让前端可缩放到 24×24）；
     *   5) 大小上限 64KB；
     *   6) 重置 PointService 进程内币种缓存，前台立即生效（无需刷新整站）。
     *
     * 入参：{ id?: 数字, code?: 字符串, svg: '<svg>...</svg>' }
     * 优先用 id 定位币种（模板上行 data-cur-id 就是 id），找不到再按 code。
     */
    public function uploadCurrencyIcon()
    {
        if (!is_webmaster()) { $this->error('仅站长可上传币种图标'); return; }
        if (!is_post()) { $this->error('非法请求'); return; }

        $id   = (int)input('id', 0);
        $code = trim((string)input('code', ''));
        $svg  = (string)input('svg', '');
        if ($id <= 0 && $code === '') { $this->error('缺少币种 id 或 code'); return; }
        if (strlen($svg) > 64 * 1024) { $this->error('SVG 内容超出 64KB 上限'); return; }
        $svg = trim($svg);
        if ($svg === '' || !preg_match('#^<svg\b[^>]*>.*?</svg>\s*$#si', $svg, $m)) {
            $this->error('请提交合法的 <svg>...</svg> 内容（且仅含一个 svg 根元素）'); return;
        }
        $svg = $m[0];

        // 防御 XSS：用 DOMDocument 标准解析 + 清脚本/事件/javascript:
        if (class_exists('\\DOMDocument')) {
            $doc = new \DOMDocument();
            libxml_use_internal_errors(true);
            // 用 XML 解析，避开 HTML 的容错机制（要求严格 XML）
            $loaded = $doc->loadXML('<?xml version="1.0" encoding="UTF-8"?>' . $svg);
            libxml_clear_errors();
            if (!$loaded || !$doc->documentElement) {
                error_log('[uploadCurrencyIcon] SVG 解析失败 raw=' . substr($svg, 0, 200));
                $this->error('SVG 解析失败'); return;
            }
            $root = $doc->documentElement;
            // 移除 <script>、<foreignObject>（无论嵌套多深）
            $kill = ['script', 'foreignObject'];
            foreach ($kill as $tag) {
                $list = iterator_to_array($root->getElementsByTagName($tag));
                foreach ($list as $n) { if ($n->parentNode) $n->parentNode->removeChild($n); }
            }
            // 清 on* 事件 + href="javascript:"
            $walk = function (\DOMNode $node) use (&$walk) {
                if ($node->hasAttributes()) {
                    $toDel = [];
                    foreach ($node->attributes as $a) {
                        $name = strtolower($a->nodeName);
                        if (strpos($name, 'on') === 0) { $toDel[] = $a->nodeName; continue; }
                        if (in_array($name, ['href', 'xlink:href'], true) && stripos(ltrim($a->nodeValue), 'javascript:') === 0) {
                            $toDel[] = $a->nodeName;
                        }
                    }
                    foreach ($toDel as $attr) $node->removeAttribute($attr);
                }
                if ($node->hasChildNodes()) foreach ($node->childNodes as $c) $walk($c);
            };
            $walk($root);
            if (!$root->hasAttribute('viewBox')) {
                $root->setAttribute('viewBox', '0 0 24 24');
            }
            // 序列化回纯 SVG 字符串（带 xmlns 让 HTML 内联合法）
            $svg = $doc->saveXML($root);
            if ($svg === false) { $this->error('SVG 序列化失败'); return; }
            // 去掉 saveXML 输出根上的 xmlns 属性（HTML 内联 svg 不需要）
            $svg = preg_replace('#\sxmlns="[^"]*"#', '', $svg, 1);
        } else {
            // 无 DOMDocument：粗粒度正则清理
            $svg = preg_replace('#<script\b.*?</script>#si', '', $svg);
            $svg = preg_replace('#<foreignObject\b.*?</foreignObject>#si', '', $svg);
            $svg = preg_replace_callback('#\son[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#i', function () { return ''; }, $svg);
            $svg = preg_replace_callback('#(href|xlink:href)\s*=\s*"\s*javascript:[^"]*"#i', function () { return ''; }, $svg);
            if (stripos($svg, 'viewBox=') === false) {
                $svg = preg_replace('#<svg\b([^>]*)>#i', '<svg$1 viewBox="0 0 24 24">', $svg, 1);
            }
        }

        $row = null;
        if ($id > 0) $row = Model::table('currencies')->where('id', $id)->first();
        if (!$row && $code !== '') $row = Model::table('currencies')->where('code', $code)->first();
        if (!$row) { $this->error('找不到对应币种'); return; }

        Model::table('currencies')->where('id', (int)$row['id'])->update([
            'icon_svg'   => $svg,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        \PointService::clearCurrencyCache();
        $this->success(['svg' => $svg], '图标已上传');
    }

    /**
     * 清除币种的 SVG 图标（恢复默认首字符）
     */
    public function clearCurrencyIcon()
    {
        if (!is_webmaster()) { $this->error('仅站长可清除币种图标'); return; }
        if (!is_post()) { $this->error('非法请求'); return; }
        $id   = (int)input('id', 0);
        $code = trim((string)input('code', ''));
        if ($id <= 0 && $code === '') { $this->error('缺少币种 id 或 code'); return; }
        $row = null;
        if ($id > 0) $row = Model::table('currencies')->where('id', $id)->first();
        if (!$row && $code !== '') $row = Model::table('currencies')->where('code', $code)->first();
        if (!$row) { $this->error('找不到对应币种'); return; }
        Model::table('currencies')->where('id', (int)$row['id'])->update([
            'icon_svg'   => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        \PointService::clearCurrencyCache();
        $this->success(null, '图标已清除');
    }

    /**
     * 一次性把 currencies / points_log / user_balances / point_rules 中所有币种 code
     * 统一转小写（用于清理历史 data 中 saveCurrency 老正则放行的大写 / 数字开头的脏数据）。
     * 幂等：本身已小写的不会变更。冲突（如存在 token 与 Token 两条 currencies）会跳过冲突的 code 并返回报告。
     */
    public function normalizeCurrencyCodes()
    {
        if (!is_webmaster()) { $this->error('仅站长可执行'); return; }
        if (!is_post()) { $this->error('非法请求'); return; }

        $tables = [
            'currencies'   => ['col' => 'code'],
            'points_log'   => ['col' => 'currency'],
            'user_balances'=> ['col' => 'currency'],
            'point_rules'  => ['col' => 'currency'],
        ];
        $report = ['updated' => 0, 'conflicts' => []];
        try {
            Model::beginTransaction();
            $rows = Model::query("SELECT id, code FROM currencies");
            foreach ($rows as $r) {
                $old = (string)$r['code'];
                $new = strtolower(trim($old));
                if ($new === '' || $new === $old) continue;
                // 检测冲突：是否已存在别人（小写或大写）等价的新 code
                $conflict = Model::table('currencies')->where('code', $new)->where('id', '!=', (int)$r['id'])->first();
                if ($conflict) { $report['conflicts'][] = "currencies.id={$r['id']} code={$old} → 目标 {$new} 已被占用"; continue; }
                Model::table('currencies')->where('id', (int)$r['id'])->update(['code' => $new]);
                foreach ($tables as $tbl => $cfg) {
                    if ($tbl === 'currencies') continue;
                    $ct = Model::execute("UPDATE `{$tbl}` SET `{$cfg['col']}` = ? WHERE `{$cfg['col']}` = ?", [$new, $old]);
                    $report['updated'] += (int)$ct;
                }
            }
            Model::commit();
        } catch (\Throwable $e) {
            Model::rollBack();
            error_log('[normalizeCurrencyCodes] ' . $e->getMessage());
            $this->error('执行失败：' . $e->getMessage());
            return;
        }
        \PointService::clearCurrencyCache();
        $this->success($report, '币种 code 已统一转小写（更新 ' . $report['updated'] . ' 条，冲突 ' . count($report['conflicts']) . ' 条）');
    }

    /**
     * ========== 页脚设置（仅站长）==========
     * 站点 logo / 简介 / 4 栏菜单 / 社交媒体 / 版权备案 / 预留 HTML
     * 用户没填就回退到 default_footer_data()（写在 core/helpers.php）
     */
    public function footerSettings()
    {
        if (!is_webmaster()) { flash('仅站长可访问「页脚设置」', 'error'); redirect(url('admin/index')); return; }

        $def = default_footer_data();
        $stored = [];
        foreach (Model::query("SELECT key_name, value FROM settings WHERE key_name IN ('site_footer_columns','site_footer_socials','site_footer_copyright','site_footer_site')") as $r) {
            $stored[$r['key_name']] = $r['value'];
        }
        $columns = json_decode($stored['site_footer_columns'] ?? '', true);
        if (!is_array($columns)) $columns = $def['columns'];
        $socials = json_decode($stored['site_footer_socials'] ?? '', true);
        if (!is_array($socials)) $socials = $def['socials'];
        $copyright = json_decode($stored['site_footer_copyright'] ?? '', true);
        if (!is_array($copyright)) $copyright = [];

        // 站点信息：站名 / logo / 简介（用户配置；缺字段 fallback 到 default_footer_data 对应字段）
        // 注意：siteName / siteLogo / siteIntro 直接读取 $site 字段，不强制 fallback——
        //   用户清空网站名 → 前台也能保存空值（前台 layout 用空字符串渲染或自动 fallback 到默认）
        $site = json_decode($stored['site_footer_site'] ?? '', true);
        if (!is_array($site)) $site = [];
        $siteName  = isset($site['name'])  ? (string)$site['name']  : '';
        $siteLogo  = isset($site['logo'])  ? (string)$site['logo']  : '';
        $siteIntro = isset($site['intro']) ? (string)$site['intro'] : '';

        $this->view('admin/footer_settings', [
            'columns'     => $columns,
            'socials'     => $socials,
            'copyright'   => $copyright,
            'siteName'    => $siteName,
            'siteLogo'    => $siteLogo,
            'siteIntro'   => $siteIntro,
        ]);
    }

    /**
     * 保存页脚设置
     *  - 4 栏菜单：每栏最多 5 个链接
     *  - 社交媒体：每行有 type 标识 link/qrcode + value
     *  - 版权块：JSON {copyright, icp, police_record, contact_extra}
     *  - 旧 site_footer_html 键：保存时一并删除（已下线「预留 HTML 块」）
     */
    public function saveFooterSettings()
    {
        if (!is_webmaster()) $this->error('仅站长可保存页脚设置');

        // 1. 4 栏菜单
        $rawCols = input('columns', []);
        if (!is_array($rawCols)) $rawCols = [];
        $cleanCols = [];
        foreach ($rawCols as $col) {
            $title = trim((string)($col['title'] ?? ''));
            $linksIn = is_array($col['links'] ?? null) ? $col['links'] : [];
            $cleanLinks = [];
            foreach ($linksIn as $lk) {
                $name = trim((string)($lk['name'] ?? ''));
                $url  = trim((string)($lk['url'] ?? ''));
                if ($name === '' && $url === '') continue;       // 空行跳过
                if ($name === '') continue;
                $cleanLinks[] = ['name' => mb_substr($name, 0, 30), 'url' => mb_substr($url, 0, 255)];
                if (count($cleanLinks) >= 5) break;              // 硬限 5 条
            }
            $cleanCols[] = [
                'title' => mb_substr($title, 0, 30),
                'links' => $cleanLinks,
            ];
            if (count($cleanCols) >= 6) break;                  // 防御：最多 6 栏
        }
        // 如果整组为空（用户主动删光）→ 写空 JSON 触发前台 fallback 到默认
        $this->saveSetting('site_footer_columns', json_encode($cleanCols, JSON_UNESCAPED_UNICODE), '页脚4栏菜单');

        // 2. 社交媒体
        $rawSocials = input('socials', []);
        if (!is_array($rawSocials)) $rawSocials = [];
        $cleanSocials = [];
        foreach ($rawSocials as $s) {
            $icon  = trim((string)($s['icon'] ?? ''));
            $type  = trim((string)($s['type'] ?? 'link'));
            $value = trim((string)($s['value'] ?? ''));
            $title = trim((string)($s['title'] ?? ''));
            if ($icon === '') continue;                          // 必须有图标
            // 服务端归一：把历史脏数据 fa--xxx 折叠为 fa-xxx，FA4 写法（fa fa-home）→ FA6（fa-solid fa-house），
            // 裸名（weibo）→ FA6（fa-brands fa-weibo）。这样无论前端 JS 出错或被绕过，存进 DB 的都是 FA6 标准类名。
            if (function_exists('fa_icon_class')) $icon = fa_icon_class($icon);
            if ($icon === '') $icon = 'fa-solid fa-link';        // 空归一结果兜底
            // 安全闸：必须严格匹配「fa-家族 + 空白 + fa-裸名」的 FA6 标准结构，裸名仅允许 [a-z0-9-]，
            // 不允许引号 / 尖括号 / 多 class / 任意 Unicode 注入。匹配不上的统统兜底为 fa-solid fa-link。
            // 这条正则是真正的 XSS / 注入防御，足够代替旧的裸名白名单。
            if (!preg_match('/^fa-(solid|regular|light|thin|duotone|brands)\s+fa-[a-z0-9][a-z0-9-]*$/i', $icon)) {
                $icon = 'fa-solid fa-link';
            }
            if (!in_array($type, ['link', 'qrcode'], true)) $type = 'link';
            if ($value === '') continue;                         // 没值跳过
            $cleanSocials[] = [
                'icon'  => $icon,
                'type'  => $type,
                'value' => mb_substr($value, 0, 500),
                'title' => mb_substr($title, 0, 30) ?: $icon,
            ];
        }
        $this->saveSetting('site_footer_socials', json_encode($cleanSocials, JSON_UNESCAPED_UNICODE), '页脚社交媒体');

        // 3. 版权 / 备案 / 联系方式（含新增的「公安备案号」）
        $cp = [
            'copyright'     => trim((string)input('copyright', '')),
            'icp'           => trim((string)input('icp', '')),
            'police_record' => trim((string)input('police_record', '')),
            'contact_extra' => trim((string)input('contact_extra', '')),
        ];
        $this->saveSetting('site_footer_copyright', json_encode($cp, JSON_UNESCAPED_UNICODE), '页脚版权/备案/联系');

        // 4. 站点信息（站名 / logo / 简介） —— 整体落到一个 JSON 键
        //    logo 只接受形如 uploads/xxx 的相对路径（由前端上传组件写入 hidden input 提供）
        //    任一字段都允许留空保存（不强制整组非空）；仅 3 个全空时主动 DELETE 触发前台 fallback
        $sitePayload = [
            'name'  => trim((string)input('site_name', '')),
            'logo'  => trim((string)input('site_logo', '')),
            'intro' => trim((string)input('site_intro', '')),
        ];
        if ($sitePayload['name'] === '' && $sitePayload['logo'] === '' && $sitePayload['intro'] === '') {
            try { Model::table('settings')->where('key_name', 'site_footer_site')->delete(); } catch (Throwable $e) {}
        } else {
            // 仍要对 logo 做一次白名单净化：只允许 uploads/ 开头
            if ($sitePayload['logo'] !== '' && strpos($sitePayload['logo'], 'uploads/') !== 0) {
                $sitePayload['logo'] = '';
            }
            // 截断防止过长
            $sitePayload['name']  = mb_substr($sitePayload['name'], 0, 30);
            $sitePayload['intro'] = mb_substr($sitePayload['intro'], 0, 200);
            $this->saveSetting('site_footer_site', json_encode($sitePayload, JSON_UNESCAPED_UNICODE), '页脚站点信息');
        }

        // 5. 清理已下线的 site_footer_html（预留 HTML 块已删除）
        try {
            Model::table('settings')->where('key_name', 'site_footer_html')->delete();
        } catch (Throwable $e) { /* 忽略：表/列可能不存在 */ }

        $this->success(null, '页脚设置已保存');
    }

    /**
     * 发送测试邮件（验证 SMTP 配置）
     */
    public function testMail()
    {
        if (!is_webmaster()) $this->error('仅站长可测试邮箱');
        $to = trim(input('to', ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) $this->error('请输入有效的测试收件邮箱');

        $settings = [];
        foreach (['mail_from_name', 'mail_from_email', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_secure'] as $k) {
            $settings[$k] = trim((string)input($k, ''));
        }

        $subject = '【' . Config::get('site.title', '论坛') . '】SMTP 测试邮件';
        $body = "这是一封测试邮件，收到说明 SMTP 配置正确。\n\n发送时间：" . date('Y-m-d H:i:s');

        $result = Mailer::sendWith($to, $subject, $body, $settings);
        if ($result === true) {
            $this->success(null, '测试邮件发送成功，请查收 ' . $to);
        }
        $this->error('测试邮件发送失败：' . $result);
    }

    /* ========== 敏感词管理 ========== */
    public function sensitiveWords()
    {
        if (!is_high_admin()) { flash('无权限访问', 'error'); redirect(url('admin/index')); return; }
        $page = (int)input('page', 1);
        $result = Model::table('sensitive_words')->orderBy('created_at', 'DESC')->paginate($page, 50);
        $this->view('admin/sensitive_words', ['words' => $result['data'], 'pagination' => $result, 'page' => $page]);
    }

    public function addSensitiveWord()
    {
        if (!is_high_admin()) $this->error('无权限');
        $word = trim(input('word'));
        $level = (int)input('level', 1);
        $scope = input('scope', 'all');
        // 禁用范围枚举：all=全部（用户名/昵称+内容）、username_nickname=仅用户名或昵称、content=仅发布内容
        if (!in_array($scope, ['all', 'username_nickname', 'content'], true)) $scope = 'all';
        if (!in_array($level, [1, 2], true)) $level = 1;
        if ($word === '' || mb_strlen($word) > 100) $this->error('请输入合法的敏感词（1-100 字）');
        $exists = Model::table('sensitive_words')->where('word', $word)->first();
        if ($exists) $this->error('该敏感词已存在');
        Model::table('sensitive_words')->insert([
            'word' => $word, 'level' => $level, 'scope' => $scope, 'status' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->success(null, '敏感词已添加');
    }

    public function editSensitiveWord($id = null)
    {
        if (!is_high_admin()) $this->error('无权限');
        $id = (int)($id ?? input('id'));
        $row = Model::table('sensitive_words')->where('id', $id)->first();
        if (!$row) $this->error('敏感词不存在');

        $word = trim((string)input('word', $row['word']));
        $level = (int)input('level', $row['level']);
        $scope = (string)input('scope', $row['scope']);
        $status = (int)input('status', $row['status']);
        if (!in_array($scope, ['all', 'username_nickname', 'content'], true)) $this->error('禁用范围不合法');
        if (!in_array($level, [1, 2], true)) $this->error('级别不合法');
        if (!in_array($status, [0, 1], true)) $this->error('状态不合法');
        if ($word === '' || mb_strlen($word) > 100) $this->error('请输入合法的敏感词（1-100 字）');

        // 唯一性：修改 word 后不能撞已有
        if ($word !== $row['word']) {
            $dup = Model::table('sensitive_words')->where('word', $word)->where('id', '!=', $id)->first();
            if ($dup) $this->error('该敏感词已存在');
        }

        Model::table('sensitive_words')->where('id', $id)->update([
            'word' => $word, 'level' => $level, 'scope' => $scope, 'status' => $status,
        ]);
        $this->success(null, '敏感词已更新');
    }

    public function deleteSensitiveWord($id = null)
    {
        if (!is_high_admin()) $this->error('无权限');
        $id = (int)($id ?? input('id'));
        Model::table('sensitive_words')->where('id', $id)->delete();
        $this->success(null, '敏感词已删除');
    }

    /**
     * 导出敏感词为 CSV（Excel 直接打开；含 UTF-8 BOM，避免中文乱码）
     * 列：ID, 敏感词, 级别, 禁用范围, 状态, 添加时间
     */
    public function exportSensitiveWords()
    {
        if (!is_high_admin()) { flash('无权限', 'error'); redirect(url('admin/index')); return; }
        $rows = Model::table('sensitive_words')->orderBy('id', 'DESC')->get();

        $scopeMap = ['all' => '全部', 'username_nickname' => '仅用户名或昵称', 'content' => '仅发布内容'];
        $levelMap = [1 => '普通', 2 => '高危'];

        $filename = 'sensitive_words_' . date('Y-m-d_His') . '.csv';
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        // UTF-8 BOM 让 Excel 自动识别编码
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', '敏感词', '级别', '禁用范围', '状态', '添加时间']);
        foreach ($rows as $r) {
            $scope = $r['scope'] ?? 'all';
            if ($scope === 'username') $scope = 'username_nickname'; // 兼容旧值
            fputcsv($out, [
                (int)$r['id'],
                $r['word'],
                $levelMap[(int)$r['level']] ?? '普通',
                $scopeMap[$scope] ?? '全部',
                ((int)$r['status'] === 1) ? '启用' : '禁用',
                $r['created_at'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    /**
     * 导入敏感词 CSV（multipart/form-data 上传）
     * 列宽容度：首行可含表头，列位置以"敏感词"为锚；中文 / 英文枚举值都接受；重复自动跳过
     * @return JSON {code, message, inserted, skipped, failed, total}
     */
    public function importSensitiveWords()
    {
        if (!is_high_admin()) $this->error('无权限');

        if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->error('请选择 CSV 文件');
        }
        $tmp = $_FILES['file']['tmp_name'];
        if (!is_uploaded_file($tmp) || !is_readable($tmp)) {
            $this->error('文件读取失败');
        }

        // 列名双向映射：英文枚举 ↔ 中文标签
        $scopeMap = [
            'all' => 'all', 'username_nickname' => 'username_nickname', 'content' => 'content',
            '全部' => 'all', '仅用户名或昵称' => 'username_nickname', '仅发布内容' => 'content',
            // 旧值兼容
            'username' => 'username_nickname',
        ];
        $levelMap = [
            '1' => 1, '2' => 2,
            '普通' => 1, '高危' => 2, 'normal' => 1, 'danger' => 2, 'high' => 2,
        ];

        $fh = fopen($tmp, 'r');
        // 自动跳过 UTF-8 BOM
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($fh);

        $header = fgetcsv($fh);
        if (!$header) { $this->error('CSV 为空'); }

        // 找列索引：以"敏感词"列名为锚，识别表头
        $colWord = null; $colLevel = null; $colScope = null;
        $hasHeader = false;
        foreach ($header as $i => $h) {
            $h = trim((string)$h);
            if ($h === '敏感词' || strcasecmp($h, 'word') === 0) { $colWord = $i; $hasHeader = true; }
            elseif (in_array($h, ['级别', 'level', 'Level'], true)) $colLevel = $i;
            elseif (in_array($h, ['禁用范围', 'scope', 'Scope'], true)) $colScope = $i;
        }
        if ($colWord === null) {
            // 没"敏感词"列：按"ID,敏感词,级别,禁用范围,状态,添加时间"回退
            rewind($fh);
            $bom2 = fread($fh, 3);
            if ($bom2 !== "\xEF\xBB\xBF") rewind($fh);
            $colWord = 1; $colLevel = 2; $colScope = 3;
        }

        $inserted = 0; $skipped = 0; $failed = 0; $total = 0;
        $now = date('Y-m-d H:i:s');
        while (($row = fgetcsv($fh)) !== false) {
            $total++;
            $word = isset($row[$colWord]) ? trim((string)$row[$colWord]) : '';
            if ($word === '') { $skipped++; continue; }
            $word = mb_substr($word, 0, 100);

            $level = 1;
            if ($colLevel !== null && isset($row[$colLevel])) {
                $raw = trim((string)$row[$colLevel]);
                $level = $levelMap[$raw] ?? 1;
            }
            $scope = 'all';
            if ($colScope !== null && isset($row[$colScope])) {
                $raw = trim((string)$row[$colScope]);
                $scope = $scopeMap[$raw] ?? 'all';
            }

            try {
                $exists = Model::table('sensitive_words')->where('word', $word)->first();
                if ($exists) { $skipped++; continue; }
                Model::table('sensitive_words')->insert([
                    'word' => $word, 'level' => $level, 'scope' => $scope, 'status' => 1, 'created_at' => $now,
                ]);
                $inserted++;
            } catch (Exception $e) {
                $failed++;
            }
        }
        fclose($fh);

        $msg = "导入完成：成功 {$inserted} 条，跳过 {$skipped} 条";
        if ($failed > 0) $msg .= "，失败 {$failed} 条";
        $this->success([
            'inserted' => $inserted, 'skipped' => $skipped, 'failed' => $failed, 'total' => $total,
        ], $msg);
    }

    /* ========== 认证项管理（含认证项目组；仅站长） ========== */
    public function certItems($group_id = null)
    {
        if (!is_webmaster()) { flash('仅站长可访问「认证项设置」', 'error'); redirect(url('admin/index')); return; }
        $groups = Model::table('certification_groups')->where('status', 1)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->get();
        $group_id = (int)($group_id ?? input('group_id', 0));
        if (!$group_id && !empty($groups)) $group_id = (int)$groups[0]['id'];
        $items = [];
        if ($group_id) {
            $items = Model::table('certification_items')->where('group_id', $group_id)->where('status', 1)->orderBy('sort_order', 'ASC')->get();
        }
        // 取当前组的独立图标（每组一个）
        $currentGroup = null;
        $currentIcon = '';
        foreach ($groups as $g) {
            if ((int)$g['id'] === (int)$group_id) { $currentGroup = $g; $currentIcon = $g['icon_svg'] ?? ''; break; }
        }
        // 兼容旧库：缺 icon_svg 列时回退全局 settings
        if (!$currentIcon) {
            $row = Model::table('settings')->where('key_name', 'cert_badge_svg')->first();
            $currentIcon = $row ? ($row['value'] ?? '') : '';
        }
        $this->view('admin/cert_items', [
            'items' => $items,
            'badge' => $currentIcon,
            'groups' => $groups,
            'currentGroupId' => $group_id,
            'currentGroup' => $currentGroup,
        ]);
    }

    /** 新增认证项目组（自定义组） */
    public function addCertGroup()
    {
        if (!is_webmaster()) $this->error('仅站长可管理认证项目');
        $name = trim(input('name'));
        if (!$name) $this->error('认证项目名称不能为空');
        if (mb_strlen($name) > 50) $this->error('名称不能超过 50 字');
        $exists = Model::table('certification_groups')->where('name', $name)->first();
        if ($exists) $this->error('该名称已存在');
        $maxSort = (int)Model::scalar('SELECT MAX(sort_order) FROM certification_groups');
        Model::table('certification_groups')->insert([
            'name' => $name,
            'sort_order' => $maxSort + 1,
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $newId = (int)Model::scalar('SELECT MAX(id) FROM certification_groups');
        $this->success(['id' => $newId], '认证项目已添加');
    }

    /** 重命名认证项目组 */
    public function renameCertGroup($id = null)
    {
        if (!is_webmaster()) $this->error('仅站长可管理认证项目');
        $id = (int)($id ?? input('id'));
        $name = trim(input('name'));
        if (!$name) $this->error('名称不能为空');
        if (mb_strlen($name) > 50) $this->error('名称不能超过 50 字');
        Model::table('certification_groups')->where('id', $id)->update(['name' => $name]);
        $this->success(null, '已重命名');
    }

    /** 删除认证项目组（默认 id=1 实名认证不可删；级联删组下认证项） */
    public function deleteCertGroup($id = null)
    {
        if (!is_webmaster()) $this->error('仅站长可管理认证项目');
        $id = (int)($id ?? input('id'));
        if ($id === 1) $this->error('默认「实名认证」不可删除');
        $g = Model::table('certification_groups')->where('id', $id)->first();
        if (!$g) $this->error('认证项目不存在');
        Model::table('certification_items')->where('group_id', $id)->delete();
        Model::table('certification_groups')->where('id', $id)->delete();
        $this->success(null, '认证项目已删除');
    }

    /**
     * 保存某认证项目组的独立认证图标（SVG 文件上传）
     * 接收 group_id（POST 隐藏域）；清除时传 cert_badge_svg_remove=1
     */
    public function saveCertGroupIcon($id = null)
    {
        if (!is_webmaster()) $this->error('仅站长可管理认证项目');
        $id = (int)($id ?? input('group_id', 0));
        if (!$id) $this->error('缺少认证项目');
        $g = Model::table('certification_groups')->where('id', $id)->first();
        if (!$g) $this->error('认证项目不存在');

        // 清除该组图标
        if ((int)input('cert_badge_svg_remove', 0) === 1) {
            Model::table('certification_groups')->where('id', $id)->update(['icon_svg' => null]);
            $this->success(null, '图标已清除');
        }

        if (empty($_FILES['cert_badge_svg']) || !isset($_FILES['cert_badge_svg']['error'])) {
            $this->error('请上传一个 SVG 文件');
        }
        $file = $_FILES['cert_badge_svg'];
        if ($file['error'] !== UPLOAD_ERR_OK) $this->error('上传失败，错误码：' . $file['error']);
        if ($file['size'] > 51200) $this->error('SVG 文件不能超过 50KB');
        if ($file['size'] === 0) $this->error('文件为空');

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'svg') $this->error('仅支持 .svg 后缀的文件');

        $content = file_get_contents($file['tmp_name']);
        if ($content === false || $content === '') $this->error('无法读取文件内容');

        // XSS 清洗
        $sanitized = preg_replace('#<script\b.*?</script>#is', '', $content) ?? $content;
        $sanitized = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('#javascript\s*:#i', '', $sanitized) ?? $sanitized;

        $prevUseErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($sanitized);
        libxml_clear_errors();
        libxml_use_internal_errors($prevUseErrors);
        if ($xml === false || strtolower($xml->getName()) !== 'svg') {
            $this->error('文件不是合法的 SVG');
        }

        Model::table('certification_groups')->where('id', $id)->update(['icon_svg' => $sanitized]);
        $this->success(null, '图标已保存');
    }

    /**
     * 恢复某认证项目组的图标为系统默认（品牌红 ✓ 24×24 矢量图）
     * 无论中途修改过多少次，都恢复到 AdminController::DEFAULT_CERT_BADGE_SVG 常量
     */
    public function restoreCertGroupIcon($id = null)
    {
        if (!is_webmaster()) $this->error('仅站长可管理认证项目');
        $id = (int)($id ?? input('group_id', 0));
        if (!$id) $this->error('缺少认证项目');
        $g = Model::table('certification_groups')->where('id', $id)->first();
        if (!$g) $this->error('认证项目不存在');
        Model::table('certification_groups')->where('id', $id)->update(['icon_svg' => self::DEFAULT_CERT_BADGE_SVG]);
        $this->success(null, '已恢复为系统默认图标（品牌红 ✓）');
    }

    public function addCertItem()
    {
        if (!is_webmaster()) $this->error('仅站长可管理认证项目');
        $name = trim(input('name'));
        $label = trim(input('label'));
        $type = input('type', 'text');
        $options = trim(input('options', ''));
        $required = (int)input('required', 1);
        $sortOrder = (int)input('sort_order', 0);
        $groupId = (int)input('group_id', 1);
        if (!$name || !$label) $this->error('字段名和标签不能为空');
        if (!in_array($type, ['text', 'textarea', 'image', 'select'])) $this->error('类型无效');
        $groupExists = Model::table('certification_groups')->where('id', $groupId)->where('status', 1)->first();
        if (!$groupExists) $this->error('认证项目不存在');
        $exists = Model::table('certification_items')->where('name', $name)->where('group_id', $groupId)->first();
        if ($exists) $this->error('当前项目中该字段名已存在');
        Model::table('certification_items')->insert([
            'group_id' => $groupId, 'name' => $name, 'label' => $label, 'type' => $type, 'options' => $options ?: null,
            'required' => $required, 'sort_order' => $sortOrder, 'status' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->success(null, '认证项已添加');
    }

    public function updateCertItem($id = null)
    {
        if (!is_webmaster()) $this->error('仅站长可管理认证项目');
        $id = (int)($id ?? input('id'));
        $item = Model::table('certification_items')->where('id', $id)->first();
        if (!$item) $this->error('认证项不存在');
        $data = [];
        foreach (['name', 'label', 'type', 'options'] as $f) {
            $v = input($f);
            if ($v !== null) $data[$f] = trim($v);
        }
        $data['required'] = (int)input('required', 0);
        $data['sort_order'] = (int)input('sort_order', 0);
        $data['status'] = (int)input('status', 1);
        Model::table('certification_items')->where('id', $id)->update($data);
        $this->success(null, '认证项已更新');
    }

    public function deleteCertItem($id = null)
    {
        if (!is_webmaster()) $this->error('仅站长可管理认证项目');
        $id = (int)($id ?? input('id'));
        Model::table('certification_items')->where('id', $id)->delete();
        $this->success(null, '认证项已删除');
    }

    /**
     * 保存认证通过后图标
     * 新版只接受 SVG 文件上传；同时支持 "清除图标"。
     * 旧版 cert_badge_svg 文本字段放弃（不向后兼容——后台只允许上传）。
     */
    public function saveCertSettings()
    {
        if (!is_webmaster()) $this->error('仅站长可管理认证项目');
        // 恢复默认图标：无论中途改过多少次，一律恢复到原始内置品牌红 #ea6f5a 圆底 + 白色 ✓
        if ((int)input('cert_badge_svg_restore', 0) === 1) {
            $this->saveSetting('cert_badge_svg', self::DEFAULT_CERT_BADGE_SVG, '认证通过后图标SVG');
            $this->success(null, '已恢复默认图标');
        }

        // 清除（仅清空当前生效图标，不影响"恢复默认"永远回到原始内置图标）
        if ((int)input('cert_badge_svg_remove', 0) === 1) {
            $this->saveSetting('cert_badge_svg', '', '认证通过后图标SVG');
            $this->success(null, '图标已清除');
        }

        if (empty($_FILES['cert_badge_svg']) || !isset($_FILES['cert_badge_svg']['error'])) {
            $this->error('请上传一个 SVG 文件');
        }
        $file = $_FILES['cert_badge_svg'];
        if ($file['error'] !== UPLOAD_ERR_OK) $this->error('上传失败，错误码：' . $file['error']);

        // 50KB 上限
        if ($file['size'] > 51200) $this->error('SVG 文件不能超过 50KB');
        if ($file['size'] === 0) $this->error('文件为空');

        // 文件名/扩展名校验
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'svg') $this->error('仅支持 .svg 后缀的文件');

        // 内容读取 + XML 解析校验
        $content = file_get_contents($file['tmp_name']);
        if ($content === false || $content === '') $this->error('无法读取文件内容');

        // 简单 XSS 清洗：去掉 <script> 与 on* 事件 + javascript: 链接
        $sanitized = preg_replace('#<script\b.*?</script>#is', '', $content) ?? $content;
        $sanitized = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('#javascript\s*:#i', '', $sanitized) ?? $sanitized;

        // 解析 XML 校验合法性（用超管模式阻止外部实体）
        $prevUseErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($sanitized);
        libxml_clear_errors();
        libxml_use_internal_errors($prevUseErrors);
        if ($xml === false || strtolower($xml->getName()) !== 'svg') {
            $this->error('文件不是合法的 SVG');
        }

        // 重序列化（清洗后的纯净内容）
        $cleanSvg = $sanitized;

        $this->saveSetting('cert_badge_svg', $cleanSvg, '认证通过后图标SVG');
        $this->success(null, '认证图标已保存');
    }

    /* ========== 角色编辑/删除 ========== */
    public function updateRole($id = null)
    {
        if (!is_webmaster()) $this->error('仅站长可管理角色');
        $id = (int)($id ?? input('id'));
        $role = Model::table('roles')->where('id', $id)->first();
        if (!$role) $this->error('角色不存在');
        $name = trim(input('name'));
        $code = trim(input('code'));
        $desc = trim(input('description'));
        if (!$name || !$code) $this->error('名称和编码不能为空');
        // 编码唯一性（排除自身）
        $exists = Model::table('roles')->where('code', $code)->where('id', '!=', $id)->first();
        if ($exists) $this->error('角色编码已存在');
        Model::table('roles')->where('id', $id)->update([
            'name' => $name, 'code' => $code, 'description' => $desc,
        ]);
        $this->success(null, '角色已更新');
    }

    public function deleteRole($id = null)
    {
        if (!is_webmaster()) $this->error('仅站长可管理角色');
        $id = (int)($id ?? input('id'));
        $role = Model::table('roles')->where('id', $id)->first();
        if (!$role) $this->error('角色不存在');
        // 禁止删除系统内置角色
        if (in_array($role['code'], ['guest', 'user', 'certified_user', 'moderator', 'admin', 'super_admin'])) {
            $this->error('系统内置角色不可删除');
        }
        Model::execute('DELETE FROM role_permissions WHERE role_id = ?', [$id]);
        Model::table('roles')->where('id', $id)->delete();
        $this->success(null, '角色已删除');
    }

    /* ========== 后台编辑用户：按认证项目组勾选 ========== */

    /**
     * 同步用户的"认证项目组"通过/取消状态。
     * POST cert_groups[] = 已勾选组 id 列表（未勾选则视为未通过；空数组 = 全部未认证）。
     *
     * 行为：
     *  - 已在勾选列表里：插入或更新一条 status=1（已通过）的记录（reviewer_id=当前管理员）
     *  - 不在勾选列表里：把该 user_id+group_id 已通过的记录改为 status=2（驳回/reject），
     *    并写 reject_reason='管理员后台取消'、reviewed_at=now，便于审计
     *  - 默认组（group_id=1）通过/取消时同步 users.is_certified + certified_at
     *
     * @param int $userId 目标用户 id
     */
    protected function syncUserCertGroups($userId)
    {
        $now = date('Y-m-d H:i:s');
        $reviewerId = (int)(Auth::id() ?? 0);

        // 已勾选的组 id 列表（强制 int + 去重 + 过滤非正数）
        $picked = input('cert_groups', []);
        if (!is_array($picked)) $picked = [];
        $picked = array_values(array_unique(array_filter(array_map('intval', $picked), function ($v) { return $v > 0; })));
        $pickedMap = array_flip($picked);

        // 加载所有启用项目组（status=1）；按 sort_order 升序
        $groups = [];
        try {
            $groups = Model::table('certification_groups')->where('status', 1)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->get();
        } catch (Exception $e) {}
        if (empty($groups)) return;

        // 当前用户所有认证记录按 group_id 索引
        $existing = [];
        try {
            $rows = Model::table('certifications')->where('user_id', $userId)->get();
            foreach ($rows as $r) $existing[(int)$r['group_id']] = $r;
        } catch (Exception $e) {}

        $isCertifiedChanged = false;
        $newIsCertified = isset($existing[1]) && (int)$existing[1]['status'] === 1 ? 1 : 0;

        foreach ($groups as $g) {
            $gid = (int)$g['id'];
            $isPicked = isset($pickedMap[$gid]);
            $cur = $existing[$gid] ?? null;
            $gName = (string)($g['name'] ?? '');
            if ($gName === '') $gName = '认证';

            if ($isPicked) {
                // 期望：通过
                $wasPassed = $cur && (int)$cur['status'] === 1;
                if (!$cur) {
                    // 没有记录 → 插入一条管理员手动通过的记录
                    try {
                        $certId = Model::table('certifications')->insert([
                            'user_id' => $userId,
                            'group_id' => $gid,
                            'real_name' => null,
                            'id_card' => null,
                            'phone' => null,
                            'extra_note' => null,
                            'form_data' => null,
                            'status' => 1,
                            'reject_reason' => null,
                            'reviewer_id' => $reviewerId,
                            'reviewed_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    } catch (Exception $e) {}
                } elseif ((int)$cur['status'] !== 1) {
                    // 已有记录但未通过 → 改为通过
                    try {
                        Model::table('certifications')->where('id', (int)$cur['id'])->update([
                            'status' => 1, 'reject_reason' => null,
                            'reviewer_id' => $reviewerId, 'reviewed_at' => $now, 'updated_at' => $now,
                        ]);
                    } catch (Exception $e) {}
                }
                if ($gid === 1) $newIsCertified = 1;
                // ✦「管理员后台通过」通知：从未通过 → 通过时通知用户（含新增记录 + 已有记录被改为通过两种情况）
                if (!$wasPassed) {
                    try {
                        Model::table('notifications')->insert([
                            'user_id' => $userId,
                            'type' => 'certification',
                            'content' => '恭喜，您的「' . $gName . '」已通过审核',
                            'link' => url('certification/index', ['group_id' => $gid]),
                            'is_read' => 0,
                            'created_at' => $now,
                        ]);
                    } catch (Exception $e) {}
                }
            } else {
                // 期望：未通过
                $wasPassed = $cur && (int)$cur['status'] === 1;
                if ($wasPassed) {
                    // 把已通过的记录改为"驳回(管理员取消)"，保留审计
                    try {
                        Model::table('certifications')->where('id', (int)$cur['id'])->update([
                            'status' => 2, 'reject_reason' => '管理员后台取消',
                            'reviewer_id' => $reviewerId, 'reviewed_at' => $now, 'updated_at' => $now,
                        ]);
                    } catch (Exception $e) {}
                    // ✦「管理员后台取消」通知：仅当从通过 → 取消时才通知用户
                    try {
                        Model::table('notifications')->insert([
                            'user_id' => $userId,
                            'type' => 'certification',
                            'content' => '您的「' . $gName . '」已被管理员后台取消',
                            'link' => url('certification/index', ['group_id' => $gid]),
                            'is_read' => 0,
                            'created_at' => $now,
                        ]);
                    } catch (Exception $e) {}
                }
                if ($gid === 1) $newIsCertified = 0;
            }
        }

        // 默认组（实名认证）勾选状态同步到 users.is_certified
        $userRow = Model::table('users')->where('id', $userId)->first();
        $oldIsCertified = $userRow ? (int)$userRow['is_certified'] : 0;
        if ($oldIsCertified !== $newIsCertified) {
            try {
                Model::table('users')->where('id', $userId)->update([
                    'is_certified' => $newIsCertified,
                    'certified_at' => $newIsCertified ? $now : null,
                    'updated_at' => $now,
                ]);
            } catch (Exception $e) {}
            // 兼容旧 role 联动：升级 / 降级
            if ($userRow) {
                if ($newIsCertified === 1 && $userRow['role'] === 'user') {
                    try { Model::table('users')->where('id', $userId)->update(['role' => 'certified_user']); } catch (Exception $e) {}
                } elseif ($newIsCertified === 0 && $userRow['role'] === 'certified_user') {
                    try { Model::table('users')->where('id', $userId)->update(['role' => 'user']); } catch (Exception $e) {}
                }
            }
        }
    }

    /* ========== 系统通知（站长向站内用户群发广播；is_system_notification=1） ==========
     *   - target_type='all'    → 给所有 status=1 用户
     *   - target_type='role'   → 给指定角色组（多选）status=1 用户
     *   - target_type='users'  → 给指定用户（每行一个：用户 ID 或用户名），按 users 表精确匹配
     *   - 每条群发写一行 system_notification_logs，便于追溯
     */
    public function systemNotification()
    {
        if (!is_webmaster()) { flash('无权访问', 'error'); redirect(url('admin/index')); return; }

        // 站内可见的角色（按权限等级升序，前端复选展示）
        $roleList = [
            'user' => '普通用户',
            'certified_user' => '已认证用户',
            'moderator' => '版主',
            'admin' => '管理员',
            'super_admin' => '超级管理员',
        ];
        // 站长始终能收到，无需出现在待选列表里

        // 最近 20 条历史
        $logs = [];
        try {
            $logs = Model::query(
                "SELECT l.*, u.username AS sender_name, u.nickname AS sender_nick
                 FROM system_notification_logs l
                 LEFT JOIN users u ON u.id = l.sender_id
                 ORDER BY l.id DESC LIMIT 20"
            );
        } catch (Exception $e) {
            $logs = [];
        }

        $this->view('admin/system_notification', [
            'roleList' => $roleList,
            'logs' => $logs,
        ]);
    }

    public function sendSystemNotification()
    {
        if (!is_webmaster()) $this->error('仅站长可发送系统通知');
        // 中间件已 csrf_check；这里再加一道表单字段校验
        $title   = trim((string)input('title', ''));
        $content = trim((string)input('content', ''));
        $link    = trim((string)input('link', ''));
        $targetType = trim((string)input('target_type', ''));
        $allowedTargets = ['all', 'role', 'users'];
        if (!in_array($targetType, $allowedTargets, true)) $this->error('不支持的发送目标');

        if ($title === '')   $this->error('请填写通知摘要（标题）');
        if (mb_strlen($title) > 100) $this->error('摘要不能超过 100 字');
        if ($content === '') $this->error('请填写通知内容');
        if (mb_strlen($content) > 255) $this->error('内容不能超过 255 字（系统通知单行展示）');

        // 完整消息：摘要：内容（在前端模板渲染样式化展示）
        $display = $title . ($content !== '' ? '：' . $content : '');

        // 解析目标
        $recipientIds = [];
        $targetValue = '';
        if ($targetType === 'all') {
            $targetValue = 'all';
            $rows = Model::query("SELECT id FROM users WHERE status = 1");
            foreach ($rows as $r) $recipientIds[] = (int)$r['id'];
        } elseif ($targetType === 'role') {
            $codes = input('roles', []);
            if (!is_array($codes)) $codes = [];
            $codes = array_values(array_unique(array_filter(array_map('strval', $codes), function ($v) {
                return in_array($v, ['user', 'certified_user', 'moderator', 'admin', 'super_admin'], true);
            })));
            if (empty($codes)) $this->error('请至少选择一个角色组');
            $targetValue = json_encode($codes, JSON_UNESCAPED_UNICODE);
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            $rows = Model::query("SELECT id FROM users WHERE status = 1 AND role IN ($placeholders)", $codes);
            foreach ($rows as $r) $recipientIds[] = (int)$r['id'];
        } elseif ($targetType === 'users') {
            $raw = trim((string)input('user_list', ''));
            if ($raw === '') $this->error('请输入至少一个用户名或用户 ID');
            $tokens = preg_split('/[\s,，]+/u', $raw);
            $tokens = array_values(array_unique(array_filter(array_map('trim', $tokens), function ($v) { return $v !== ''; })));
            if (empty($tokens)) $this->error('请输入至少一个用户名或用户 ID');
            $ids = []; $names = [];
            foreach ($tokens as $t) {
                if (ctype_digit($t)) $ids[] = (int)$t; else $names[] = $t;
            }
            $placeholders = [];
            $params = [];
            $sql = "SELECT id FROM users WHERE status = 1";
            if (!empty($ids)) {
                $placeholders[] = implode(',', array_fill(0, count($ids), '?'));
                $sql .= " AND id IN (" . $placeholders[count($placeholders)-1] . ")";
                foreach ($ids as $_) $params[] = (int)$_;
            }
            if (!empty($names)) {
                $sql .= $ids ? " OR username IN (" . implode(',', array_fill(0, count($names), '?')) . ")" : " AND username IN (" . implode(',', array_fill(0, count($names), '?')) . ")";
                foreach ($names as $n) $params[] = $n;
            }
            $targetValue = json_encode($tokens, JSON_UNESCAPED_UNICODE);
            $rows = Model::query($sql, $params);
            foreach ($rows as $r) $recipientIds[] = (int)$r['id'];
        }

        // 去重 + 数量兜底
        $recipientIds = array_values(array_unique(array_filter($recipientIds, function ($v) { return $v > 0; })));
        if (empty($recipientIds)) $this->error('未匹配到任何可发送的用户（请检查目标 / 是否账号被禁用）');

        // 写入日志（先写日志拿 id）
        $now = date('Y-m-d H:i:s');
        $logId = Model::table('system_notification_logs')->insert([
            'title' => mb_substr($title, 0, 100),
            'content' => mb_substr($content, 0, 255),
            'link' => $link !== '' ? $link : null,
            'target_type' => $targetType,
            'target_value' => $targetValue,
            'sender_id' => (int)Auth::id(),
            'sent_count' => count($recipientIds),
            'created_at' => $now,
        ]);
        if (!$logId) $this->error('写入日志失败');

        // 批量插入 notifications（is_system_notification=1）
        // 用事务 + 多值 INSERT 提升性能（每条 100 个 VALUES）
        try {
            Database::pdo()->beginTransaction();
            $chunks = array_chunk($recipientIds, 100);
            $totalInserted = 0;
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '(?, ?, ?, ?, 0, 1, ?)'));
                $params = [];
                foreach ($chunk as $_) {
                    $params[] = $_;                                  // user_id
                    $params[] = 'system';                           // type
                    $params[] = $display;                           // content
                    $params[] = ($link !== '' ? $link : null);       // link
                    $params[] = $now;
                }
                $stmt = Database::pdo()->prepare("INSERT INTO notifications (user_id, type, content, link, is_read, is_system_notification, created_at) VALUES $placeholders");
                $stmt->execute($params);
                $totalInserted += $stmt->rowCount();
            }
            Database::pdo()->commit();
            $this->success(['sent' => $totalInserted, 'log_id' => $logId], "已成功向 {$totalInserted} 名用户发送通知");
        } catch (\Throwable $e) {
            if (Database::pdo()->inTransaction()) Database::pdo()->rollBack();
            // 不删除日志：留给站长在历史里看到「发送失败」
            Model::table('system_notification_logs')->where('id', $logId)->update([
                'sent_count' => 0,
            ]);
            $this->error('通知批量插入失败：' . $e->getMessage());
        }
    }

    /* ========== 固定连接（permalink）========== */

    /**
     * 固定连接设置页（仅站长）
     */
    public function permalinkSettings()
    {
        if (!is_webmaster()) { flash('仅站长可访问「固定连接」', 'error'); redirect(url('admin/index')); return; }
        $current = permalink_structure();
        // 统计 url_slug 缺失的帖子数（用于提示一键补全）
        try {
            $missingSlug = (int)Model::query("SELECT COUNT(*) AS c FROM posts WHERE url_slug IS NULL OR url_slug = ''")[0]['c'];
        } catch (Throwable $e) {
            $missingSlug = 0;
        }
        $this->view('admin/permalink_settings', [
            'current' => $current,
            'missing_slug_count' => $missingSlug,
        ]);
    }

    /**
     * 保存 permalink 结构 + 触发历史 url_slug 补全（仅站长）
     */
    public function savePermalinkSettings()
    {
        if (!is_webmaster()) $this->error('仅站长可保存');
        $structure = trim((string)input('permalink_structure', 'id'));
        $allowed = ['default', 'id', 'cat_id', 'title', 'cat_title'];
        if (!in_array($structure, $allowed, true)) $structure = 'id';

        // 写 settings
        $row = Model::table('settings')->where('key_name', 'permalink_structure')->first();
        if ($row) {
            Model::table('settings')->where('key_name', 'permalink_structure')->update([
                'value' => $structure,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            Model::table('settings')->insert([
                'key_name' => 'permalink_structure',
                'value' => $structure,
                'description' => '前台公开页 permalink 结构（default/id/cat_id/title/cat_title）',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // 触发历史 url_slug 补全（仅在选了 title/cat_title 时才有意义；id/cat_id 模式下补了也无害，但为了体感还是只在确实需要时跑）
        $filled = 0;
        if (in_array($structure, ['title', 'cat_title'], true)) {
            try {
                $rows = Model::query("SELECT id, title FROM posts WHERE url_slug IS NULL OR url_slug = ''");
                foreach ($rows as $r) {
                    $newSlug = generate_unique_slug($r['title'], (int)$r['id']);
                    Model::table('posts')->where('id', (int)$r['id'])->update(['url_slug' => $newSlug]);
                    $filled++;
                }
            } catch (Throwable $e) {
                // 忽略：单个 slug 生成失败不应阻塞主流程
            }
        }

        $this->success([
            'structure' => $structure,
            'filled' => $filled,
        ], "固定连接已保存" . ($filled > 0 ? "，已为 {$filled} 条历史帖子生成 url_slug" : ''));
    }

    // ============ 积分充值系统 ============

    /**
     * 卡密管理（生成 / 列表 / 导出 / 作废）
     */
    public function rechargeCards()
    {
        if (!is_webmaster()) { flash('无权限：仅站长可访问充值系统', 'error'); redirect(url('admin/index')); return; }
        extract($this->loadPointsSystemBaseData());
        $page = max(1, (int)input('page', 1));
        $status = input('status', '');
        $kw = trim(input('q', ''));
        $q = Model::table('recharge_cards')
            ->leftJoin('users', 'users.id', '=', 'recharge_cards.used_by')
            ->select('recharge_cards.*', 'users.username as used_username');
        if ($status) $q->where('recharge_cards.status', $status);
        if ($kw) $q->whereLike('recharge_cards.card_no', '%' . $kw . '%');
        $pag = $q->orderBy('recharge_cards.id', 'DESC')->paginate($page, 20);
        // 渲染前为每条卡密计算展示状态与可删标记：
        //   status='used' 且 used_at 距今 < 7 天 → 状态显示「对账期」（不可删）
        //   status='used' 且度过 7 天           → 状态显示「已使用」（可删）
        //   其它状态沿用原文案；仅 invalid 或 (used 且超 7 天) 可删
        $statusText = ['unused' => '未使用', 'used' => '已使用', 'expired' => '已过期', 'invalid' => '已作废'];
        $lockSec = 7 * 86400;
        $now = time();
        foreach ($pag['data'] as &$c) {
            if (($c['status'] ?? '') === 'used') {
                $usedAt = !empty($c['used_at']) ? strtotime($c['used_at']) : 0;
                if ($usedAt > 0 && ($now - $usedAt) >= $lockSec) {
                    $c['status_label'] = '已使用';
                    $c['can_delete']   = true;
                } else {
                    $c['status_label'] = '对账期';
                    $c['can_delete']   = false;
                    $c['lock_days']    = $usedAt > 0 ? max(1, (int)ceil(($usedAt + $lockSec - $now) / 86400)) : 7;
                }
            } else {
                $c['status_label'] = $statusText[$c['status']] ?? ($c['status'] ?? '');
                $c['can_delete']   = (($c['status'] ?? '') === 'invalid');
            }
        }
        unset($c);
        $stats = [
            'total'   => (int)Model::scalar("SELECT COUNT(*) FROM recharge_cards"),
            'used'    => (int)Model::scalar("SELECT COUNT(*) FROM recharge_cards WHERE status='used'"),
            'unused'  => (int)Model::scalar("SELECT COUNT(*) FROM recharge_cards WHERE status='unused'"),
            'expired' => (int)Model::scalar("SELECT COUNT(*) FROM recharge_cards WHERE status IN ('expired','invalid')"),
        ];
        $this->view('admin/points_system', [
            'rules'         => $rules,
            'risk'          => $risk,
            'levels'        => $levels,
            'currencies'    => $currencies,
            'rewardCfg'     => $rewardCfg,
            'currencyCodes' => array_values(array_unique($currencyCodes)),
            'currentTab'    => 'recharge',
            'rechargeSub'   => 'cards',
            'rcData'        => [
                'cards'             => $pag['data'],
                'pagination'        => $pag,
                'stats'             => $stats,
                'filters'           => ['status' => $status, 'q' => $kw],
                'currenciesEnabled' => PointService::currencies(true),
            ],
            'title'         => '充值中心 · 卡密管理',
        ]);
    }

    /**
     * 批量生成卡密（ajax）
     */
    public function rechargeGenerate()
    {
        if (!is_webmaster()) { $this->error('无权限', 403); return; }
        try {
            $prefix   = trim(input('prefix', 'VIP-'));
            $count    = (int)input('count', 1);
            $amount   = (int)input('amount', 0);
            $currency = trim(input('currency', 'byte'));
            $expire   = (int)input('expire_days', 0); // 0=永久
            $note     = trim(input('batch_note', ''));
            if ($amount <= 0) { $this->error('面值必须大于 0'); return; }
            if (!PointService::currencyExists($currency)) { $this->error('币种不存在'); return; }
            if ($count < 1 || $count > 500) { $this->error('数量需在 1-500 之间'); return; }
            $rows = RechargeService::generateCards($prefix, $count, $amount, $currency, $expire, $note);
            if (empty($rows)) { $this->error('未生成任何卡密，请重试'); return; }
            $this->success(['count' => count($rows), 'sample' => $rows[0] ?? ''], '已生成 ' . count($rows) . ' 张卡密');
        } catch (\Throwable $e) {
            $this->error('卡密生成失败：' . $e->getMessage());
            error_log('[rechargeGenerate] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /**
     * 导出卡密 CSV
     */
    public function rechargeCardExport()
    {
        if (!is_webmaster()) { $this->error('无权限', 403); return; }
        $status = input('status', '');
        $q = Model::table('recharge_cards');
        if ($status) $q->where('status', $status);
        $rows = $q->orderBy('id', 'DESC')->get();
        header('Content-Type: text/csv; charset=utf-8-sig');
        header('Content-Disposition: attachment; filename="recharge_cards_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['卡号', '面值', '币种', '前缀', '生成时间', '状态', '使用时间', '充值账户', '有效期至']);
        foreach ($rows as $r) {
            $uname = '';
            if ($r['used_by']) {
                $u = Model::table('users')->select('username')->where('id', (int)$r['used_by'])->first();
                $uname = $u ? ($u['username'] . ' (uid:' . $r['used_by'] . ')') : ('uid:' . $r['used_by']);
            }
            fputcsv($out, [
                $r['card_no'], $r['amount'], PointService::currencyLabel($r['currency']), $r['prefix'],
                $r['created_at'], $r['status'], $r['used_at'], $uname, $r['expire_at'],
            ]);
        }
        fclose($out);
        exit;
    }

    /**
     * 作废卡密（单张 / 批量）
     */
    public function rechargeCardInvalidate()
    {
        if (!is_webmaster()) { $this->error('无权限', 403); return; }
        try {
            $ids = input('ids', []);
            if (!is_array($ids)) $ids = [$ids];
            $ids = array_filter(array_map('intval', $ids), function ($x) { return $x > 0; });
            if (empty($ids)) { $this->error('请选择要作废的卡密'); return; }
            $n = Model::table('recharge_cards')
                ->whereIn('id', $ids)
                ->where('status', 'unused') // 仅未使用的可作废
                ->update(['status' => 'invalid']);
            $this->success(['affected' => $n], '已作废 ' . $n . ' 张卡密');
        } catch (\Throwable $e) {
            $this->error('作废失败：' . $e->getMessage());
            error_log('[rechargeCardInvalidate] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /**
     * 删除单张卡密（仅「已作废」可删；ajax）
     */
    public function rechargeCardDelete()
    {
        if (!is_webmaster()) { $this->error('无权限', 403); return; }
        try {
            $id = (int)input('id', 0);
            $res = RechargeService::deleteCard($id);
            if (!$res['ok']) { $this->error($res['msg']); return; }
            $this->success(['affected' => $res['affected'] ?? 1], '已删除');
        } catch (\Throwable $e) {
            $this->error('删除失败：' . $e->getMessage());
            error_log('[rechargeCardDelete] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /**
     * 批量删除卡密（仅「已作废」可删，其余跳过；ajax）
     */
    public function rechargeCardBatchDelete()
    {
        if (!is_webmaster()) { $this->error('无权限', 403); return; }
        try {
            $ids = input('ids', []);
            if (!is_array($ids)) $ids = [$ids];
            $res = RechargeService::batchDeleteCards($ids);
            if (!$res['ok']) { $this->error($res['msg']); return; }
            $msg = '已删除 ' . ($res['affected'] ?? 0) . ' 张卡密';
            if (($res['skipped'] ?? 0) > 0) $msg .= '，跳过 ' . $res['skipped'] . ' 张（仅已作废可删）';
            $this->success(['affected' => $res['affected'] ?? 0], $msg);
        } catch (\Throwable $e) {
            $this->error('删除失败：' . $e->getMessage());
            error_log('[rechargeCardBatchDelete] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /**
     * 折扣活动管理
     */
    public function rechargeCampaigns()
    {
        if (!is_webmaster()) { flash('无权限', 'error'); redirect(url('admin/index')); return; }
        extract($this->loadPointsSystemBaseData());
        $list = Model::table('recharge_campaigns')->orderBy('id', 'DESC')->get();
        foreach ($list as &$c) {
            $c['channels_arr'] = json_decode($c['channels'] ?? '[]', true) ?: [];
        }
        unset($c);
        $this->view('admin/points_system', [
            'rules'         => $rules,
            'risk'          => $risk,
            'levels'        => $levels,
            'currencies'    => $currencies,
            'rewardCfg'     => $rewardCfg,
            'currencyCodes' => array_values(array_unique($currencyCodes)),
            'currentTab'    => 'recharge',
            'rechargeSub'   => 'campaigns',
            'rcData'        => ['campaigns' => $list],
            'title'         => '充值中心 · 折扣活动',
        ]);
    }

    /**
     * 新增折扣活动（ajax）
     */
    public function rechargeCampaignAdd()
    {
        if (!is_webmaster()) { $this->error('无权限', 403); return; }
        try {
            $channels = input('channels', []);
            if (!is_array($channels)) $channels = [];
            $res = RechargeService::addCampaign([
                'name'       => input('name', ''),
                'type'       => input('type', 'bonus'),
                'value'      => input('value', 0),
                'channels'   => $channels,
                'start_at'   => input('start_at', ''),
                'end_at'     => input('end_at', ''),
                'min_amount' => input('min_amount', 0),
                'cap'        => input('cap', 0),
            ]);
            if (!$res['ok']) { $this->error($res['msg']); return; }
            $this->success(['id' => $res['id'] ?? 0], '活动已添加');
        } catch (\Throwable $e) {
            $this->error('活动添加失败：' . $e->getMessage());
            error_log('[rechargeCampaignAdd] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /**
     * 活动启用 / 停用（ajax）
     */
    public function rechargeCampaignToggle()
    {
        if (!is_webmaster()) { $this->error('无权限', 403); return; }
        try {
            $id = (int)input('id', 0);
            $enabled = input('enabled', 1) ? 1 : 0;
            RechargeService::toggleCampaign($id, $enabled);
            $this->success([], '已更新');
        } catch (\Throwable $e) {
            $this->error($e->getMessage() ?: '活动更新失败：' . $e->getMessage());
            error_log('[rechargeCampaignToggle] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /**
     * 删除活动（仅停用的活动可删除；ajax）
     */
    public function rechargeCampaignDelete()
    {
        if (!is_webmaster()) { $this->error('无权限', 403); return; }
        try {
            $id = (int)input('id', 0);
            $res = RechargeService::deleteCampaign($id);
            if (!$res['ok']) { $this->error($res['msg']); return; }
            $this->success([], '活动已删除');
        } catch (\Throwable $e) {
            $this->error('活动删除失败：' . $e->getMessage());
            error_log('[rechargeCampaignDelete] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /**
     * 支付通道配置
     */
    public function rechargeConfig()
    {
        if (!is_webmaster()) { flash('无权限', 'error'); redirect(url('admin/index')); return; }
        extract($this->loadPointsSystemBaseData());
        $cfg = RechargeService::getConfig();
        // 保证模板渲染时每个已启用的币种都有一条 base_rates 槽位（首次访问某新币种时也会显示默认行）
        $currenciesEnabled = PointService::currencies(true);
        foreach ($currenciesEnabled as $cc) {
            $code = (string)($cc['code'] ?? '');
            if ($code === '') continue;
            if (!array_key_exists($code, $cfg['base_rates'])) {
                $cfg['base_rates'][$code] = RechargeService::DEFAULT_BASE_RATE;
            }
        }
        $this->view('admin/points_system', [
            'rules'             => $rules,
            'risk'              => $risk,
            'levels'            => $levels,
            'currencies'        => $currencies,
            'currenciesEnabled' => $currenciesEnabled,
            'rewardCfg'         => $rewardCfg,
            'currencyCodes'     => array_values(array_unique($currencyCodes)),
            'currentTab'        => 'recharge',
            'rechargeSub'       => 'config',
            'rcData'            => ['cfg' => $cfg],
            'title'             => '充值中心 · 支付配置',
        ]);
    }

    /**
     * 保存支付通道配置（ajax）
     */
    public function rechargeConfigSave()
    {
        if (!is_webmaster()) { $this->error('无权限', 403); return; }
        try {
            $cfg = RechargeService::getConfig();
            // 按币种分别配置的基础比例 base_rates[code] = N；前端会传所有已启用币种；服务端以输入为准
            $rates = input('base_rates', []);
            $mergedRates = [];
            if (is_array($rates)) {
                // 关键：先按当前已启用的币种填默认槽，避免删除币种后旧比例残留
                $enabled = PointService::currencies(true);
                foreach ($enabled as $cc) {
                    $code = (string)($cc['code'] ?? '');
                    if ($code === '') continue;
                    $mergedRates[$code] = isset($rates[$code]) ? max(1, (int)$rates[$code]) : RechargeService::DEFAULT_BASE_RATE;
                }
                // 后端再补：前端可能没传过来的非启用币种，仍按当前 base_rates 保留
                if (isset($cfg['base_rates']) && is_array($cfg['base_rates'])) {
                    foreach ($cfg['base_rates'] as $code => $val) {
                        $code = (string)$code;
                        if ($code !== '' && !array_key_exists($code, $mergedRates)) {
                            $mergedRates[$code] = max(1, (int)$val);
                        }
                    }
                }
            } else {
                $mergedRates = is_array($cfg['base_rates'] ?? null) ? $cfg['base_rates'] : [];
            }
            $cfg['base_rates'] = $mergedRates;
            // 启用的方案（互斥：同时仅一个；空字符串 = 无）
            $enable = input('enabled_scheme', $cfg['enabled_scheme']);
            if (!in_array($enable, ['personal', 'aggregate', 'merchant', ''], true)) {
                $this->error('无效的支付方案'); return;
            }
            $cfg['enabled_scheme'] = $enable;
            // 各方案字段（取原值合并，避免覆盖未传字段）
            $p = input('personal', []);
            if (is_array($p)) {
                $cfg['schemes']['personal']['wechat_qr']  = $p['wechat_qr']  ?? ($cfg['schemes']['personal']['wechat_qr']  ?? '');
                $cfg['schemes']['personal']['alipay_qr']  = $p['alipay_qr']  ?? ($cfg['schemes']['personal']['alipay_qr']  ?? '');
                $cfg['schemes']['personal']['tail_precision'] = (int)($p['tail_precision'] ?? ($cfg['schemes']['personal']['tail_precision'] ?? 2));
            }
            $a = input('aggregate', []);
            if (is_array($a)) {
                foreach (['provider', 'merchant_id', 'key', 'notify_url'] as $k) {
                    if (isset($a[$k])) $cfg['schemes']['aggregate'][$k] = $a[$k];
                }
            }
            $m = input('merchant', []);
            if (is_array($m)) {
                foreach (['appid', 'mch_id', 'api_v3_key', 'cert_path', 'key_path', 'notify_url'] as $k) {
                    if (isset($m['wechat'][$k])) $cfg['schemes']['merchant']['wechat'][$k] = $m['wechat'][$k];
                }
                foreach (['app_id', 'private_key', 'public_key', 'gateway'] as $k) {
                    if (isset($m['alipay'][$k])) $cfg['schemes']['merchant']['alipay'][$k] = $m['alipay'][$k];
                }
            }
            RechargeService::saveConfig($cfg);
            $this->success([], '配置已保存，当前启用方案：' . ($enable ?: '无'));
        } catch (\Throwable $e) {
            $this->error('配置保存失败：' . $e->getMessage());
            error_log('[rechargeConfigSave] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /**
     * 充值订单（后台查看）
     */
    public function rechargeOrders()
    {
        if (!is_webmaster()) { flash('无权限', 'error'); redirect(url('admin/index')); return; }
        extract($this->loadPointsSystemBaseData());
        $page = max(1, (int)input('page', 1));
        $ch = input('channel', '');
        $q = Model::table('recharge_orders');
        if ($ch) $q->where('channel', $ch);
        $pag = $q->orderBy('id', 'DESC')->paginate($page, 30);
        $this->view('admin/points_system', [
            'rules'         => $rules,
            'risk'          => $risk,
            'levels'        => $levels,
            'currencies'    => $currencies,
            'rewardCfg'     => $rewardCfg,
            'currencyCodes' => array_values(array_unique($currencyCodes)),
            'currentTab'    => 'recharge',
            'rechargeSub'   => 'orders',
            'rcData'        => [
                'orders'     => $pag['data'],
                'pagination' => $pag,
                'filter_ch'  => $ch,
            ],
            'title'         => '充值中心 · 充值订单',
        ]);
    }

    /* ========== 表情管理（站长专属，2026-09-03 新增） ==========
     * 数据：emoji_packs / emoji_items（install/upgrade.php 升级 62 已建表 + 默认三套数据）
     * 权限：仅站长可管理（is_webmaster()）；前台公开接口 /emoji/list 由 EmojiController 提供
     */
    public function emojiPacks()
    {
        if (!is_webmaster()) { flash('仅站长可访问「表情管理」', 'error'); redirect(url('admin/index')); return; }
        $packs = Model::query(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM emoji_items WHERE pack_id = p.id) AS item_count,
                    (SELECT COUNT(*) FROM emoji_items WHERE pack_id = p.id AND enabled = 1) AS enabled_item_count
               FROM emoji_packs p
              ORDER BY p.is_system DESC, p.sort_order ASC, p.id ASC"
        );
        $this->view('admin/emoji_packs', ['packs' => $packs]);
    }

    public function emojiPackEdit($id = null)
    {
        if (!is_webmaster()) { flash('仅站长可访问「表情管理」', 'error'); redirect(url('admin/index')); return; }
        // 路由只解析 controller+action，第三个 path 段会被丢弃；id 实际从 query string 来
        $id = (int)($id ?? input('id', 0));
        $pack = null;
        $items = [];
        if ($id > 0) {
            $pack = Model::table('emoji_packs')->where('id', $id)->first();
            if (!$pack) { flash('表情包不存在', 'error'); redirect(url('admin/emojiPacks')); return; }
            $items = Model::query(
                "SELECT * FROM emoji_items WHERE pack_id = ? ORDER BY category ASC, sort_order ASC, id ASC",
                [$pack['id']]
            );
        }
        $this->view('admin/emoji_pack_edit', ['pack' => $pack, 'items' => $items]);
    }

    public function emojiPackSave()
    {
        if (!is_webmaster()) $this->error('仅站长可管理表情');
        $id = (int)input('id', 0);
        $name = trim((string)input('name', ''));
        $slug = trim((string)input('slug', ''));
        $type = trim((string)input('type', 'unicode'));
        $cd = trim((string)input('cd', ''));
        $cover = trim((string)input('cover', ''));
        $sortOrder = (int)input('sort_order', 0);

        if ($id === 0) {
            // 新建自定义 pack
            if ($name === '') $this->error('表情包名不能为空');
            if (!preg_match('/^[a-z0-9_]{1,32}$/', $slug)) $this->error('slug 格式错误（仅小写字母/数字/下划线，1-32 字符）');
            if (!in_array($type, ['unicode', 'image'], true)) $this->error('type 必须是 unicode 或 image');
            $exists = Model::table('emoji_packs')->where('slug', $slug)->first();
            if ($exists) $this->error('slug 已存在：' . $slug);
            $now = date('Y-m-d H:i:s');
            $newId = Model::table('emoji_packs')->insert([
                'name'       => $name,
                'slug'       => $slug,
                'type'       => $type,
                'cd'         => $cd !== '' ? $cd : null,
                'cover'      => $cover !== '' ? $cover : null,
                'sort_order' => $sortOrder,
                'enabled'    => 1,
                'is_system'  => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->success(['id' => $newId], '表情包已创建');
        }

        $pack = Model::table('emoji_packs')->where('id', $id)->first();
        if (!$pack) $this->error('表情包不存在');

        // 校验：编辑模式所有字段都可改（系统包/自定义包一视同仁），仅「删除」受限（系统包不可删，避免误删破坏基础数据）
        if ($name === '') $this->error('表情包名不能为空');
        if ($slug !== '' && !preg_match('/^[a-z0-9_]{1,32}$/', $slug)) {
            $this->error('slug 格式错误（仅小写字母/数字/下划线，1-32 字符）');
        }
        if (!in_array($type, ['unicode', 'image'], true)) $this->error('type 必须是 unicode 或 image');
        if ($slug !== '' && $slug !== $pack['slug']) {
            $exists = Model::table('emoji_packs')->where('slug', $slug)->first();
            if ($exists) $this->error('slug 已存在：' . $slug);
        }

        // ⚠️ 切类型会破坏现有 items：unicode→image 会让 char 字段全空，image→unicode 会让 image 字段全空。
        // 提示一次让站长知晓，但不强阻断（实测后再次确认）。
        $typeChanged = ($type !== $pack['type']);

        Model::table('emoji_packs')->where('id', $id)->update([
            'name'       => $name,
            'slug'       => $slug !== '' ? $slug : $pack['slug'],
            'type'       => $type,
            'cd'         => $cd !== '' ? $cd : null,
            'cover'      => $cover !== '' ? $cover : null,
            'sort_order' => $sortOrder,
            'enabled'    => (int)input('enabled', 1),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $msg = '表情包已更新';
        if ($typeChanged) $msg .= '（已修改类型，注意补全各表情的「字符/图片文件名」字段）';
        $this->success(null, $msg);
    }

    public function emojiPackToggle()
    {
        if (!is_webmaster()) $this->error('仅站长可管理表情');
        $id = (int)input('id', 0);
        $pack = Model::table('emoji_packs')->where('id', $id)->first();
        if (!$pack) $this->error('表情包不存在');
        $next = (int)$pack['enabled'] ? 0 : 1;
        Model::table('emoji_packs')->where('id', $id)->update([
            'enabled'    => $next,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->success(['enabled' => $next], $next ? '已启用' : '已停用');
    }

    public function emojiPackDelete()
    {
        if (!is_webmaster()) $this->error('仅站长可管理表情');
        $id = (int)input('id', 0);
        $pack = Model::table('emoji_packs')->where('id', $id)->first();
        if (!$pack) $this->error('表情包不存在');
        if (!empty($pack['is_system'])) $this->error('系统内置表情包不可删除');
        // 先清 items 再删 pack（无事务，量小可接受；失败可在下次清理）
        Model::execute("DELETE FROM emoji_items WHERE pack_id = ?", [$id]);
        Model::table('emoji_packs')->where('id', $id)->delete();
        $this->success(null, '表情包已删除');
    }

    public function emojiItemSave()
    {
        if (!is_webmaster()) $this->error('仅站长可管理表情');
        $id = (int)input('id', 0);
        $packId = (int)input('pack_id', 0);
        $pack = Model::table('emoji_packs')->where('id', $packId)->first();
        if (!$pack) $this->error('表情包不存在');

        $code      = trim((string)input('code', ''));
        $name      = trim((string)input('name', ''));
        $keywords  = trim((string)input('keywords', ''));
        $char      = trim((string)input('char', ''));
        $image     = trim((string)input('image', ''));
        $category  = trim((string)input('category', 'other')) ?: 'other';
        $sortOrder = (int)input('sort_order', 0);

        if (!preg_match('/^[a-z0-9_]{1,32}$/', $code)) $this->error('短代码格式错误（仅小写字母/数字/下划线，1-32 字符）');
        if ($name === '') $this->error('中文名不能为空');
        if ($pack['type'] === 'unicode' && $char === '') $this->error('unicode 类型必须填写「字符」');
        if ($pack['type'] === 'image' && $image === '') $this->error('image 类型必须填写「文件名（codepoint 或相对路径）」');

        $now = date('Y-m-d H:i:s');
        if ($id === 0) {
            $exists = Model::table('emoji_items')->where('code', $code)->first();
            if ($exists) $this->error('短代码已存在：' . $code);
            $newId = Model::table('emoji_items')->insert([
                'pack_id'    => $packId,
                'code'       => $code,
                'name'       => $name,
                'keywords'   => $keywords,
                'char'       => $char !== '' ? $char : null,
                'image'      => $image !== '' ? $image : null,
                'category'   => $category,
                'sort_order' => $sortOrder,
                'enabled'    => 1,
                'created_at' => $now,
            ]);
            $this->success(['id' => $newId], '表情已添加');
        }

        $item = Model::table('emoji_items')->where('id', $id)->first();
        if (!$item) $this->error('表情不存在');
        Model::table('emoji_items')->where('id', $id)->update([
            'code'       => $code,
            'name'       => $name,
            'keywords'   => $keywords,
            'char'       => $char !== '' ? $char : null,
            'image'      => $image !== '' ? $image : null,
            'category'   => $category,
            'sort_order' => $sortOrder,
        ]);
        $this->success(null, '表情已更新');
    }

    public function emojiItemToggle()
    {
        if (!is_webmaster()) $this->error('仅站长可管理表情');
        $id = (int)input('id', 0);
        $item = Model::table('emoji_items')->where('id', $id)->first();
        if (!$item) $this->error('表情不存在');
        $next = (int)$item['enabled'] ? 0 : 1;
        Model::table('emoji_items')->where('id', $id)->update(['enabled' => $next]);
        $this->success(['enabled' => $next], $next ? '已启用' : '已停用');
    }

    public function emojiItemDelete()
    {
        if (!is_webmaster()) $this->error('仅站长可管理表情');
        $id = (int)input('id', 0);
        $item = Model::table('emoji_items')->where('id', $id)->first();
        if (!$item) $this->error('表情不存在');
        Model::table('emoji_items')->where('id', $id)->delete();
        $this->success(null, '表情已删除');
    }
}
