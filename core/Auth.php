<?php
/**
 * 认证与权限类（基于 Session）
 */
class Auth
{
    protected static $user = null;

    /**
     * 「记住我」持久化登录配置
     * - cookie 仅存随机 token（服务端存 SHA-256 哈希），HttpOnly + SameSite=Lax，HTTPS 下 Secure
     * - 有效期 30 天，临近过期（<7 天）自动轮换，实现滑动过期
     */
    const REMEMBER_COOKIE = 'th_remember';
    const REMEMBER_EXPIRY = 2592000; // 30 天（秒）

    public static function init()
    {
        if (!empty($_SESSION['user_id'])) {
            // 封禁用户仍可保持登录状态以便浏览，状态由业务层（banned 中间件）控制写操作
            $user = Model::table('users')->where('id', $_SESSION['user_id'])->first();
            if ($user) {
                self::$user = $user;
            } else {
                unset($_SESSION['user_id']);
            }
            return;
        }
        // 无 session：尝试用「记住我」cookie 恢复登录态（重启浏览器后仍然有效）
        self::tryRestoreFromRemember();
    }

    public static function user()
    {
        return self::$user;
    }

    public static function check()
    {
        return self::$user !== null;
    }

    public static function id()
    {
        return self::$user['id'] ?? null;
    }

    public static function role()
    {
        return self::$user['role'] ?? 'guest';
    }

    public static function login($userId)
    {
        $_SESSION['user_id'] = $userId;
        Model::table('users')->where('id', $userId)->update(['last_login_at' => date('Y-m-d H:i:s')]);
        self::init();
    }

    public static function logout()
    {
        self::clearRememberToken();
        unset($_SESSION['user_id']);
        self::$user = null;
        session_destroy();
    }

    /**
     * 用「记住我」cookie 恢复登录态
     * cookie 存 "user_id|token"，服务端 remember_tokens 表存 token 的 SHA-256 哈希 + 过期时间。
     * 仅当 cookie 存在时才查库；命中后恢复 session，临近过期自动轮换 token。
     */
    protected static function tryRestoreFromRemember()
    {
        if (empty($_COOKIE[self::REMEMBER_COOKIE])) return;
        $parts = explode('|', (string)$_COOKIE[self::REMEMBER_COOKIE], 2);
        if (count($parts) !== 2) { self::clearRememberCookie(); return; }
        list($userId, $token) = $parts;
        $userId = (int)$userId;
        $token  = (string)$token;
        if ($userId <= 0 || $token === '') { self::clearRememberCookie(); return; }

        $row = null;
        try {
            $row = Model::table('remember_tokens')
                ->where('user_id', $userId)
                ->where('token_hash', hash('sha256', $token))
                ->first();
        } catch (Exception $e) { return; }

        if (!$row) { self::clearRememberCookie(); return; }

        $expires = strtotime($row['expires_at']);
        if ($expires === false || $expires < time()) {
            try { Model::table('remember_tokens')->where('id', $row['id'])->delete(); } catch (Exception $e) {}
            self::clearRememberCookie();
            return;
        }

        $user = null;
        try { $user = Model::table('users')->where('id', $userId)->first(); } catch (Exception $e) {}
        if (!$user) { self::clearRememberCookie(); return; }

        // 恢复登录态（之后的请求会走正常 session，不再依赖此 cookie）
        $_SESSION['user_id'] = $userId;
        self::$user = $user;

        // 临近过期（剩余 < 7 天）时轮换 token，实现滑动过期，避免每个请求都写库
        if ($expires - time() < 7 * 24 * 3600) {
            self::rotateRememberToken($userId, $row['id']);
        }
    }

