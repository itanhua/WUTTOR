<?php
/**
 * 后台管理控制器
 */
class AdminController extends Controller
{
    protected $middleware = [
        ['middleware' => ['auth', 'admin']],
        ['middleware' => 'csrf', 'only' => [
            'storeCategory', 'updateCategory', 'deleteCategory',
            'banUser', 'unbanUser', 'deleteUser', 'assignModerator', 'revokeModerator',
            'approveCert', 'rejectCert', 'batchCert', 'batchDeletePosts', 'batchDeleteComments',
            'handleReport', 'saveSettings', 'saveMailSettings', 'saveNavSettings', 'addSensitiveWord', 'deleteSensitiveWord',
            'createRole', 'updateRolePermissions', 'updateRole', 'deleteRole',
            'addCertItem', 'updateCertItem', 'deleteCertItem', 'saveCertSettings',
            'testMail', 'updateUser',
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

        $this->view('admin/users', [
            'users' => $users, 'total' => $total, 'page' => $page, 'perPage' => $perPage,
            'keyword' => $keyword, 'role' => $role, 'certStatus' => $certStatus,
        ]);
    }

    public function banUser($id = null)
    {
        $id = (int)($id ?? input('id'));
        $user = Model::table('users')->where('id', $id)->first();
        if (!$user) $this->error('用户不存在');
        if ($user['role'] === 'super_admin') $this->error('不能禁用超级管理员');
        Model::table('users')->where('id', $id)->update(['status' => 0]);
        $this->success(null, '已禁用用户');
    }

    public function unbanUser($id = null)
    {
        $id = (int)($id ?? input('id'));
        Model::table('users')->where('id', $id)->update(['status' => 1]);
        $this->success(null, '已启用用户');
    }

    public function deleteUser($id = null)
    {
        if (!is_super_admin()) $this->error('无权限');
        $id = (int)($id ?? input('id'));
        $user = Model::table('users')->where('id', $id)->first();
        if (!$user) $this->error('用户不存在');
        if ($user['role'] === 'super_admin') $this->error('不能删除超级管理员');
        Model::table('users')->where('id', $id)->delete();
        $this->success(null, '用户已删除');
    }

    public function assignModerator($id = null)
    {
        if (!is_super_admin()) $this->error('无权限');
        $id = (int)($id ?? input('id'));
        $catId = (int)input('category_id');
        $user = Model::table('users')->where('id', $id)->first();
        if (!$user) $this->error('用户不存在');
        Model::table('users')->where('id', $id)->update(['role' => 'moderator']);
        if ($catId) {
            Model::execute(
                'INSERT IGNORE INTO category_moderators (category_id, user_id, created_at) VALUES (?, ?, ?)',
                [$catId, $id, date('Y-m-d H:i:s')]
            );
        }
        $this->success(null, '已设为版主');
    }

    public function revokeModerator($id = null)
    {
        if (!is_super_admin()) $this->error('无权限');
        $id = (int)($id ?? input('id'));
        Model::table('users')->where('id', $id)->update(['role' => 'user']);
        Model::table('category_moderators')->where('user_id', $id)->delete();
        $this->success(null, '已撤销版主身份');
    }

    /**
     * 后台编辑用户（GET：返回 JSON 数据，供前端弹窗填充）
     */
    public function editUser($id = null)
    {
        if (!is_super_admin()) $this->error('无权限');
        $id = (int)($id ?? input('id'));
        $user = Model::table('users')->where('id', $id)->first();
        if (!$user) $this->error('用户不存在');
        // 脱敏：不返回密码
        unset($user['password_hash']);
        $this->success($user);
    }

    /**
     * 后台保存用户编辑（用户名、头像、邮箱、邮箱验证状态、认证状态、签名档、角色、封禁）
     */
    public function updateUser()
    {
        if (!is_super_admin()) $this->error('无权限');
        $id = (int)input('id');
        $user = Model::table('users')->where('id', $id)->first();
        if (!$user) $this->error('用户不存在');

        $data = ['updated_at' => date('Y-m-d H:i:s')];

        // 用户名（唯一）—— 跟前台统一按 name_strlen 加权（1汉字=2字符；字母/数字=1字符）
        $username = trim(input('username', ''));
        if ($username !== '' && $username !== $user['username']) {
            if (name_strlen($username) < 2 || name_strlen($username) > 50) $this->error('用户名长度需 2-50 个字节');
            $dup = Model::table('users')->where('username', $username)->where('id', '!=', $id)->first();
            if ($dup) $this->error('该用户名已被占用');
            $data['username'] = $username;
        }

        // 昵称（签名档之外的展示名，可选）
        $nickname = trim(input('nickname', ''));
        if ($nickname !== '') {
            if (mb_strlen($nickname) > 50) $this->error('昵称长度不能超过 50 个字符');
            $data['nickname'] = $nickname;
        }

        // 头像（URL 或留空）
        $avatar = trim(input('avatar', ''));
        $data['avatar'] = $avatar === '' ? null : $avatar;

        // 邮箱（唯一）
        $email = trim(input('email', ''));
        if ($email !== '' && $email !== $user['email']) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $this->error('邮箱格式不正确');
            $dup = Model::table('users')->where('email', $email)->where('id', '!=', $id)->first();
            if ($dup) $this->error('该邮箱已被占用');
            $data['email'] = $email;
        }

        // 邮箱验证状态
        if (input('email_verified') !== null) {
            $data['email_verified'] = (int)input('email_verified') ? 1 : 0;
        }

        // 账号认证状态（is_certified）+ 认证时间
        if (input('is_certified') !== null) {
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

        // 用户角色（不可将他人设为/取消超级管理员，自己也不能被降权）
        if (input('role') !== null) {
            $role = trim(input('role'));
            $allowed = ['user', 'moderator', 'admin', 'super_admin'];
            if (!in_array($role, $allowed, true)) $this->error('非法的用户角色');
            // 不允许编辑超级管理员（含自己）的角色，避免锁死后台
            if ($user['role'] === 'super_admin' || $id === (Auth::id() ?? -1)) {
                $this->error('超级管理员角色不可通过此方式修改');
            }
            $data['role'] = $role;
            // 若取消版主，清理版主关联
            if ($role !== 'moderator' && $user['role'] === 'moderator') {
                Model::table('category_moderators')->where('user_id', $id)->delete();
            }
        }

        // 封禁状态（status: 1 正常 / 0 封禁）；不允许封禁超级管理员
        if (input('status') !== null) {
            $status = (int)input('status');
            if ($user['role'] === 'super_admin') $this->error('不能封禁超级管理员');
            $data['status'] = $status === 1 ? 1 : 0;
        }

        Model::table('users')->where('id', $id)->update($data);
        $this->success(null, '用户资料已更新');
    }

    /* ========== 板块管理 ========== */
    public function categories()
    {
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
        $name = trim(input('name'));
        $desc = trim(input('description'));
        $icon = trim(input('icon')); // Font Awesome / iconfont 类名
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
        $id = (int)($id ?? input('id'));
        $cat = Model::table('categories')->where('id', $id)->first();
        if (!$cat) $this->error('板块不存在');
        $data = ['updated_at' => date('Y-m-d H:i:s')];
        foreach (['name', 'description', 'rule', 'icon', 'color'] as $f) {
            $v = input($f);
            if ($v !== null) $data[$f] = trim($v);
        }
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
        if (!is_super_admin()) $this->error('无权限');
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
        if (!is_super_admin()) $this->error('无权限');
        $ids = input('ids', []);
        if (empty($ids)) $this->error('请选择帖子');
        $count = 0;
        foreach ((array)$ids as $pid) {
            Model::table('posts')->where('id', (int)$pid)->update(['status' => 0]);
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
        if (!is_super_admin()) $this->error('无权限');
        $ids = input('ids', []);
        if (empty($ids)) $this->error('请选择评论');
        $count = 0;
        foreach ((array)$ids as $cid) {
            Model::table('comments')->where('id', (int)$cid)->update(['status' => 0]);
            $count++;
        }
        $this->success(['count' => $count], '已批量删除 ' . $count . ' 条');
    }

    /* ========== 认证审核 ========== */
    public function certifications()
    {
        $page = (int)input('page', 1);
        $status = input('status', '');
        $sql = "SELECT cert.*, u.username, u.nickname, u.avatar FROM certifications cert LEFT JOIN users u ON cert.user_id = u.id";
        $params = [];
        if ($status !== '') {
            $sql .= " WHERE cert.status = ?";
            $params[] = (int)$status;
        }
        $sql .= " ORDER BY cert.created_at DESC";
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
            "SELECT cert.*, u.username, u.nickname, u.avatar, u.email, u.created_at AS user_created, rev.nickname AS reviewer_name FROM certifications cert LEFT JOIN users u ON cert.user_id = u.id LEFT JOIN users rev ON cert.reviewer_id = rev.id WHERE cert.id = ?",
            [$id]
        );
        $cert = $cert[0] ?? null;
        if (!$cert) { View::render('errors/404', [], 404); return; }
        // 解密身份证号
        $cert['id_card_plain'] = decrypt_value($cert['id_card']);
        $this->view('admin/cert_detail', ['cert' => $cert]);
    }

    public function approveCert($id = null)
    {
        $id = (int)($id ?? input('id'));
        $cert = Model::table('certifications')->where('id', $id)->where('status', 0)->first();
        if (!$cert) $this->error('申请不存在或已处理');

        $now = date('Y-m-d H:i:s');
        Model::table('certifications')->where('id', $id)->update([
            'status' => 1, 'reviewer_id' => Auth::id(), 'reviewed_at' => $now, 'updated_at' => $now,
        ]);
        Model::table('users')->where('id', $cert['user_id'])->update([
            'is_certified' => 1, 'certified_at' => $now,
        ]);
        // 如果角色是 user，升级为 certified_user
        $user = Model::table('users')->where('id', $cert['user_id'])->first();
        if ($user && $user['role'] === 'user') {
            Model::table('users')->where('id', $cert['user_id'])->update(['role' => 'certified_user']);
        }
        // 通知
        Model::table('notifications')->insert([
            'user_id' => $cert['user_id'], 'type' => 'certification',
            'content' => '恭喜，您的实名认证已通过审核', 'link' => url('certification/index'),
            'is_read' => 0, 'created_at' => $now,
        ]);
        $this->success(null, '认证已通过');
    }

    public function rejectCert($id = null)
    {
        $id = (int)($id ?? input('id'));
        $reason = trim(input('reject_reason'));
        if (!$reason) $this->error('请填写驳回原因');
        $cert = Model::table('certifications')->where('id', $id)->where('status', 0)->first();
        if (!$cert) $this->error('申请不存在或已处理');

        $now = date('Y-m-d H:i:s');
        Model::table('certifications')->where('id', $id)->update([
            'status' => 2, 'reject_reason' => $reason, 'reviewer_id' => Auth::id(),
            'reviewed_at' => $now, 'updated_at' => $now,
        ]);
        Model::table('notifications')->insert([
            'user_id' => $cert['user_id'], 'type' => 'certification',
            'content' => '您的实名认证未通过，原因：' . $reason, 'link' => url('certification/index'),
            'is_read' => 0, 'created_at' => $now,
        ]);
        $this->success(null, '已驳回并通知用户');
    }

    public function batchCert()
    {
        if (!is_super_admin()) $this->error('无权限');
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
            Model::table($table)->where('id', $report['target_id'])->update(['status' => 0]);
        }
        $this->success(null, '举报已处理');
    }

    /* ========== 角色权限 ========== */
    public function roles()
    {
        if (!is_super_admin()) { flash('无权限访问', 'error'); redirect(url('admin/index')); return; }
        $roles = Model::table('roles')->get();
        $permissions = Model::table('permissions')->get();
        // 各角色已分配的权限
        $rolePerms = [];
        foreach ($roles as $r) {
            $perms = Model::query("SELECT permission_id FROM role_permissions WHERE role_id = ?", [$r['id']]);
            $rolePerms[$r['id']] = array_column($perms, 'permission_id');
        }
        $this->view('admin/roles', ['roles' => $roles, 'permissions' => $permissions, 'rolePerms' => $rolePerms]);
    }

    public function createRole()
    {
        if (!is_super_admin()) $this->error('无权限');
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
        if (!is_super_admin()) $this->error('无权限');
        $id = (int)($id ?? input('id'));
        $permIds = input('permission_ids', []);
        Model::execute('DELETE FROM role_permissions WHERE role_id = ?', [$id]);
        foreach ((array)$permIds as $pid) {
            Model::execute('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$id, (int)$pid]);
        }
        $this->success(null, '权限已更新');
    }

    /* ========== 系统设置 ========== */
    public function settings()
    {
        if (!is_super_admin()) { flash('无权限访问', 'error'); redirect(url('admin/index')); return; }
        $settings = Model::query("SELECT * FROM settings");
        $settingsMap = [];
        foreach ($settings as $s) $settingsMap[$s['key_name']] = $s['value'];
        $this->view('admin/settings', ['settings' => $settingsMap]);
    }

    /**
     * 邮箱设置页（独立二级菜单）
     */
    public function mailSettings()
    {
        if (!is_super_admin()) { flash('无权限访问', 'error'); redirect(url('admin/index')); return; }
        $settings = Model::query("SELECT * FROM settings");
        $settingsMap = [];
        foreach ($settings as $s) $settingsMap[$s['key_name']] = $s['value'];
        $this->view('admin/mail_settings', ['settings' => $settingsMap]);
    }

    /**
     * 导航设置页（独立二级菜单）
     */
    public function navSettings()
    {
        if (!is_super_admin()) { flash('无权限访问', 'error'); redirect(url('admin/index')); return; }
        $settings = Model::query("SELECT * FROM settings");
        $settingsMap = [];
        foreach ($settings as $s) $settingsMap[$s['key_name']] = $s['value'];
        $this->view('admin/nav_settings', ['settings' => $settingsMap]);
    }

    /**
     * 保存站点信息设置（仅处理站点信息表单提交的字段，避免覆盖其他设置）
     */
    public function saveSettings()
    {
        if (!is_super_admin()) $this->error('无权限');
        $certLimitDays = (int)input('cert_limit_days', 7);
        $siteDesc = trim(input('site_description', ''));
        $allowRegister = (int)input('allow_register', 1);
        $siteLogo = trim(input('site_logo', ''));

        $this->saveSetting('cert_limit_days', $certLimitDays, '认证申请频率限制（天）');
        $this->saveSetting('site_description', $siteDesc, '站点描述');
        $this->saveSetting('allow_register', $allowRegister, '是否允许注册');
        $this->saveSetting('site_logo', $siteLogo, '网站Logo URL');

        // 上传权限（按角色）
        $imgRoles = input('allow_image_roles', []);
        if (!is_array($imgRoles)) $imgRoles = $imgRoles === '' ? [] : explode(',', $imgRoles);
        $this->saveSetting('allow_image_roles', implode(',', array_filter($imgRoles)), '允许上传图片的角色');

        $attRoles = input('allow_attach_roles', []);
        if (!is_array($attRoles)) $attRoles = $attRoles === '' ? [] : explode(',', $attRoles);
        $this->saveSetting('allow_attach_roles', implode(',', array_filter($attRoles)), '允许上传附件的角色');

        // 更新站点标题副标题logo到配置文件
        $title = trim(input('site_title') ?? '');
        $subtitle = trim(input('site_subtitle') ?? '');
        if ($title) {
            $siteConfig = Config::get('site', []);
            $siteConfig['title'] = $title;
            $siteConfig['subtitle'] = $subtitle;
            $siteConfig['description'] = $title . ' - ' . $subtitle;
            $siteConfig['logo'] = $siteLogo;
            $php = "<?php\nreturn " . var_export($siteConfig, true) . ";\n";
            file_put_contents(CONFIG_PATH . '/site.php', $php);
        }
        $this->success(null, '设置已保存');
    }

    /**
     * 保存邮箱设置（仅处理邮箱表单提交的字段）
     */
    public function saveMailSettings()
    {
        if (!is_super_admin()) $this->error('无权限');
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
        if (!is_super_admin()) $this->error('无权限');

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
                $navMenus[$pos][] = [
                    'name' => $name,
                    'icon' => $icon,
                    'url'  => $url,
                ];
            }
        }
        $this->saveSetting('nav_menus', json_encode($navMenus, JSON_UNESCAPED_UNICODE), '导航菜单（顶部 + 侧边）');
        $this->success(null, '导航设置已保存');
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
     * 发送测试邮件（验证 SMTP 配置）
     */
    public function testMail()
    {
        if (!is_super_admin()) $this->error('无权限');
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
        if (!is_super_admin()) { flash('无权限访问', 'error'); redirect(url('admin/index')); return; }
        $page = (int)input('page', 1);
        $result = Model::table('sensitive_words')->orderBy('created_at', 'DESC')->paginate($page, 50);
        $this->view('admin/sensitive_words', ['words' => $result['data'], 'pagination' => $result, 'page' => $page]);
    }

    public function addSensitiveWord()
    {
        if (!is_super_admin()) $this->error('无权限');
        $word = trim(input('word'));
        $level = (int)input('level', 1);
        $scope = input('scope', 'all');
        if (!in_array($scope, ['all', 'username', 'content'])) $scope = 'all';
        if (!$word) $this->error('请输入敏感词');
        $exists = Model::table('sensitive_words')->where('word', $word)->first();
        if ($exists) $this->error('该敏感词已存在');
        Model::table('sensitive_words')->insert([
            'word' => $word, 'level' => $level, 'scope' => $scope, 'status' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->success(null, '敏感词已添加');
    }

    public function deleteSensitiveWord($id = null)
    {
        if (!is_super_admin()) $this->error('无权限');
        $id = (int)($id ?? input('id'));
        Model::table('sensitive_words')->where('id', $id)->delete();
        $this->success(null, '敏感词已删除');
    }

    /* ========== 认证项管理 ========== */
    public function certItems()
    {
        $items = Model::table('certification_items')->orderBy('sort_order', 'ASC')->get();
        $badge = Model::table('settings')->where('key_name', 'cert_badge_svg')->first();
        $this->view('admin/cert_items', ['items' => $items, 'badge' => $badge ? $badge['value'] : '']);
    }

    public function addCertItem()
    {
        $name = trim(input('name'));
        $label = trim(input('label'));
        $type = input('type', 'text');
        $options = trim(input('options', ''));
        $required = (int)input('required', 1);
        $sortOrder = (int)input('sort_order', 0);
        if (!$name || !$label) $this->error('字段名和标签不能为空');
        if (!in_array($type, ['text', 'textarea', 'image', 'select'])) $this->error('类型无效');
        $exists = Model::table('certification_items')->where('name', $name)->first();
        if ($exists) $this->error('字段名已存在');
        Model::table('certification_items')->insert([
            'name' => $name, 'label' => $label, 'type' => $type, 'options' => $options ?: null,
            'required' => $required, 'sort_order' => $sortOrder, 'status' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->success(null, '认证项已添加');
    }

    public function updateCertItem($id = null)
    {
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
        // 清除
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
        if (!is_super_admin()) $this->error('无权限');
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
        if (!is_super_admin()) $this->error('无权限');
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
}