    /**
     * 登录时签发「记住我」token：生成随机 token，服务端存哈希，下发持久 cookie
     */
    public static function issueRememberToken($userId)
    {
        $token  = bin2hex(random_bytes(32));
        $expiry = time() + self::REMEMBER_EXPIRY;
        try {
            // 清理该用户已过期 token，避免无限堆积（保留其它设备有效 token）
            Model::table('remember_tokens')
                ->where('user_id', $userId)
                ->where('expires_at', '<', date('Y-m-d H:i:s'))
                ->delete();
            Model::table('remember_tokens')->insert([
                'user_id'    => $userId,
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', $expiry),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {}
        self::setRememberCookie($userId . '|' . $token, $expiry);
    }

    /**
     * 轮换 token：删除旧记录、写入新 token，刷新 cookie（仅在临近过期时调用，限流写库）
     */
    protected static function rotateRememberToken($userId, $oldId)
    {
        $token  = bin2hex(random_bytes(32));
        $expiry = time() + self::REMEMBER_EXPIRY;
        try {
            Model::table('remember_tokens')->where('id', $oldId)->delete();
            Model::table('remember_tokens')->insert([
                'user_id'    => $userId,
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', $expiry),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {}
        self::setRememberCookie($userId . '|' . $token, $expiry);
    }

    /**
     * 失效「记住我」：删除服务端 token 并清除 cookie（登出 / 改密码时调用）
     */
    public static function clearRememberToken($userId = null)
    {
        $id = $userId ?? self::id();
        if ($id) {
            try { Model::table('remember_tokens')->where('user_id', $id)->delete(); } catch (Exception $e) {}
        }
        self::clearRememberCookie();
    }

    protected static function setRememberCookie($value, $expiry)
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        $opts = [
            'expires'  => (int)$expiry,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if ($secure) $opts['secure'] = true;
        setcookie(self::REMEMBER_COOKIE, $value, $opts);
    }

    protected static function clearRememberCookie()
    {
        if (isset($_COOKIE[self::REMEMBER_COOKIE])) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            $opts = ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax'];
            if ($secure) $opts['secure'] = true;
            setcookie(self::REMEMBER_COOKIE, '', $opts);
        }
    }

    public static function isCertified()
    {
        return self::$user && self::$user['is_certified'] == 1;
    }

    /**
     * 角色等级
     */
    public static function roleLevel($role = null)
    {
        $role = $role ?? self::role();
        $levels = [
            'guest' => 0,
            'user' => 1,
            'certified_user' => 2,
            'moderator' => 3,
            'admin' => 4,
            'super_admin' => 5,
            'webmaster' => 6,  // 站长：最高权限，独立于 super_admin 之上
        ];
        return $levels[$role] ?? 0;
    }

    /**
     * 检查当前用户是否达到指定角色等级
     */
    public static function roleAtLeast($role)
    {
        return self::roleLevel() >= self::roleLevel($role);
    }

    /**
     * 进程内单次请求的权限结果缓存（避免同一请求重复查 DB）。
     * 注意：不做跨请求缓存 — 管理员在角色权限矩阵改动后立即生效。
     */
    protected static $permCache = [];

    /**
     * 权限检查 - 真正查 role_permissions 表（无文件/进程间缓存）。
     *
     * 判定顺序：
     *   1) 未登录 → false
     *   2) 站长（webmaster）→ 永远 true（系统最高权限，不受矩阵开关影响）
     *   3) 其他角色 → 联表查询 (users.role = roles.code) AND (role_permissions) AND (permissions.code = ?)
     *   4) 未在角色权限矩阵中显式勾选 → false（不再有硬编码默认）
     *
     * 提示：调用 controllers 之前请确保 Auth::init() 已执行（默认路由会执行）。
     */
    public static function can($permission)
    {
        $permission = (string)$permission;
        if ($permission === '') return false;
        $user = self::$user;
        if (!$user) return false;

        // 站长豁免：永远 true
        if (($user['role'] ?? '') === 'webmaster') return true;

        $uid = (int)$user['id'];
        $cacheKey = $uid . '|' . $permission;
        if (isset(self::$permCache[$cacheKey])) {
            return self::$permCache[$cacheKey];
        }

        try {
            $row = Model::query(
                "SELECT 1
                   FROM users u
                   JOIN roles r ON r.code = u.role
                   JOIN role_permissions rp ON rp.role_id = r.id
                   JOIN permissions p ON p.id = rp.permission_id
                  WHERE u.id = ? AND p.code = ?
                  LIMIT 1",
                [$uid, $permission]
            );
            $allowed = !empty($row);
        } catch (Exception $e) {
            // DB 异常时降级：避免站点整体瘫痪，按"无权限"处理
            $allowed = false;
        }

        self::$permCache[$cacheKey] = $allowed;
        return $allowed;
    }

    /**
     * 多权限"任一通过"
     */
    public static function canAny(array $permissions)
    {
        foreach ($permissions as $p) {
            if (self::can($p)) return true;
        }
        return false;
    }

    /**
     * 多权限"全部通过"
     */
    public static function canAll(array $permissions)
    {
        foreach ($permissions as $p) {
            if (!self::can($p)) return false;
        }
        return true;
    }

    /**
     * 各角色拥有的权限码
     */
    public static function rolePermissions($role)
    {
        $map = [
            'guest' => [
                'post.view', 'comment.view', 'category.view', 'user.view_public',
                'search',
            ],
            'user' => [
                'post.view', 'post.create', 'post.edit_own', 'post.delete_own',
                'comment.view', 'comment.create', 'comment.delete_own',
                'category.view', 'user.view_public',
                'social.like', 'social.collect', 'social.follow', 'social.message',
                'report.create', 'certification.apply', 'search',
                'profile.edit', 'notification.view',
            ],
            'certified_user' => [
                'post.view', 'post.create', 'post.edit_own', 'post.delete_own',
                'comment.view', 'comment.create', 'comment.delete_own',
                'category.view', 'category.certified_post', 'user.view_public',
                'social.like', 'social.collect', 'social.follow', 'social.message',
                'report.create', 'certification.apply', 'search',
                'profile.edit', 'notification.view', 'certification.badge',
            ],
            'moderator' => [
                '*_moderator', // 版主权限（在本版范围内）
                'post.view', 'post.create', 'post.edit_own', 'post.delete_own',
                'comment.view', 'comment.create', 'comment.delete_own',
                'category.view', 'user.view_public',
                'social.like', 'social.collect', 'social.follow', 'social.message',
                'report.create', 'search',
                'profile.edit', 'notification.view', 'certification.badge',
                'post.pin_section', 'post.essence_section', 'post.delete_section', 'post.move_section',
                'comment.hide_section', 'comment.delete_section',
            ],
            'admin' => [
                '*', // 全部权限
            ],
            'super_admin' => [
                '*',
            ],
            'webmaster' => [
                '*',  // 站长：所有权限（含 super_admin 已有的）+ 可修改 super_admin 的 role/status
            ],
        ];
        return $map[$role] ?? [];
    }

    /**
     * 是否是某版块的版主
     * 站长（webmaster）权限高于一切用户，视为所有板块的版主。
     */
    public static function isModeratorOf($categoryId)
    {
        if (!self::check()) return false;
        $r = self::role();
        if ($r === 'admin' || $r === 'super_admin' || $r === 'webmaster') return true;
        if (!self::id()) return false;
        $row = Model::table('category_moderators')
            ->where('category_id', (int)$categoryId)
            ->where('user_id', self::id())
            ->first();
        return $row ? true : false;
    }

    /**
     * 获取某板块的所有版主（数组，含 nickname/username）
     */
    public static function getModeratorsOf($categoryId)
    {
        return Model::query(
            'SELECT u.id, u.username, u.nickname, u.is_certified, u.show_cert_badges FROM category_moderators cm LEFT JOIN users u ON cm.user_id = u.id WHERE cm.category_id = ? ORDER BY cm.created_at ASC',
            [(int)$categoryId]
        );
    }
}
