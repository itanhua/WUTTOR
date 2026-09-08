<?php
/**
 * 辅助函数库
 */

if (!function_exists('isAjax')) {
    function isAjax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /**
     * 生成站内 URL
     * 当 permalink_structure != 'default' 且路由为 post/show / home/index / user/profile 时，
     * 自动改写为 pretty URL（/post/123、/c/5、/u/8、/），其他路由保持 ?r=... 原样。
     * @param string $route 路由如 home/index
     * @param array  $params 查询参数
     * @param bool   $absolute 是否返回绝对 URL（带 scheme+host）——用于邀请链接等需要在新窗口/邮件里点开的场景
     * @return string
     */
    function url($route = '', $params = [], $absolute = false) {
        // 尝试 pretty URL 改写（仅前台公开页：post/show, home/index, user/profile）
        $structure = permalink_structure();
        if ($structure !== 'default' && $route !== '') {
            $pretty = _url_make_pretty($route, $params, $structure);
            if ($pretty !== null) {
                $path = $pretty['path'];
                // 未被 pretty 规则"消费"掉的其它参数（如 sort / tab / page 等）保留为 query string，
                // 避免固定连接启用后排序 tab、带参板块导航点了没反应（之前被丢弃）。
                $consumed = $pretty['consumed'] ?? [];
                $extra = array_diff_key($params, array_flip($consumed));
                if (!empty($extra)) {
                    $path .= (strpos($path, '?') === false ? '?' : '&') . http_build_query($extra);
                }
                if ($absolute) {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    return $scheme . '://' . $host . $path;
                }
                return $path;
            }
        }

        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        $url = $base . '/index.php';
        $query = [];
        if ($route !== '') {
            $query['r'] = $route;
        }
        $query = array_merge($query, $params);
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        // 防御：pretty URL 启用且当前不在 /index.php 页面下时，强制输出**绝对 URL**（含 origin）。
        // 背景：nginx `try_files $uri $uri/ /index.php?$query_string;` 命中已存在的 /index.php 文件
        // 后**不重新匹配 location ~ \.php$**，会直接静态 serve（返回 404 或 PHP 源码），
        // 导致 POST 请求被拒；而 GET pretty URL 走的是 try_files 回退（内部 redirect）会重新匹配，
        // 所以表现是「GET 正常、POST 404」。输出绝对 URL 让 fetch 路径明确，规避这个坑。
        if ($structure !== 'default' && $absolute === false) {
            $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
            if (basename($reqPath) !== 'index.php') {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
                return $scheme . '://' . $host . $url;
            }
        }

        if ($absolute) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            return $scheme . '://' . $host . $url;
        }
        return $url;
    }
}

/**
 * 生成「带回跳目标」的登录页 URL。
 *
 * 背景（反复踩坑）：站内大量「登录」入口（右上角按钮、移动端抽屉、各处的「登录后XX」）
 * 原本都是裸的 url('auth/login')，URL 上不带任何来源信息。用户在公开页点登录后，
 * 后端 AuthController::login() 拿不到 redirect，登录成功只能回首页。
 * 只有中间件（core/Controller.php 'auth'）拦截未登录访问时，才会在 URL 上挂 ?redirect=。
 *
 * 用本函数生成登录链接，即可让「从任何页面点登录」都回到原页面。
 *
 * @param string|null $back 回跳目标（站内路径，如 /post/123）；留空则取当前 REQUEST_URI
 * @return string
 */
if (!function_exists('login_url')) {
    function login_url($back = null) {
        if ($back === null || $back === '') {
            $back = $_SERVER['REQUEST_URI'] ?? '';
        }
        $loginUrl = url('auth/login');
        if ($back === '') return $loginUrl;
        // 已经是登录/注册页自身时不再挂 redirect，避免登录后又被弹回认证页形成死循环
        if (preg_match('#/(auth/|login|logout|register)#i', (string)parse_url($back, PHP_URL_PATH))) {
            return $loginUrl;
        }
        $sep = (strpos($loginUrl, '?') === false) ? '?' : '&';
        return $loginUrl . $sep . 'redirect=' . urlencode($back);
    }
}

/**
 * 把 post/show / home/index / user/profile 转成 pretty URL；不支持时返回 null。
 * 内部静态缓存减少重复 DB 查询。
 */
if (!function_exists('_url_make_pretty')) {
    /**
     * 把 post/show / home/index / user/profile 转成 pretty URL；不支持时返回 null。
     * 返回数组 ['path' => '/post/123', 'consumed' => ['id']]，
     * consumed 记录已被编入路径的参数 key，便于 url() 把剩余参数（sort/tab 等）保留为 query string。
     */
    function _url_make_pretty($route, $params, $structure) {
        $consumed = [];
        // post/show → /post/{id}[-{slug}] 或 /c/{cat_id}/{id}[-{slug}]
        if ($route === 'post/show' && isset($params['id'])) {
            $id = (int)$params['id'];
            if ($id <= 0) return null;
            $post = _url_get_post_meta($id);
            if (!$post) return null; // 帖子不存在，回退到 ?r=
            $consumed[] = 'id';
            $catId = (int)($post['category_id'] ?? 0);
            $slug  = trim((string)($post['url_slug'] ?? ''));
            switch ($structure) {
                case 'id':
                    $path = "/post/{$id}"; break;
                case 'cat_id':
                    $path = $catId > 0 ? "/c/{$catId}/{$id}" : "/post/{$id}"; break;
                case 'title':
                    $path = $slug !== '' ? "/post/{$id}-{$slug}" : "/post/{$id}"; break;
                case 'cat_title':
                    if ($catId > 0 && $slug !== '')      $path = "/c/{$catId}/{$id}-{$slug}";
                    elseif ($catId > 0)                 $path = "/c/{$catId}/{$id}";
                    elseif ($slug !== '')               $path = "/post/{$id}-{$slug}";
                    else                                $path = "/post/{$id}";
                    break;
                default:
                    $path = "/post/{$id}";
            }
            return ['path' => $path, 'consumed' => $consumed];
        }

        // home/index?cat=N（N 为数字）→ /c/{cat_id}
        // 注意：非数字 cat（如认证专区 'certified'）不进入 pretty，交由 url() 回退 ?r=...&cat=certified
        if ($route === 'home/index' && isset($params['cat'])) {
            $cat = $params['cat'];
            if (is_numeric($cat) && (int)$cat > 0) {
                $consumed[] = 'cat';
                return ['path' => "/c/{$cat}", 'consumed' => $consumed];
            }
            return null;
        }
        // home/index（无参/含 sort 等）→ / （其余参数由 url() 追加为 query）
        if ($route === 'home/index') {
            return ['path' => "/", 'consumed' => $consumed];
        }

        // user/profile?id=N → /u/{id}
        if ($route === 'user/profile' && isset($params['id'])) {
            $id = (int)$params['id'];
            if ($id > 0) {
                $consumed[] = 'id';
                return ['path' => "/u/{$id}", 'consumed' => $consumed];
            }
        }

        return null;
    }
}

/**
 * 缓存单次请求内的 post meta 查询（id => [url_slug, category_id]）
 */
if (!function_exists('_url_get_post_meta')) {
    function _url_get_post_meta($id) {
        static $cache = [];
        $id = (int)$id;
        if (isset($cache[$id])) return $cache[$id];
        try {
            $row = Model::table('posts')->select('id', 'url_slug', 'category_id')->where('id', $id)->first();
        } catch (Throwable $e) {
            $row = null;
        }
        $cache[$id] = $row;
        return $row;
    }
}

/**
 * 读取当前 permalink 结构设置（默认 'default'：保持 ?r= 兼容形态）
 * 可选值：default / id / cat_id / title / cat_title
 *
 * 不要默认开 pretty —— 否则 nginx 没配 try_files 时全站 404。
 */
if (!function_exists('permalink_structure')) {
    function permalink_structure() {
        static $cached = null;
        if ($cached !== null) return $cached;
        try {
            $row = Model::table('settings')->where('key_name', 'permalink_structure')->first();
        } catch (Throwable $e) {
            $row = null;
        }
        $val = $row['value'] ?? '';
        if (!in_array($val, ['default', 'id', 'cat_id', 'title', 'cat_title'], true)) {
            // 未显式设置时保持 default（?r=... 兼容形态），
            // 不要默认开 pretty —— 否则 nginx 没配 try_files 时全站 404。
            $val = 'default';
        }
        $cached = $val;
        return $cached;
    }
}

/**
 * 把标题转成 URL slug
 * - 英文/数字：小写 + 短横线连接
 * - 中文：原样保留（UTF-8 URL 友好，Baidu/Google 均能索引中文关键词）
 * - 仅剔除：空白（压缩为 -）、标点、emoji、控制字符等
 * - 截断到 80 字符（中文按字符计，非字节）
 *
 * @param string $title  原始标题
 * @param int    $maxLen 最大长度（默认 80，与 posts.url_slug VARCHAR(200) 留足余量）
 * @return string 空时回退为空串（外层逻辑用 /post/{id} 兜底）
 */
if (!function_exists('slugify')) {
    /**
     * 把标题转成 URL slug
     * - 英文/数字：小写 + 短横线连接
     * - 中文：原样保留（UTF-8 URL 友好，Baidu/Google 均能索引中文关键词）
     * - 仅剔除：空白（压缩为 -）、标点、emoji、控制字符等
     * - 截断到 $maxLen 字符（中文按字符计，非字节）
     *
     * @param string $title  原始标题
     * @param int    $maxLen 最大长度（默认 80，与 posts.url_slug VARCHAR(200) 留足余量）
     * @return string
     */
    function slugify($title, $maxLen = 80) {
        $title = (string)$title;
        $title = mb_strtolower($title, 'UTF-8');
        // 空白（含全角空格）压缩为单个连字符
        $title = preg_replace('/\s+/u', '-', $title);
        // 仅保留：中文（CJK 基本区 U+4E00–U+9FFF）、小写字母、数字、连字符
        $title = preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}\-]/u', '', $title);
        $title = preg_replace('/-+/', '-', $title);
        $title = trim($title, '-');
        if (mb_strlen($title, 'UTF-8') > $maxLen) {
            $title = mb_substr($title, 0, $maxLen, 'UTF-8');
            $title = rtrim($title, '-');
        }
        return $title;
    }
}

/**
 * 帖子 URL（直接用 post 数组生成，零 DB 查询；推荐列表页/详情页使用）
 *  - $post 必须含 id, 可选 url_slug, category_id
 *  - 当 permalink_structure=default 时回退 ?r=post/show&id=
 */
if (!function_exists('post_url')) {
    function post_url($post, $absolute = false) {
        $structure = permalink_structure();
        if ($structure === 'default') {
            return url('post/show', ['id' => (int)($post['id'] ?? 0)], $absolute);
        }
        $id    = (int)($post['id'] ?? 0);
        $catId = (int)($post['category_id'] ?? 0);
        $slug  = trim((string)($post['url_slug'] ?? ''));
        if ($id <= 0) return url('post/show', [], $absolute);
        $path = null;
        switch ($structure) {
            case 'id':        $path = "/post/{$id}"; break;
            case 'cat_id':    $path = $catId > 0 ? "/c/{$catId}/{$id}" : "/post/{$id}"; break;
            case 'title':     $path = $slug !== '' ? "/post/{$id}-{$slug}" : "/post/{$id}"; break;
            case 'cat_title': $path = ($catId > 0 && $slug !== '') ? "/c/{$catId}/{$id}-{$slug}"
                                  : ($catId > 0 ? "/c/{$catId}/{$id}" : ($slug !== '' ? "/post/{$id}-{$slug}" : "/post/{$id}")); break;
        }
        if ($path === null) {
            return url('post/show', ['id' => $id], $absolute);
        }
        if ($absolute) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            return $scheme . '://' . $host . $path;
        }
        return $path;
    }
}

/**
 * 板块 URL：/c/{cat_id}
 */
if (!function_exists('board_url')) {
    function board_url($catId, $absolute = false) {
        $catId = (int)$catId;
        $structure = permalink_structure();
        if ($structure === 'default' || $catId <= 0) {
            return url('home/index', $catId > 0 ? ['cat' => $catId] : [], $absolute);
        }
        $path = "/c/{$catId}";
        if ($absolute) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            return $scheme . '://' . $host . $path;
        }
        return $path;
    }
}

/**
 * 用户主页 URL：/u/{id}
 */
if (!function_exists('user_url')) {
    function user_url($userId, $absolute = false) {
        $userId = (int)$userId;
        $structure = permalink_structure();
        if ($structure === 'default' || $userId <= 0) {
            return url('user/profile', $userId > 0 ? ['id' => $userId] : [], $absolute);
        }
        $path = "/u/{$userId}";
        if ($absolute) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            return $scheme . '://' . $host . $path;
        }
        return $path;
    }
}

/**
 * 首页 URL：/
 */
if (!function_exists('home_url')) {
    function home_url($absolute = false) {
        $structure = permalink_structure();
        if ($structure === 'default') {
            return url('home/index', [], $absolute);
        }
        $path = "/";
        if ($absolute) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            return $scheme . '://' . $host . $path;
        }
        return $path;
    }
}

/**
 * 生成唯一 url_slug：以 slugify(title) 为基，碰撞时追加 -2、-3...
 * $excludePostId 用于"更新帖子时排除自己"（保证不和自己冲突）
 * 中文等非 ASCII 标题会回退到 'post-{id}'
 */
if (!function_exists('generate_unique_slug')) {
    function generate_unique_slug($title, $excludePostId = 0) {
        $base = slugify($title);
        if ($base === '') $base = 'post';
        $slug = $base;
        $suffix = 0;
        $excludePostId = (int)$excludePostId;
        try {
            while ($suffix < 200) {
                $q = Model::table('posts')->where('url_slug', $slug);
                if ($excludePostId > 0) $q = $q->where('id', '!=', $excludePostId);
                $row = $q->first();
                if (!$row) return $slug;
                $suffix++;
                $slug = $base . '-' . $suffix;
            }
            // 极端情况兜底
            return $base . '-' . ($excludePostId > 0 ? $excludePostId : uniqid());
        } catch (Throwable $e) {
            return $base;
        }
    }
}

/**
 * 反向解析：把 pretty URL 路径（如 /post/123、/c/5、/u/8、/）转回 r= 形式
 * 返回 ['route' => 'post/show', 'params' => ['id' => 123]]，不匹配返回 null
 */
if (!function_exists('_parse_pretty_url')) {
    function _parse_pretty_url($path) {
        $path = '/' . ltrim((string)$path, '/');
        // /
        if ($path === '/' || $path === '') {
            return ['route' => 'home/index', 'params' => []];
        }
        // /post/{id}[-{slug}]
        if (preg_match('#^/post/(\d+)(?:-.*)?/?$#', $path, $m)) {
            return ['route' => 'post/show', 'params' => ['id' => (int)$m[1]]];
        }
        // /c/{cat_id}
        if (preg_match('#^/c/(\d+)/?$#', $path, $m)) {
            return ['route' => 'home/index', 'params' => ['cat' => (int)$m[1]]];
        }
        // /c/{cat_id}/{id}[-{slug}]
        if (preg_match('#^/c/(\d+)/(\d+)(?:-.*)?/?$#', $path, $m)) {
            return ['route' => 'post/show', 'params' => ['id' => (int)$m[2]]];
        }
        // /u/{id}
        if (preg_match('#^/u/(\d+)/?$#', $path, $m)) {
            return ['route' => 'user/profile', 'params' => ['id' => (int)$m[1]]];
        }
        return null;
    }
}

if (!function_exists('base_url')) {
    /**
     * 当前站点绝对根 URL（不带路径）
     */
    function base_url() {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }
}

if (!function_exists('asset')) {
    /**
     * 静态资源 URL
     */
    function asset($path) {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        return $base . '/public/' . ltrim($path, '/');
    }
}

if (!function_exists('upload_url')) {
    function upload_url($path) {
        if (empty($path)) return '';
        if (preg_match('/^https?:\/\//', $path)) return $path;
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        return $base . '/public/' . ltrim($path, '/');
    }
}

if (!function_exists('qr_url')) {
    /**
     * 收款码 URL 归一化（与后台上传预览 rcQrUrl 完全一致）：
     * - 相对路径 uploads/xxx → /public/uploads/xxx
     * - 绝对 URL 缺 /public 则补一段 /public（upload_url 本身不会补，这里补上）
     */
    function qr_url($path) {
        $v = trim((string)$path);
        if ($v === '') return '';
        if (preg_match('#^https?://#', $v)) {
            if (strpos($v, '/public/') === false) {
                $v = preg_replace('#^(https?://[^/]+)(/.*)$#', '$1/public$2', $v);
            }
            return $v;
        }
        return upload_url($v);
    }
}

if (!function_exists('absolute_url')) {
    /**
     * 拼成完整绝对 URL（含协议+host）。用于前端 <img src> 等场景，
     * 避免 pretty URL / 子目录部署下相对路径被解析成 404。
     */
    function absolute_url($path) {
        $path = trim((string)$path);
        if ($path === '') return '';
        if (preg_match('#^https?://#', $path)) return $path;
        $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
        return $host . (strpos($path, '/') === 0 ? $path : '/' . $path);
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        // 优先从 session 读取，其次从 cookie 读取（兼容 session 无法写入的环境）
        if (!empty($_SESSION['_csrf_token'])) {
            return $_SESSION['_csrf_token'];
        }
        if (!empty($_COOKIE['_csrf_token'])) {
            $_SESSION['_csrf_token'] = $_COOKIE['_csrf_token'];
            return $_COOKIE['_csrf_token'];
        }
        $token = bin2hex(random_bytes(32));
        $_SESSION['_csrf_token'] = $token;
        // 同时写入 cookie，确保 session 失效时仍有 token
        setcookie('_csrf_token', $token, time() + 86400, '/', '', false, true);
        return $token;
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('csrf_check')) {
    function csrf_check() {
        $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        // 兼容 JSON 请求体中的 token
        if (empty($token)) {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);
            if (is_array($json) && isset($json['_token'])) {
                $token = $json['_token'];
            }
        }
        $stored = $_SESSION['_csrf_token'] ?? ($_COOKIE['_csrf_token'] ?? '');
        if (!hash_equals($stored, $token)) {
            Response::json(['code' => 419, 'message' => 'CSRF 令牌无效，请刷新页面重试'], 419);
        }
    }
}

if (!function_exists('current_user')) {
    function current_user() {
        return Auth::user();
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return Auth::check();
    }
}

if (!function_exists('can')) {
    function can($permission) {
        return Auth::can($permission);
    }
}

if (!function_exists('role')) {
    function role() {
        return Auth::role();
    }
}

if (!function_exists('is_admin')) {
    /**
     * 是否为"管理级用户"（admin / super_admin / webmaster）。
     * 站长（webmaster）权限高于一切其他用户，本函数一并纳入，使：
     *   - 顶部/页脚"后台"入口对站长可见
     *   - 板块/帖子的"管理员可绕过"限制对站长也跳过
     *   - "已是认证用户才能进入认证板块"等限制对站长跳过
     * 注意：本函数不区分三档等级；如需仅 super_admin+webmaster 走严格路径，用 is_high_admin()。
     */
    function is_admin() {
        // 内置管理角色（admin / super_admin / webmaster）直接放行
        if (in_array(Auth::role(), ['admin', 'super_admin', 'webmaster'], true)) {
            return true;
        }
        // 自定义角色：只要在「角色权限」矩阵中授予了「用户管理(user.manage)」即视为管理级，
        // 可显示前台「后台」入口并进入后台（各后台页仍按 can() 做细粒度校验，
        // 站长专属页仍仅 is_webmaster() 通过）。这样后台新建的自定义管理角色才能真正生效。
        return Auth::can('user.manage');
    }
}

if (!function_exists('is_super_admin')) {
    function is_super_admin() {
        return Auth::role() === 'super_admin';
    }
}

if (!function_exists('is_webmaster')) {
    /**
     * 是否站长（webmaster）：权限高于一切其他用户。
     * - 站长 > 超级管理员 > 管理员 > 版主 > 普通/认证用户。
     * - 站长可编辑任何用户（含其他 super_admin）；但站长自己的 role/status 不可改。
     */
    function is_webmaster() {
        return Auth::role() === 'webmaster';
    }
}

/**
 * 分配新用户 uid 并插入用户表（统一入口，AuthController 注册流程与 AdminController 添加用户共用）。
 *
 * 规则（业务诉求）：
 *   1) 新 uid = 当前所有用户的最大 uid + 1；
 *   2) 不查空——绝不复用被删除用户的旧 uid，只取 MAX；
 *   3) 若手动把某个存量用户的 uid 改大（预留/提升编号价值），新用户自动顺着 +1，
 *      不会出现编号回退或被覆盖。
 *
 * 并发兜底：两个并发插入可能同时读到相同 MAX，+1 后得到相同 uid → 主键冲突(23000)。
 * 此时重试（重查 MAX 取最新值再插）；连续重试仍冲突则回退到 AUTO_INCREMENT，
 * 保证插入流程不被打断。
 *
 * @param array $data 不含 id 的用户字段
 * @return int 新分配的用户 uid
 */
if (!function_exists('allocate_user_id')) {
    function allocate_user_id(array $data)
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $maxRow = Model::query("SELECT COALESCE(MAX(id), 0) AS m FROM users");
            $nextId = (int)($maxRow[0]['m'] ?? 0) + 1;
            $row = $data;
            $row['id'] = $nextId;
            try {
                Model::table('users')->insert($row);
                return $nextId;
            } catch (Throwable $e) {
                $code = method_exists($e, 'getCode') ? (string)$e->getCode() : '';
                // 非主键冲突（23000）→ 直接抛出，交给上层处理
                if (strpos($code, '23000') === false) {
                    throw $e;
                }
                // 主键冲突：仅当还有重试次数时继续，否则跳出走 AUTO_INCREMENT 兜底
                if ($attempt === 2) {
                    break;
                }
            }
        }
        // 兜底：不指定 id，交给 AUTO_INCREMENT（确保插入不中断）
        $fallback = $data;
        unset($fallback['id']);
        return (int)Model::table('users')->insert($fallback);
    }
}

/**
 * 是否 POST 请求。
 * - 用于控制器在写操作前做"必须是 POST"校验，避免被 GET/HEAD 触发。
 * - 同时支持 application/x-www-form-urlencoded / multipart/form-data / application/json
 *   三种 body（只要 REQUEST_METHOD === 'POST' 即视为 POST；body 解析交给 input()）。
 */
if (!function_exists('is_post')) {
    function is_post() {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
        return $method === 'POST';
    }
}

/**
 * 是否 GET 请求。
 */
if (!function_exists('is_get')) {
    function is_get() {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
        return $method === 'GET';
    }
}

/**
 * 是否 AJAX 请求（与 PHP 同源检测 helpers.php 中的 isAjax() 重名小写不冲突，本函数返回 bool）。
 */
if (!function_exists('is_ajax')) {
    function is_ajax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

if (!function_exists('is_high_admin')) {
    /**
     * 是否"高阶管理员"（super_admin 或站长）。
     * - 用于替代模板/控制器中大量 `is_super_admin()` 调用，使站长一并通过权限校验。
     * - 注意：本函数**不**代表"站长专属"判断，仅当原 `is_super_admin()` 用作"管理后台高权限"时使用。
     */
    function is_high_admin() {
        $r = Auth::role();
        return $r === 'super_admin' || $r === 'webmaster';
    }
}

if (!function_exists('is_moderator')) {
    function is_moderator() {
        return in_array(Auth::role(), ['moderator', 'admin', 'super_admin']);
    }
}

if (!function_exists('is_certified')) {
    function is_certified() {
        $u = Auth::user();
        return $u && $u['is_certified'] == 1;
    }
}

if (!function_exists('is_banned')) {
    function is_banned() {
        if (!Auth::check()) return false;
        if (is_super_admin()) return false;
        $u = Auth::user();
        return empty($u['status']) || $u['status'] != 1;
    }
}

if (!function_exists('abortIfBanned')) {
    /**
     * 封禁用户（禁言）拦截：超级管理员/站长豁免。
     * 作为「banned」中间件的纵深防御——即便中间件配置被遗漏，写操作入口也会被拦下。
     * AJAX 请求返回 JSON 错误；普通请求渲染 403 页。
     */
    function abortIfBanned() {
        if (!Auth::check()) return;
        // 超级管理员 / 站长不被禁言（与 banned 中间件保持一致）
        $role = Auth::role();
        if ($role === 'super_admin' || $role === 'webmaster') return;
        $u = Auth::user();
        if (empty($u['status']) || $u['status'] != 1) {
            if (isAjax()) {
                Response::error('账号已被禁言', 403, 403);
            }
            http_response_code(403);
            View::render('errors/403', ['message' => '账号已被禁言，仅可浏览'], 403);
            exit;
        }
    }
}

if (!function_exists('post_feature_tags')) {
    /**
     * 根据帖子数据生成标题前缀特征标记：图片 / 附件 / 拍卖 / 已关闭
     * 仅当对应特征存在时才输出，全部用品牌红渲染。
     * @param array $post 帖子数组（需含 images / attachments / is_closed 字段）
     * @return string 拼接好的 <span class="badge badge-feature">…</span> HTML
     */
    function post_feature_tags($post) {
        if (empty($post) || !is_array($post)) return '';
        $imgs = !empty($post['images']) ? json_decode($post['images'], true) : [];
        if (!is_array($imgs)) $imgs = [];
        $atts = !empty($post['attachments']) ? json_decode($post['attachments'], true) : [];
        if (!is_array($atts)) $atts = [];
        $html = '';
        // 特殊主题标识：优先取 topic_type（拍卖兼容 is_auction 兜底），与详情页 .sb-badge 配色一致
        // 注意：拍卖帖的 topic_type 可能为 'normal'（历史数据/默认），此时用 is_auction 兜底识别
        $type = !empty($post['topic_type']) ? $post['topic_type'] : '';
        if (($type === '' || $type === 'normal') && !empty($post['is_auction'])) $type = 'auction';
        $themeLabels = [
            'auction'   => '拍卖',
            'pay'       => '付费',
            'event'     => '活动',
            'bounty'    => '悬赏',
            'poll'      => '投票',
            'debate'    => '辩论',
            'interview' => '采访',
            'lottery'   => '抽奖',
        ];
        if (isset($themeLabels[$type])) {
            $html .= '<span class="badge badge-topic ' . $type . '">' . $themeLabels[$type] . '</span>';
        }
        // 不再输出「图片」标题标识：图库/付费图片解锁已下线，新帖不写 posts.images；
        // 老帖残留的 posts.images 字段会让「图片」标识永远显示——直接删掉这段。
        // 附件 / 已关闭 标识保留。
        if (!empty($atts)) {
            $html .= '<span class="badge badge-feature-attach">附件</span>';
        }
        if (!empty($post['is_closed'])) {
            $html .= '<span class="badge badge-closed">已关闭</span>';
        }
        return $html;
    }
}

if (!function_exists('can_upload_image')) {
    /**
     * 当前登录用户是否允许上传图片。
     *   关键逻辑：先查 permissions 表里有没有 image.upload 权限码——
     *     - 有（升级已完成）→ 严格走角色权限矩阵：未勾选 → 拒绝；不会 fallback。
     *     - 没有（旧库尚未跑 upgrade.php #32）→ 走旧 settings.allow_image_roles 灰度回退。
     *
     * 这避免了「升级 DELETE 了 settings 行 → fallback 永远 true → 任何人都能发图」的 bug。
     */
    function can_upload_image() {
        if (!Auth::check()) return false;
        if (is_admin()) return true; // 站长/超管/管理员始终允许
        return _can_upload_after_upgrade('image.upload', 'allow_image_roles');
    }
}

if (!function_exists('can_upload_attach')) {
    /**
     * 当前登录用户是否允许上传附件。
     * 同 can_upload_image：先看 permission code 是否在，再决定严格 / 回退。
     */
    function can_upload_attach() {
        if (!Auth::check()) return false;
        if (is_admin()) return true;
        return _can_upload_after_upgrade('attachment.upload', 'allow_attach_roles');
    }
}

if (!function_exists('_can_upload_after_upgrade')) {
    /**
     * 「升级后严格 / 升级前回退」的统一判定逻辑：
     *   1. 查询 permissions.code = $permCode；不存在 → 视为旧库未升级，走 settings 回退
     *   2. 存在 → 直接走 Auth::can()：勾上 → true；未勾 → false（不 fallback）
     */
    function _can_upload_after_upgrade($permCode, $legacySettingKey) {
        // 1) 检查权限码是否已经在系统中（升级标志）
        $hasPermCode = false;
        try {
            $row = Model::table('permissions')->where('code', $permCode)->first();
            $hasPermCode = !empty($row);
        } catch (Exception $e) {
            $hasPermCode = false;
        }

        // 2) 升级后：严格走角色权限矩阵
        if ($hasPermCode) {
            try { return Auth::can($permCode); } catch (Exception $e) { return false; }
        }

        // 3) 升级前：回退到旧 settings（兼容历史默认 = 全部登录用户允许）
        try {
            $row = Model::table('settings')->where('key_name', $legacySettingKey)->first();
        } catch (Exception $e) {
            $row = null;
        }
        if (!$row || $row['value'] === '' || $row['value'] === null) return true;
        $roles = array_map('trim', array_filter(explode(',', $row['value'])));
        if (empty($roles)) return true;
        $userRole = Auth::role();
        if (in_array($userRole, $roles, true)) return true;
        if (in_array('certified', $roles, true) && is_certified()) return true;
        return false;
    }
}

// 保留 _can_role_upload 作为升级前的兼容路径（不要删除）；新逻辑直接走 Auth::can()
if (!function_exists('_can_role_upload')) {
    function _can_role_upload($key) {
        if (!Auth::check()) return false;
        if (is_admin()) return true; // 管理员始终允许
        try {
            $row = Model::table('settings')->where('key_name', $key)->first();
        } catch (Exception $e) {
            $row = null;
        }
        if (!$row || $row['value'] === '' || $row['value'] === null) return true;
        $roles = array_map('trim', array_filter(explode(',', $row['value'])));
        if (empty($roles)) return true;
        $userRole = Auth::role();
        if (in_array($userRole, $roles, true)) return true;
        if (in_array('certified', $roles, true) && is_certified()) return true;
        return false;
    }
}

if (!function_exists('can_browse_category')) {
    function can_browse_category($category) {
        if (empty($category['browse_roles'])) return true; // 未配置：所有人均可浏览
        if (is_admin()) return true; // 管理员/超管始终允许
        $roles = array_map('trim', array_filter(explode(',', $category['browse_roles'])));
        if (empty($roles)) return true;
        $userRole = Auth::check() ? Auth::role() : 'guest';
        if (in_array($userRole, $roles, true)) return true;
        if (in_array('certified', $roles, true) && is_certified()) return true;
        return false;
    }
}

if (!function_exists('can_publish_category')) {
    function can_publish_category($category) {
        if (empty($category['publish_roles'])) return true; // 未配置：所有登录用户均可发表
        if (is_admin()) return true;
        if (!Auth::check()) return false;
        $roles = array_map('trim', array_filter(explode(',', $category['publish_roles'])));
        if (empty($roles)) return true;
        $userRole = Auth::role();
        if (in_array($userRole, $roles, true)) return true;
        if (in_array('certified', $roles, true) && is_certified()) return true;
        return false;
    }
}

if (!function_exists('special_theme_config')) {
    /**
     * 读取「特殊主题」配置（按 theme_key 维度返回 cat_allow / role_allow 映射）。
     * 配置为空时返回空数组 —— 调用方应据此推定"默认允许"（向后兼容全员可发）。
     * 数据存储在 settings.special_themes（JSON）：
     *   { "auction": { "cat_allow": {板块id:1,...}, "role_allow": {角色code:1,...} } }
     */
    function special_theme_config($themeKey) {
        static $cache = null;
        if ($cache === null) {
            $row = null;
            try { $row = Model::table('settings')->where('key_name', 'special_themes')->first(); } catch (\Throwable $e) { $row = null; }
            $cfg = $row ? json_decode($row['value'] ?? '', true) : [];
            if (!is_array($cfg)) $cfg = [];
            // 兼容迁移：历史上若存过 auction_cat_allow / auction_role_allow 独立键，并入
            if (isset($cfg['auction'])) {
                foreach (['auction_cat_allow' => 'cat_allow', 'auction_role_allow' => 'role_allow'] as $oldKey => $newKey) {
                    $old = null;
                    try { $old = Model::table('settings')->where('key_name', $oldKey)->first(); } catch (\Throwable $e) { $old = null; }
                    if ($old && $old['value'] !== '') {
                        $dec = json_decode($old['value'], true);
                        if (is_array($dec)) { foreach ($dec as $k => $v) $cfg['auction'][$newKey][$k] = $v; }
                    }
                }
            }
            $cache = $cfg;
        }
        return isset($cache[$themeKey]) && is_array($cache[$themeKey]) ? $cache[$themeKey] : [];
    }
}

if (!function_exists('can_publish_special')) {
    /**
     * 通用：当前用户是否可在指定板块发布某类「特殊主题」。
     * 双重授权：① 该板块在特殊主题里允许发布该类型；② 当前角色组允许发布该类型。
     * 任一维度未配置（空映射）→ 视为"默认允许"，向后兼容"原来全员可发"。
     * 管理级用户（admin/super_admin/webmaster）始终允许。
     * @param string $themeKey 主题键（auction/pay/event/bounty/poll/debate/interview）
     */
    function can_publish_special($themeKey, $category) {
        // 兜底防御：上游已尽量保证传数组；万一传 null/字符串等，本函数不再炸 fatal，而是退化到"未配置默认允许"
        if (!is_array($category) || empty($category['id'])) return true;
        if (is_admin()) return true;
        if (!Auth::check()) return false;

        $cfg = special_theme_config($themeKey);
        $catAllow  = isset($cfg['cat_allow'])  && is_array($cfg['cat_allow'])  ? $cfg['cat_allow']  : [];
        $roleAllow = isset($cfg['role_allow']) && is_array($cfg['role_allow']) ? $cfg['role_allow'] : [];

        // 维度为空 → 默认允许（未做收敛配置时不过度拦截）
        if (!empty($catAllow) && empty($catAllow[(string)$category['id']])) return false;
        if (!empty($roleAllow) && empty($roleAllow[Auth::role()])) return false;
        return true;
    }
}

if (!function_exists('can_publish_auction')) {
    /**
     * 当前用户是否可在指定板块发布「拍卖帖」（委托通用实现 can_publish_special）。
     */
    function can_publish_auction($category) {
        return can_publish_special('auction', $category);
    }
}

/**
 * 从帖子 content 中实时抽取纯文本摘要（用于卡片式预览）。
 * - 解码 HTML 实体（&nbsp; / &amp; 等）
 * - 剥 HTML 标签
 * - 把连续空白压成一个空格
 * - 截到 $max 字（中文按字计），超出加「…」
 * 注：仅用于列表摘要展示；帖子详情正文走原 content（保留 emoji / 富文本）。
 */
if (!function_exists('make_post_excerpt')) {
    function make_post_excerpt($content, $max = 80) {
        if ($content === null || $content === '') return '';
        $text = html_entity_decode((string)$content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);
        if ($text === '') return '';
        if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $max) {
            $text = mb_substr($text, 0, $max, 'UTF-8') . '…';
        } elseif (strlen($text) > $max) {
            $text = substr($text, 0, $max) . '…';
        }
        return $text;
    }
}

if (!function_exists('time_ago')) {
    function time_ago($datetime) {
        $time = is_numeric($datetime) ? $datetime : strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 60) return '刚刚';
        if ($diff < 3600) return floor($diff / 60) . '分钟前';
        if ($diff < 86400) return floor($diff / 3600) . '小时前';
        if ($diff < 2592000) return floor($diff / 86400) . '天前';
        if ($diff < 31536000) return floor($diff / 2592000) . '个月前';
        return floor($diff / 31536000) . '年前';
    }
}

if (!function_exists('truncate')) {
    function truncate($string, $length = 100, $suffix = '...') {
        $string = strip_tags($string ?? '');
        if (mb_strlen($string) <= $length) return $string;
        return mb_substr($string, 0, $length) . $suffix;
    }
}

if (!function_exists('mask_id_card')) {
    function mask_id_card($id) {
        if (empty($id) || strlen($id) < 10) return $id;
        return substr($id, 0, 6) . '********' . substr($id, -4);
    }
}

if (!function_exists('mask_phone')) {
    function mask_phone($phone) {
        if (empty($phone) || strlen($phone) < 7) return $phone;
        return substr($phone, 0, 3) . '****' . substr($phone, -4);
    }
}

if (!function_exists('mask_name')) {
    function mask_name($name) {
        if (empty($name)) return $name;
        $len = mb_strlen($name);
        if ($len == 1) return $name;
        if ($len == 2) return mb_substr($name, 0, 1) . '*';
        return mb_substr($name, 0, 1) . str_repeat('*', $len - 2) . mb_substr($name, -1);
    }
}

if (!function_exists('json_input')) {
    function json_input() {
        // 静态缓存：$_POST / JSON 请求体（php://input）在同一请求内只能读取一次，
        // 若多个 input() 调用各自读取会导致后续调用拿到空值。缓存后多次调用安全。
        static $cached = null;
        if ($cached !== null) return $cached;
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        $cached = is_array($data) ? $data : [];
        return $cached;
    }
}

if (!function_exists('has_input')) {
    /**
     * 判断请求中是否真的携带了某个字段（含 GET / POST / JSON 三种来源）。
     *
     * 为什么不直接用 input($key) !== null：
     *   input() 内部会执行 `$value = $data[$key] ?? $default; if ($value === null) $value = '';`
     *   即"未携带 = 返回 ''"，而非 null。所以 `!== null` 永远为 true，
     *   用它来当存在性检查会导致「前端没发送字段，后端却以为发送了」误判。
     *   has_input() 直接查 $_GET / $_POST / json_input() 三个原始数组的 key 存在性，
     *   适用于"前端通过 delete data['xxx'] / 不带字段 / 带 null 哪种方式都不应该被记录"的场景。
     *
     * @param string $key 字段名
     * @return bool true 表示该字段在请求中真实出现（值可能为 '' / '0' / 0 / null）
     */
    function has_input($key) {
        if (array_key_exists($key, $_GET)) return true;
        if (array_key_exists($key, $_POST)) return true;
        $json = json_input();
        if (is_array($json) && array_key_exists($key, $json)) return true;
        return false;
    }
}

if (!function_exists('input')) {
    function input($key = null, $default = null) {
        $data = array_merge($_GET, $_POST, json_input());
        if ($key === null) return $data;
        $value = $data[$key] ?? $default;
        // PHP 8.1+ 兼容：避免 null 传给 trim()/strlen() 等触发 Deprecated 警告
        if ($value === null) $value = '';
        return $value;
    }
}

if (!function_exists('filter_sensitive')) {
    /**
     * 敏感词过滤
     * @param string $text
     * @param string $scope 调用方作用域：content（帖子/评论/消息等）| all（用户名/昵称等身份类）
     * 兼容：旧数据若 scope='username' 视作 username_nickname（仅限身份）
     */
    function filter_sensitive($text, $scope = 'content') {
        $words = Model::table('sensitive_words')->where('status', 1)->get();
        $validWScopes = ($scope === 'all')
            ? ['all', 'username_nickname', 'username']
            : ['all', $scope];
        foreach ($words as $w) {
            $wscope = $w['scope'] ?? 'all';
            if (!in_array($wscope, $validWScopes, true)) continue;
            $text = str_ireplace($w['word'], '***', $text);
        }
        return $text;
    }
}

if (!function_exists('has_high_risk_sensitive')) {
    /**
     * 检测文本是否包含高危敏感词（level=2），用于发布前硬拦截。
     * 普通词（level=1）由 filter_sensitive() 打码后照常发布；高危词则交由本函数拦截拒绝。
     * @param string $text
     * @param string $scope content（帖子/评论/私信等）| all（用户名/昵称等身份类）
     * @return bool
     */
    function has_high_risk_sensitive($text, $scope = 'content') {
        $text = (string)$text;
        if ($text === '') return false;
        $words = Model::table('sensitive_words')->where('status', 1)->get();
        $validWScopes = ($scope === 'all')
            ? ['all', 'username_nickname', 'username']
            : ['all', $scope];
        foreach ($words as $w) {
            if ((int)($w['level'] ?? 1) !== 2) continue; // 只关心高危词
            $wscope = $w['scope'] ?? 'all';
            if (!in_array($wscope, $validWScopes, true)) continue;
            if (stripos($text, (string)$w['word']) !== false) return true;
        }
        return false;
    }
}

if (!function_exists('has_sensitive')) {
    /**
     * 检测文本是否包含敏感词（用于用户名/昵称/简介等唯一性与合规性校验）
     * @param string $text
     * @param string $scope all|content|username|nickname ...
     * @return bool 含敏感词返回 true
     */
    function has_sensitive($text, $scope = 'all', $mode = 'contains') {
        $text = (string)$text;
        if ($text === '') return false;
        $lowerText = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
        $words = Model::table('sensitive_words')->where('status', 1)->get();
        // 调用方传 'all'（身份类校验）时，词条要匹配 all 或 username_nickname（旧值 username 视同 username_nickname）；
        // 调用方传 'content' 时，匹配 all 或 content。
        $validWScopes = ($scope === 'all')
            ? ['all', 'username_nickname', 'username']
            : ['all', $scope];
        foreach ($words as $w) {
            $wscope = $w['scope'] ?? 'all';
            if (!in_array($wscope, $validWScopes, true)) continue;
            $word = (string)$w['word'];
            if ($mode === 'exact') {
                // 精确命中但不区分大小写：用户名/昵称必须整串等于敏感词（CI）才禁止
                $lowerWord = function_exists('mb_strtolower') ? mb_strtolower($word) : strtolower($word);
                if ($lowerText === $lowerWord) return true;
            } else {
                // 默认包含匹配（不区分大小写），用于帖子内容等场景
                if (stripos($text, $word) !== false) return true;
            }
        }
        return false;
    }
}

if (!function_exists('_mention_lower')) {
    /**
     * 把 token 转小写（CI 比较用）。兼容无 mbstring 环境。
     */
    function _mention_lower($s) {
        return function_exists('mb_strtolower') ? mb_strtolower((string)$s) : strtolower((string)$s);
    }
}

if (!function_exists('mention_replace_escaped')) {
    /**
     * 把已 htmlspecialchars 转义后的文本中的 @token 替换为可点击的提及链接。
     * token 规则：@ 后接一段不含空白、@ 及部分 Markdown/HTML 特殊字符的连续字符。
     * 命中 users 表 username 或 nickname 时渲染为品牌红链接，未命中原样返回。
     *
     * 大小写不敏感：匹配按 LOWER(username)/LOWER(nickname) 做 CI 比较；输入 @AMO/@amo/@Amo
     * 都能命中数据库里存 amo 的同一用户。
     *
     * 高亮文本统一为目标用户资料的展示名：nickname 优先，空则回退 username。
     * 即"同一账号无论用 @username 还是 @nickname 输入，展示都一致"，避免一个账号两次 @ 显示不同。
     * 注意展示文本保留数据库里存的原大小写（输入 `@amo` 不会强制显示成小写）。
     *
     * @param string $escaped 已转义文本
     * @return string HTML
     */
    function mention_replace_escaped($escaped) {
        if (!is_string($escaped) || $escaped === '') return $escaped;
        if (strpos($escaped, '@') === false) return $escaped;
        if (!preg_match_all('/@([^\s@<>"\'\[\]\(\)#，。！？、；：,.!?;:]+)/u', $escaped, $m)) return $escaped;
        $tokens = array_values(array_unique($m[1]));
        $map = [];     // key=小写 token, value=用户数组（@username/@nickname 命中）
        $idMap = [];   // key=uid(int), value=用户数组（@uid 命中）
        if (!empty($tokens)) {
            $lowTokens = array_map('_mention_lower', $tokens);
            $ph = implode(',', array_fill(0, count($tokens), '?'));
            $sql = "SELECT id, username, nickname FROM users WHERE LOWER(username) IN ($ph) OR LOWER(nickname) IN ($ph)";
            $params = array_merge($lowTokens, $lowTokens);
            // 同步支持 @uid：纯数字 token 直接用 id 命中
            $numericIds = [];
            foreach ($tokens as $t) {
                if (is_numeric($t) && (int)$t > 0) $numericIds[] = (int)$t;
            }
            if (!empty($numericIds)) {
                $idPh = implode(',', array_fill(0, count($numericIds), '?'));
                $sql .= " OR id IN ($idPh)";
                $params = array_merge($params, $numericIds);
            }
            try {
                $rows = \Model::query($sql, $params);
                foreach ($rows as $u) {
                    if (!empty($u['username'])) {
                        $lk = _mention_lower($u['username']);
                        if (!isset($map[$lk])) $map[$lk] = $u;
                    }
                    if (!empty($u['nickname'])) {
                        $lk = _mention_lower($u['nickname']);
                        if (!isset($map[$lk])) $map[$lk] = $u;
                    }
                    // id 索引（无论该用户是被 username/nickname 还是 id 命中，都登记，供 @uid 使用）
                    $idMap[(int)$u['id']] = $u;
                }
            } catch (\Throwable $e) {}
        }
        // 渲染规则统一为"@实际用户名"：字母大小写跟随目标用户 username 的原样。
        // 不论 @ 后输入的是 username/nickname 还是 uid，命中目标用户后都展示 username（DB 原样）。
        // 这样：①即使后续用户改了昵称，老内容里的 @ 仍能稳定显示；②输入 @amo 显示 @Amo（数据库原样），
        // 同一账号多次 @ 不会出现"输入大小写不同显示文字不同"的不一致。
        return preg_replace_callback('/@([^\s@<>"\'\[\]\(\)#]+)/u', function($mm) use ($map, $idMap) {
            $tok = $mm[1];
            $ltok = _mention_lower($tok);
            $u = null;
            if (isset($map[$ltok])) {
                $u = $map[$ltok];
            } elseif (is_numeric($tok) && (int)$tok > 0 && isset($idMap[(int)$tok])) {
                $u = $idMap[(int)$tok];
            }
            if ($u) {
                $display = $u['username']; // 始终 username，大小写跟随 DB 原样
                $href = url('user/profile', ['id' => (int)$u['id']]);
                return '<a href="' . e($href) . '" class="mention" data-uid="' . (int)$u['id'] . '">@' . e($display) . '</a>';
            }
            return $mm[0];
        }, $escaped);
    }
}

if (!function_exists('render_mentions')) {
    /**
     * 渲染含 @提及的文本（用于评论、个人简介等非 Markdown 场景）：先转义 → 替换提及 → nl2br。
     */
    function render_mentions($text) {
        if ($text === null || $text === '') return '';
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        // ✦ 清洗部分截图/剪贴板助手生成的「@image#N:filename.png」字面文本——
        //   这类内容是用户从外部截图工具粘贴进来时遗留的占位符，并非系统支持的图片嵌入语法，
        //   原样显示会污染评论区。直接剥掉整段匹配（含可能的尾部空白）。
        $escaped = preg_replace('/@image#\d+:[^\s<]+/u', '', $escaped);
        $escaped = mention_replace_escaped($escaped);
        return nl2br($escaped);
    }
}

if (!function_exists('extract_mention_user_ids')) {
    /**
     * 从文本解析被 @ 的用户 id 列表（用于发通知）。
     * 大小写不敏感：输入 @AMO/@amo/@Amo 都会命中数据库里存 amo 的同一用户。
     * @param string $text
     * @param int|null $excludeId 排除发布者本人
     * @return array 去重后的 user_id 列表
     */
    function extract_mention_user_ids($text, $excludeId = null) {
        if (empty($text)) return [];
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        if (!preg_match_all('/@([^\s@<>"\'\[\]\(\)#，。！？、；：,.!?;:]+)/u', $escaped, $m)) return [];
        $tokens = array_values(array_unique($m[1]));
        $ids = [];
        if (!empty($tokens)) {
            $lowTokens = array_map('_mention_lower', $tokens);
            $ph = implode(',', array_fill(0, count($tokens), '?'));
            $sql = "SELECT id, username, nickname FROM users WHERE LOWER(username) IN ($ph) OR LOWER(nickname) IN ($ph)";
            $params = array_merge($lowTokens, $lowTokens);
            // 同步支持 @uid：纯数字 token 直接按 id 命中
            $numericIds = [];
            foreach ($tokens as $t) {
                if (is_numeric($t) && (int)$t > 0) $numericIds[] = (int)$t;
            }
            if (!empty($numericIds)) {
                $idPh = implode(',', array_fill(0, count($numericIds), '?'));
                $sql .= " OR id IN ($idPh)";
                $params = array_merge($params, $numericIds);
            }
            try {
                $rows = \Model::query($sql, $params);
                foreach ($rows as $u) {
                    $uid = (int)$u['id'];
                    if ($excludeId !== null && $uid === (int)$excludeId) continue;
                    if (!in_array($uid, $ids, true)) $ids[] = $uid;
                }
            } catch (\Throwable $e) {}
        }
        return $ids;
    }
}

if (!function_exists('notify_user')) {
    /**
     * 写入一条用户行为通知（通用），供各 Controller 复用。
     */
    function notify_user($userId, $type, $content, $link = '') {
        if (empty($userId)) return;
        try {
            \Model::table('notifications')->insert([
                'user_id' => $userId,
                'type' => $type,
                'content' => $content,
                'link' => $link,
                'is_read' => 0,
                'is_system_notification' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {}
    }
}

if (!function_exists('is_pure_number')) {
    /**
     * 是否纯数字（ASCII 数字 0-9；空串视为否）
     * 用于用户名/昵称禁纯数字校验（仅 ASCII 数字；中文数字"一二三"不在内）。
     */
    function is_pure_number($value) {
        $value = trim((string)$value);
        if ($value === '') return false;
        return ctype_digit($value);
    }
}

if (!function_exists('is_valid_name_chars')) {
    /**
     * 用户名/昵称字符集校验：只允许 数字(0-9) / 字母(a-zA-Z) / 汉字(\x{4e00}-\x{9fa5})
     * 不允许下划线、空格、标点、emoji 等其他字符。空串视为不通过。
     * @param string $str
     * @return bool true=合法
     */
    function is_valid_name_chars($str) {
        $str = trim((string)$str);
        if ($str === '') return false;
        return (bool)preg_match('/^[a-zA-Z0-9\x{4e00}-\x{9fa5}]+$/u', $str);
    }
}

/**
 * 用户名/昵称按"加权字符数"计长（注册页 / 编辑页统一 3-15 都按这个计算）：
 *   - 1 个汉字 = 2 字符
 *   - 1 个字母 / 数字 = 1 字符
 * 规则动机：用户反馈"3个汉字(6字节)被算3字符→长度限制实际偏松/偏紧错乱"。
 * 用法例：name_strlen('ab12') → 4；name_strlen('汉语') → 4；name_strlen('汉a') → 3。
 * 数学等价：name_strlen($s) = 总字符数 + 汉字数（即 cjk_count + total）。
 */
if (!function_exists('name_strlen')) {
    function name_strlen($s) {
        $s = (string)$s;
        if ($s === '') return 0;
        $total = mb_strlen($s, 'UTF-8');
        $cjk   = preg_match_all('/[\x{4e00}-\x{9fff}]/u', $s);
        return $total + $cjk;
    }
}

if (!function_exists('username_taken')) {
    /**
     * 用户名是否已被占用（交叉查重：username 列 OR nickname 列命中即视为被占用）
     * 两个分支都排除 exceptId（编辑自己时传入），确保"自己的昵称/用户名"不算占用。
     * （注意：不能写成 where()->orWhere()->where('id !=')，因为 AND 优先级高于 OR，
     *  会变成 username=? OR (nickname=? AND id!=self)，导致自身 username 命中时仍报错。）
     * @param string $username
     * @param int|null $exceptId 编辑自己时传入当前用户 id 以排除
     * @return bool
     */
    function username_taken($username, $exceptId = null) {
        $username = trim((string)$username);
        if ($username === '') return false;
        $exceptId = ($exceptId !== null) ? (int)$exceptId : null;
        // 分支1：其他用户的 username 列
        $q1 = Model::table('users')->where('username', $username);
        if ($exceptId !== null) $q1->where('id', '!=', $exceptId);
        if ($q1->first()) return true;
        // 分支2：其他用户的 nickname 列
        $q2 = Model::table('users')->where('nickname', $username);
        if ($exceptId !== null) $q2->where('id', '!=', $exceptId);
        return (bool)$q2->first();
    }
}

if (!function_exists('nickname_taken')) {
    /**
     * 昵称是否已被占用（交叉查重：username 列 OR nickname 列命中即视为被占用）
     * 两个分支都排除 exceptId（编辑自己时传入），确保"自己的昵称/用户名"不算占用。
     * 由此实现豁免规则：用户自己把昵称改成与自身用户名相同，视为可重复（不报错）。
     * @param string $nickname
     * @param int|null $exceptId 编辑自己时传入当前用户 id 以排除
     * @return bool
     */
    function nickname_taken($nickname, $exceptId = null) {
        $nickname = trim((string)$nickname);
        if ($nickname === '') return false;
        $exceptId = ($exceptId !== null) ? (int)$exceptId : null;
        // 分支1：其他用户的 username 列
        $q1 = Model::table('users')->where('username', $nickname);
        if ($exceptId !== null) $q1->where('id', '!=', $exceptId);
        if ($q1->first()) return true;
        // 分支2：其他用户的 nickname 列
        $q2 = Model::table('users')->where('nickname', $nickname);
        if ($exceptId !== null) $q2->where('id', '!=', $exceptId);
        return (bool)$q2->first();
    }
}

if (!function_exists('avatar_html')) {
    /**
     * 渲染用户头像（不含认证图标，认证图标统一显示在用户名后）
     * @param array $user 用户数组（含 avatar/nickname/username/id）
     * @param int $size 头像尺寸 px
     * @param bool $link 是否包裹链接
     */
    function avatar_html($user, $size = 34, $link = true)
    {
        if (!$user) return '';
        $name = $user['nickname'] ?? ($user['username'] ?? '');
        $initial = e(mb_substr($name ?: '?', 0, 1));
        $avatarUrl = $user['avatar'] ?? '';
        $profileUrl = url('user/profile', ['id' => $user['id'] ?? 0]);

        if ($avatarUrl) {
            // 有头像时按原图尺寸显示，最大不超过 $size，避免被样式压小或撑爆布局
            $img = '<img src="' . upload_url($avatarUrl) . '" alt="" class="avatar-img" style="max-width:' . $size . 'px;max-height:' . $size . 'px;border-radius:50%;display:block;">';
            return $link ? '<a href="' . $profileUrl . '" class="avatar-link">' . $img . '</a>' : $img;
        }

        // 无头像时显示固定圆形首字母
        $html = '<span class="avatar-wrap" style="width:' . $size . 'px;height:' . $size . 'px;">';
        if ($link) {
            $html .= '<a href="' . $profileUrl . '" class="avatar-inner"><span class="avatar-initial">' . $initial . '</span></a>';
        } else {
            $html .= '<span class="avatar-inner"><span class="avatar-initial">' . $initial . '</span></span>';
        }
        $html .= '</span>';
        return $html;
    }
}

if (!function_exists('post_author_icon')) {
    /**
     * 帖子列表用的通用楼主人物图标（SVG）
     * @param int $size 图标尺寸 px
     * @param string $color 颜色
     */
    function post_author_icon($size = 18, $color = '#aaa')
    {
        $svg = '<svg viewBox="0 0 24 24" fill="' . e($color) . '" style="width:' . (int)$size . 'px;height:' . (int)$size . 'px;display:block;"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>';
        return '<span class="post-author-icon" style="display:inline-flex;align-items:center;justify-content:center;width:' . (int)$size . 'px;height:' . (int)$size . 'px;flex-shrink:0;">' . $svg . '</span>';
    }
}

if (!function_exists('setting')) {
    /**
     * 读取 settings 表中的系统设置。
     * - 使用进程内静态缓存（同一请求只查一次 DB）
     * - 表不存在/字段不存在时返回 $default
     * - 默认值由各业务层决定；空字符串/0/[] 也视为"有值"，不会回退 default
     *
     * @param string $key        settings.key_name
     * @param mixed  $default    设置缺失或读取失败时返回
     * @param bool   $forceReload 强制重新查询（同一进程内重新加载）
     * @return mixed
     */
    function setting($key, $default = null, $forceReload = false)
    {
        static $cache = null;
        if ($cache === null || $forceReload) {
            $cache = [];
            try {
                $rows = Model::query("SELECT key_name, value FROM settings");
                if (is_array($rows)) {
                    foreach ($rows as $r) {
                        if (isset($r['key_name'])) $cache[$r['key_name']] = $r['value'] ?? '';
                    }
                }
            } catch (Exception $e) {
                // 表不存在/字段不存在 → 走默认值
                $cache = [];
            }
        }
        return array_key_exists($key, $cache) ? $cache[$key] : $default;
    }
}

if (!function_exists('cert_badge_html')) {
    /**
     * 返回认证图标（一个或多个，显示在用户名后面 / 认证卡片上）
     * - 未传 $group_id 时，按用户"已通过认证"的所有组返回徽章（最多 $max 个，默认 3）
     * - 传了 $group_id 时，只返回该组徽章（兼容旧用法）
     * - 用户可在个人资料勾选要在前端显示的认证项（users.show_cert_badges，JSON group_ids）
     * - 某组 icon_svg 为空时不回退到全局 SVG（按用户要求"清除图标后前台不显示"）
     *
     * @param array $user 用户数组（含 id / is_certified / show_cert_badges）
     * @param int $size 图标尺寸
     * @param int|null $group_id 指定认证项目组时取该组图标；为 null 时按已认证组多徽章
     * @param int $max 最多返回徽章数量（默认 3）
     */
    function cert_badge_html($user, $size = 14, $group_id = null, $max = 3)
    {
        $font = max(9, (int)($size * 0.55));
        $wrapStyle = 'display:inline-flex;align-items:center;justify-content:center;width:' . $size . 'px;height:' . $size . 'px;vertical-align:-2px;margin-left:3px;flex-shrink:0;';
        $fallback = '<span class="cert-badge-inline" title="已认证" style="' . $wrapStyle . 'border-radius:50%;background:#52c41a;color:#fff;font-size:' . $font . 'px;font-weight:600;">✓</span>';

        // 缓存：全局 SVG + 各组 SVG
        static $globalSvg = null, $groupSvgs = [];
        if ($globalSvg === null) {
            try {
                $r = Model::table('settings')->where('key_name', 'cert_badge_svg')->first();
                $globalSvg = $r ? $r['value'] : '';
            } catch (Exception $e) { $globalSvg = ''; }
        }
        if (empty($groupSvgs)) {
            try {
                $rows = Model::table('certification_groups')->where('status', 1)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->get();
                foreach ($rows as $gg) { $groupSvgs[(int)$gg['id']] = $gg['icon_svg']; }
            } catch (Exception $e) {}
        }

        // 解析应显示的 group_id 列表
        $selected = [];
        if ($group_id !== null) {
            $selected = [(int)$group_id];
        } else {
            if (!$user || empty($user['id'])) return '';

            // 用户已通过的组
            $passed = [];
            try {
                $rows = Model::table('certifications')->where('user_id', (int)$user['id'])->where('status', 1)->get();
                foreach ($rows as $r) { $passed[(int)$r['group_id']] = true; }
            } catch (Exception $e) {}
            // 兼容：users.is_certified=1（默认组算通过）
            if (!empty($user['is_certified']) && !isset($passed[1])) $passed[1] = true;

            // 用户勾选要在前端显示的认证项
            // 三态：
            //   null = DB 字段从未写入（或写入了 NULL）→ 第一次访问的老用户走"已认证组全展示"兜底
            //   []   = 用户明确保存清空 → 严格不展示任何认证徽章
            //   [gid,...] = 用户已选 → 严格按 ∩已通过 展示
            // 注意：JSON 解析失败或字段不是 string 时按"严格空"处理，**绝不**因为无法解析而
            // 退化到全展示——避免任何代码 bug 让用户"明明取消勾选却仍看见徽章"。
            $userPick = null;
            if (array_key_exists('show_cert_badges', $user)) {
                $raw = $user['show_cert_badges'];
                if ($raw === null || $raw === '' || $raw === 'null') {
                    $userPick = null; // 视为未设置（首次访问兜底）
                } elseif (is_string($raw)) {
                    $tmp = json_decode($raw, true);
                    if (is_array($tmp)) {
                        $userPick = [];
                        foreach ($tmp as $gid) $userPick[(int)$gid] = true;
                    } else {
                        // 字段非法（脏数据 / 半截写入）→ 严格空，绝不全展示
                        $userPick = [];
                    }
                } else {
                    // 数组/对象等意外类型 → 严格空
                    $userPick = [];
                }
            }

            // 未设置（null）→ 兜底按已通过的认证组全部展示（保证已认证老用户无需操作即可看见徽章）
            // 已设置（含 [] 明确清空）→ 严格按 $userPick ∩ $passed 展示，尊重用户意图
            if ($userPick === null) {
                $selected = array_keys($passed);
            } else {
                $selected = [];
                foreach (array_keys($userPick) as $gid) {
                    if (isset($passed[$gid])) $selected[] = (int)$gid;
                }
            }
            // 稳定排序：按 groupId 升序
            sort($selected, SORT_NUMERIC);
        }

        // 截断到 $max
        $selected = array_slice($selected, 0, max(1, (int)$max));

        $html = '';
        foreach ($selected as $gid) {
            $svg = '';
            if (!empty($groupSvgs[$gid])) {
                $svg = $groupSvgs[$gid];
            } elseif (!empty($globalSvg)) {
                // 所有组（不再仅限 group 1）都允许回退到全局 SVG，避免「未选时已认证组无图可显」
                $svg = $globalSvg;
            }
            if ($svg) {
                // 兼容性双保险：内联 SVG 若缺 viewBox/width/height，iOS Safari 在
                // flex 容器内会渲染为 0 尺寸（桌面 Chrome 正常），真机看不见。
                // 这里确保带 viewBox 与当前 size 的明确像素尺寸，任意浏览器都可见。
                if (stripos($svg, '<svg') !== false) {
                    if (!preg_match('/\bviewBox\s*=/i', $svg)) {
                        $svg = preg_replace('/<svg/i', '<svg viewBox="0 0 24 24"', $svg, 1);
                    }
                    if (!preg_match('/\bwidth\s*=/i', $svg)) {
                        $svg = preg_replace('/<svg/i', '<svg width="' . (int)$size . '"', $svg, 1);
                    }
                    if (!preg_match('/\bheight\s*=/i', $svg)) {
                        $svg = preg_replace('/<svg/i', '<svg height="' . (int)$size . '"', $svg, 1);
                    }
                }
                $html .= '<span class="cert-badge-inline" style="' . $wrapStyle . '">' . $svg . '</span>';
            } else {
                // 终极兜底：绿✓ 圆，确保"未勾选按已通过自动展示"一定有视觉标识
                $html .= $fallback;
            }
        }
        return $html;
    }
}

if (!function_exists('uid_badge')) {
    /**
     * 渲染 UID 标识徽章（空心圆角框）：文案示例 "UID 1"。
     * 支持传入用户数组（含 id / user_id）或纯数字 id。
     * 样式统一走 CSS class .uid-badge（品牌红 #ea6f5a 空心圆角框）。
     * @param array|int $user 用户数组或用户 id
     * @return string HTML
     */
    function uid_badge($user) {
        if (is_array($user)) {
            $id = (int)($user['id'] ?? $user['user_id'] ?? 0);
        } else {
            $id = (int)$user;
        }
        if ($id <= 0) return '';
        return '<span class="uid-badge">UID ' . $id . '</span>';
    }
}

if (!function_exists('level_badge')) {
    /**
     * 渲染用户等级徽章：文案示例 "Lv1 学徒"。
     * 数据来自 users.level + user_levels.name（后台可自定义等级名）。
     * 样式统一走 CSS class .level-badge（淡品牌红底，深红字）。
     *
     * 用法：
     *   level_badge($user)   → 传入用户数组（优先用数组里的 level 字段，避免再查一次 DB）
     *   level_badge(123)     → 只传 id 时自动再读一次 users.level + 计算 name
     *
     * @param array|int $user 用户数组（含 level 可选）或用户 id
     * @return string HTML，缺少 level 时返回空字符串
     */
    function level_badge($user) {
        if (is_array($user)) {
            $id = (int)($user['id'] ?? $user['user_id'] ?? 0);
            $hasLevel = isset($user['level']);
            $level = $hasLevel ? (int)$user['level'] : 0;
            $byte = isset($user['byte']) ? (int)$user['byte'] : null; // null 表示未提供
        } else {
            $id = (int)$user;
            $hasLevel = false;
            $level = 0;
            $byte = null;
        }
        if ($id <= 0) return '';
        // 等级名：用 PointService::getLevelInfo()，让 byte 反算统一为权威来源；
        // 缺 level/byte 时按 id 一次性读齐两者，再调 getLevelInfo。
        $name = '';
        $displayLevel = 0;
        try {
            if (!$hasLevel || $byte === null) {
                $row = \Model::table('users')->select('level', 'byte')->where('id', $id)->first();
                if ($row) {
                    $level = (int)$row['level'];
                    $byte  = (int)($row['byte'] ?? 0);
                } else {
                    return '';
                }
            }
            $li = \PointService::getLevelInfo(['id' => $id, 'level' => $level, 'byte' => $byte]);
            $displayLevel = (int)($li['level'] ?? $level);
            $name = (string)($li['name'] ?? '');
        } catch (Throwable $e) {
            // ignore
        }
        if ($displayLevel <= 0) return '';
        if ($name === '') {
            // 兜底：从 user_levels 表读该 level 的 name（用 $displayLevel，不再用滞后的 $level）
            $row = \Model::table('user_levels')->select('name')->where('level', $displayLevel)->first();
            $name = $row ? (string)$row['name'] : ('Lv.' . $displayLevel);
        }
        return '<span class="level-badge" title="' . e('等级 Lv.' . $displayLevel . ' ' . $name) . '">Lv' . $displayLevel . ' ' . e($name) . '</span>';
    }
}

if (!function_exists('flash')) {
    function flash($message = null, $type = 'success') {
        if ($message === null) {
            $flash = $_SESSION['_flash'] ?? null;
            unset($_SESSION['_flash']);
            return $flash;
        }
        $_SESSION['_flash'] = ['message' => $message, 'type' => $type];
    }
}

if (!function_exists('cat_color_style')) {
    /**
     * 生成板块标签颜色样式
     */
    function cat_color_style($color) {
        $color = trim((string)$color);
        if (!$color) return '';
        // 简单校验十六进制格式
        if (!preg_match('/^#([0-9a-fA-F]{3}){1,2}$/', $color)) return '';
        return 'background:' . $color . '15;color:' . $color . ';border:1px solid ' . $color . '33;';
    }
}

if (!function_exists('encrypt_value')) {
    /**
     * AES 加密敏感数据（如身份证号）
     */
    function encrypt_value($plain) {
        if (empty($plain)) return '';
        $key = hash('sha256', (Config::get('site.title') ?? 'tanhua') . '_tanhua_secret_2024');
        $iv = substr(hash('sha256', 'tanhua_iv_salt'), 0, 16);
        $encrypted = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($encrypted);
    }
}

if (!function_exists('decrypt_value')) {
    /**
     * AES 解密
     */
    function decrypt_value($cipher) {
        if (empty($cipher)) return '';
        $key = hash('sha256', (Config::get('site.title') ?? 'tanhua') . '_tanhua_secret_2024');
        $iv = substr(hash('sha256', 'tanhua_iv_salt'), 0, 16);
        return openssl_decrypt(base64_decode($cipher), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }
}

if (!function_exists('parse_markdown')) {
    /**
     * 轻量 Markdown 解析（先转义 HTML，再转换语法）
     */
    function parse_markdown($text, $canViewHidden = true) {
        if (empty($text)) return '';
        // 转义 HTML
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // @提及：转义后文本中 @token 替换为品牌红可点击链接
        $text = mention_replace_escaped($text);

        // 回复可见标签 [hide]...[/hide]
        if (strpos($text, '[hide]') !== false) {
            if ($canViewHidden) {
                // 保留内部内容，去掉标签本身
                $text = preg_replace('/\[hide\]\s*/', '', $text);
                $text = preg_replace('/\s*\[\/hide\]/', '', $text);
            } else {
                // 未满足条件：整块替换为提示遮罩
                $text = preg_replace('/\[hide\][\s\S]*?\[\/hide\]/', '<div class="reply-hidden-media" style="padding:10px 12px;background:#f8f8f8;border:1px dashed #d9d9d9;border-radius:4px;color:#999;font-size:14px;">🔒 回复后可见</div>', $text);
            }
        }

        // 代码块
        $text = preg_replace('/```(\w*)\n(.*?)```/s', '<pre><code>$2</code></pre>', $text);
        // 行内代码
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        // 标题
        $text = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^# (.+)$/m', '<h2>$1</h2>', $text);
        // 引用
        $text = preg_replace('/^&gt; (.+)$/m', '<blockquote>$1</blockquote>', $text);
        // 图片：正文内联图片（由编辑器「插入图片」写入 ![alt](url)）。
        // URL 经 upload_url() 归一化为绝对地址（站内相对路径 / 外链原样返回），避免 pretty URL 下相对路径 404。
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function ($m) {
            $src = upload_url(trim($m[2]));
            $alt = trim($m[1]);
            return '<img src="' . $src . '" alt="' . $alt . '" class="inline-post-img">';
        }, $text);
        // 站内用户主页 / 主题链接 → 卡片化（用户名卡片 / 主题卡片）
        $text = convert_internal_links_to_cards($text);
        // 表情短代码 :code: → img/字符（在链接处理之后，避免代码块内的 :xxx: 被误替换）
        $text = render_emoji($text);
        // 链接
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="nofollow">$1</a>', $text);
        // 加粗
        $text = preg_replace('/\*\*([^\*]+)\*\*/', '<strong>$1</strong>', $text);
        // 斜体
        $text = preg_replace('/\*([^\*]+)\*/', '<em>$1</em>', $text);
        // 无序列表
        $text = preg_replace('/^[\-\*] (.+)$/m', '<li>$1</li>', $text);
        $text = preg_replace('/(<li>.*<\/li>\n?)+/s', '<ul>$0</ul>', $text);
        // 换行
        $text = preg_replace('/\n{2,}/', '</p><p>', $text);
        $text = '<p>' . $text . '</p>';
        // 清理空段落
        $text = preg_replace('/<p>\s*<\/p>/', '', $text);
        $text = str_replace(['<p><pre>', '</pre></p>'], ['<pre>', '</pre>'], $text);
        $text = str_replace(['<p><h', '</h2></p>', '</h3></p>', '<p><ul>', '</ul></p>', '<p><blockquote>', '</blockquote></p>'],
                            ['<h', '</h2>', '</h3>', '<ul>', '</ul>', '<blockquote>', '</blockquote>'], $text);

        return $text;
    }
}

if (!function_exists('_site_host')) {
    /**
     * 返回当前站点主机名（小写），用于判断「站内链接」。
     * 优先取当前请求域名，最可靠；无 HTTP_HOST 时回退到 url() 生成的基地址。
     */
    function _site_host() {
        static $h = null;
        if ($h === null) {
            $h = '';
            if (!empty($_SERVER['HTTP_HOST'])) {
                $h = strtolower(trim((string)$_SERVER['HTTP_HOST']));
            } elseif (function_exists('url')) {
                $ph = parse_url(url('home/index'), PHP_URL_HOST);
                if ($ph) $h = strtolower($ph);
            }
        }
        return $h;
    }
}

if (!function_exists('resolve_internal_link')) {
    /**
     * 从一段 URL 中识别是否指向本站用户主页 / 主题页，返回 ['type'=>user|topic,'id'=>int] 或 null。
     * 支持的形态：
     *   - 用户：/u/{id}、/u/{id}-slug、user/profile?id={id}、?r=user/profile&id={id}、本站域名的对应路径
     *   - 主题：/post/{id}、/post/{id}-slug、/c/{cat}/{id}、post/show?id={id}、?r=post/show&id={id}
     */
    function resolve_internal_link($url) {
        $url = trim((string)$url);
        if ($url === '' || $url[0] === '#') return null;
        // parse_markdown 已对正文做 htmlspecialchars，& 会变成 &amp;，先还原以保证 parse_str 正确解析 query
        $url = str_replace('&amp;', '&', $url);
        $host = parse_url($url, PHP_URL_HOST);
        if ($host !== null && $host !== false && $host !== '') {
            if (strcasecmp($host, _site_host()) !== 0) return null; // 外部站点链接不转换
        }
        $path  = (string)(parse_url($url, PHP_URL_PATH) ?: '');
        $query = (string)(parse_url($url, PHP_URL_QUERY) ?: '');
        parse_str($query, $q);
        $pathLower = strtolower($path);
        $r = strtolower((string)($q['r'] ?? ''));

        if (stripos($pathLower, 'user/profile') !== false || strpos($r, 'user/profile') !== false) {
            if (!empty($q['id'])) return ['type' => 'user', 'id' => (int)$q['id']];
        }
        if (preg_match('#/u/(\d+)#i', $path, $m)) {
            return ['type' => 'user', 'id' => (int)$m[1]];
        }
        if (stripos($pathLower, 'post/show') !== false || strpos($r, 'post/show') !== false) {
            if (!empty($q['id'])) return ['type' => 'topic', 'id' => (int)$q['id']];
        }
        if (preg_match('#/post/(\d+)#i', $path, $m)) {
            return ['type' => 'topic', 'id' => (int)$m[1]];
        }
        if (preg_match('#/c/\d+/(\d+)#i', $path, $m)) {
            return ['type' => 'topic', 'id' => (int)$m[1]];
        }
        return null;
    }
}

if (!function_exists('user_card_pill')) {
    /**
     * 生成「用户名卡片」HTML（pill 风格，参考邀请页已邀请用户样式，可点击跳转主页）。
     * 进程内静态缓存，避免同一页面多次出现同一用户时重复查库。
     */
    function user_card_pill($id) {
        static $cache = [];
        $id = (int)$id;
        if ($id <= 0) return '';
        if (array_key_exists($id, $cache)) return $cache[$id];
        $cache[$id] = ''; // 占位，防止重复查询
        try {
            $u = \Model::table('users')
                ->select('id', 'username', 'nickname', 'avatar', 'role', 'created_at', 'status', 'is_certified', 'certified_at', 'show_cert_badges')
                ->where('id', $id)->first();
        } catch (\Throwable $e) { $u = null; }
        if (!$u) return '';

        $name   = e($u['nickname'] ?: $u['username']);
        $avatar = avatar_html($u, 32, false);
        $role   = '';
        if (role_show_badge($u['role'])) {
            $role = '<span class="badge-role">' . e(role_display_name($u['role'])) . '</span>';
        }
        $joined = e(date('Y-m-d', strtotime($u['created_at'])));
        $href   = e(url('user/profile', ['id' => $id]));

        $html = '<a href="' . $href . '" class="card-user-pill" data-uid="' . $id . '">'
              . $avatar
              . '<span class="cup-info"><span class="cup-name">' . $name . $role . '</span>'
              . '<span class="cup-sub">' . $joined . ' 加入</span></span></a>';
        $cache[$id] = $html;
        return $html;
    }
}

if (!function_exists('topic_card_pill')) {
    /**
     * 生成「主题卡片」HTML（pill 风格，可点击跳转帖子）。
     * 进程内静态缓存，避免同一页面多次出现同一主题时重复查库。
     */
    function topic_card_pill($id) {
        static $cache = [];
        $id = (int)$id;
        if ($id <= 0) return '';
        if (array_key_exists($id, $cache)) return $cache[$id];
        $cache[$id] = '';
        try {
            // 一次查询带楼主信息：LEFT JOIN users 拿到 nickname / username / avatar，
            // 不用拆两次查询（避免与本帖缓存的 topic 用户组规则多打一次 DB）
            $rows = \Model::query(
                'SELECT p.id, p.title, p.category_id, p.created_at, p.view_count, p.comment_count, p.status, p.user_id,
                        u.username, u.nickname, u.avatar
                 FROM posts p
                 LEFT JOIN users u ON u.id = p.user_id
                 WHERE p.id = ? LIMIT 1',
                [$id]
            );
            $p = $rows[0] ?? null;
        } catch (\Throwable $e) { $p = null; }
        if (!$p || (int)$p['status'] !== 1) return '';

        $title = e(truncate($p['title'], 40));
        $cat   = '';
        if (!empty($p['category_id'])) {
            try {
                $c = \Model::table('categories')->select('name', 'color')->where('id', (int)$p['category_id'])->first();
                if ($c) $cat = '<span class="ctp-cat" style="' . e(cat_color_style($c['color'])) . '">' . e($c['name']) . '</span>';
            } catch (\Throwable $e) {}
        }
        $href = e(url('post/show', ['id' => $id]));

        // 楼主展示名：nickname 优先 → 回退 username → 都空（用户被删等）就空字符串
        $authorName = '';
        if (!empty($p['nickname'])) {
            $authorName = e($p['nickname']);
        } elseif (!empty($p['username'])) {
            $authorName = e($p['username']);
        }
        // meta 灰色小字：在 time 前补楼主昵称（实名后没人会用 username 当展示名，但 admin 删号等场景要兜底）
        $meta = $authorName
              . ($authorName !== '' ? ' · ' : '')
              . e(time_ago($p['created_at']))
              . ' · 浏览 ' . format_count($p['view_count'])
              . ' · 评论 ' . format_count($p['comment_count']);

        // 楼主头像：pill 整体已是 <a data-pid>，不能再嵌 <a>（HTML 嵌套链接无效且浏览器会重排）；
        //   avatar_html 第 3 参传 false 跳过内部 <a> 包裹
        $authorPayload = [
            'id'       => (int)($p['user_id'] ?? 0),
            'username' => (string)($p['username'] ?? ''),
            'nickname' => (string)($p['nickname'] ?? ''),
            'avatar'   => (string)($p['avatar']   ?? ''),
        ];
        $authorAvatar = avatar_html($authorPayload, 24, false);

        $html = '<a href="' . $href . '" class="card-topic-pill" data-pid="' . $id . '">'
              . $authorAvatar
              . '<span class="ctp-info"><span class="ctp-title">' . $cat . '<span class="ctp-title-text">' . $title . '</span></span>'
              . '<span class="ctp-sub">' . $meta . '</span></span></a>';
        $cache[$id] = $html;
        return $html;
    }
}

if (!function_exists('convert_internal_links_to_cards')) {
    /**
     * 把正文中的站内用户主页链接 / 主题链接转换为卡片（用户名卡片 / 主题卡片）。
     * - Markdown 链接 [label](url) 指向站内用户/主题 → 卡片
     * - 裸站内 URL（/u/N、/post/N、/c/... 或本站域名的对应路径）→ 卡片
     * 代码块（<pre>/<code>）内部不做处理，避免误伤。
     */
    function convert_internal_links_to_cards($text) {
        if (empty($text)) return $text;

        // 保护代码块 / 行内代码，避免其中的 URL 被卡片化
        $guards = [];
        $text = preg_replace_callback('/<(pre|code)[^>]*>.*?<\/\1>/is', function ($m) use (&$guards) {
            $k = "\x01CG" . count($guards) . "\x01";
            $guards[$k] = $m[0];
            return $k;
        }, $text);

        $doCard = function ($url) {
            $info = resolve_internal_link($url);
            if (!$info) return null;
            return $info['type'] === 'user'
                ? user_card_pill($info['id'])
                : topic_card_pill($info['id']);
        };

        // 1) Markdown 链接 [label](url)
        $text = preg_replace_callback('/\[([^\]]*)\]\(([^)]+)\)/', function ($m) use ($doCard) {
            $card = $doCard(trim($m[2]));
            return ($card !== null && $card !== '') ? $card : $m[0];
        }, $text);

        // 2) 裸站内 URL（排除已被 (..) 包裹、以及标签属性里的 href="..." / src="..." 等；
        //    注意：不可把空白列入负向回顾，否则「空格 + 正常 URL」这种最常见形态反而匹配不到）
        $text = preg_replace_callback(
            '/(?<![\(\]"\'=])((?:https?:\/\/[^\s<>)]+|\/(?:u|post|c)\/[^\s<>)]+))/i',
            function ($m) use ($doCard) {
                $card = $doCard($m[1]);
                return ($card !== null && $card !== '') ? $card : $m[0];
            },
            $text
        );

        if ($guards) {
            $text = str_replace(array_keys($guards), array_values($guards), $text);
        }
        return $text;
    }
}

if (!function_exists('render_emoji')) {
    /**
     * 把正文中的 :code: 短代码替换为对应 emoji 渲染（图片 / 原字符）。
     * - 输入文本应为已 htmlspecialchars 的（parse_markdown 内部用，故 emoji 字符无需再转义）
     * - unicode 类型 pack：直接输出 char 字符（兼容已有 Kaomoji/Unicode emoji）
     * - image 类型 pack：用 pack.cd 模板 + items.image 拼 URL（支持 {code} 占位符）
     * - 代码块 / 行内代码内部不替换（占位符保护）
     * - 缓存：本请求内同一进程只查一次 DB；启用/停用要等下次请求生效（前台表情选择器刷新页面即最新）
     */
    function render_emoji($text) {
        if ($text === '' || strpos($text, ':') === false) return $text;

        // 单进程内缓存（启停变化要等下次请求生效，可接受）
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            try {
                $rows = Model::query(
                    "SELECT i.code, i.`char`, i.`image`, p.type, p.cd
                       FROM emoji_items i
                       JOIN emoji_packs p ON p.id = i.pack_id
                      WHERE i.enabled = 1 AND p.enabled = 1"
                );
                foreach ($rows as $r) {
                    $key = ':' . $r['code'] . ':';
                    if ($r['type'] === 'image') {
                        $url = str_replace('{code}', (string)$r['image'], (string)$r['cd']);
                        // 绝对 URL（http/https）直接用；相对路径需补主机头，
                        // 否则 pretty URL 页面（如 /post/123）会被解析成 /post/123/uploads/... → 404
                        if (!preg_match('/^https?:\/\//', $url)) {
                            $host = _site_host();
                            if ($host !== '') {
                                $url = (strpos($url, '/') === 0 ? '//' . $host . $url : '//' . $host . '/' . $url);
                            }
                        }
                        $cache[$key] = '<img class="emoji-img" src="'
                            . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
                            . '" alt="' . htmlspecialchars($r['code'], ENT_QUOTES, 'UTF-8')
                            . '" loading="lazy">';
                    } else {
                        // unicode 类型：原字符直接输出
                        $cache[$key] = (string)$r['char'];
                    }
                }
            } catch (Throwable $e) {
                return $text; // 表不存在（旧库未跑升级）→ 原样返回
            }
        }
        if (!$cache) return $text;

        // 保护 <pre><code>...</code></pre> 与 <code>...</code>，避免内部 :code: 被误替换
        $guards = [];
        $text = preg_replace_callback('/<(?:pre|code)\b[^>]*>[\s\S]*?<\/(?:pre|code)>/i', function ($m) use (&$guards) {
            $k = "\x01EMOJI_GUARD_" . count($guards) . "\x01";
            $guards[$k] = $m[0];
            return $k;
        }, $text);

        // 一次性替换所有 :code:
        $text = strtr($text, $cache);

        // 还原代码块 / 行内代码
        if ($guards) {
            $text = str_replace(array_keys($guards), array_values($guards), $text);
        }
        return $text;
    }
}

if (!function_exists('pagination')) {
    function pagination($total, $page, $perPage, $route, $params = []) {
        $totalPages = max(1, ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $html = '<div class="pagination">';
        if ($page > 1) {
            $html .= '<a href="' . url($route, array_merge($params, ['page' => $page - 1])) . '" class="page-btn">上一页</a>';
        }
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        if ($start > 1) {
            $html .= '<a href="' . url($route, array_merge($params, ['page' => 1])) . '" class="page-btn">1</a>';
            if ($start > 2) $html .= '<span class="page-ellipsis">...</span>';
        }
        for ($i = $start; $i <= $end; $i++) {
            $cls = $i == $page ? 'page-btn active' : 'page-btn';
            $html .= '<a href="' . url($route, array_merge($params, ['page' => $i])) . '" class="' . $cls . '">' . $i . '</a>';
        }
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) $html .= '<span class="page-ellipsis">...</span>';
            $html .= '<a href="' . url($route, array_merge($params, ['page' => $totalPages])) . '" class="page-btn">' . $totalPages . '</a>';
        }
        if ($page < $totalPages) {
            $html .= '<a href="' . url($route, array_merge($params, ['page' => $page + 1])) . '" class="page-btn">下一页</a>';
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('normalize_post_images')) {
    /**
     * 把 posts.images 字段（JSON）归一化为统一结构数组：
     *   [{url, reply_visible}]  ；兼容旧数据（纯字符串 URL 或缺失字段）
     */
    function normalize_post_images($raw) {
        if (empty($raw)) return [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) return [];
        } else {
            $decoded = $raw;
        }
        if (!is_array($decoded)) return [];
        $out = [];
        foreach ($decoded as $item) {
            if (is_string($item)) {
                $out[] = ['url' => $item, 'reply_visible' => 0];
            } elseif (is_array($item) && !empty($item['url'])) {
                $out[] = [
                    'url' => $item['url'],
                    'reply_visible' => !empty($item['reply_visible']) ? 1 : 0,
                ];
            }
        }
        return $out;
    }
}

if (!function_exists('parse_comment_images')) {
    /**
     * 把 comments.image 字段（JSON 数组 或 旧版单图字符串）归一化为路径数组：
     *   ['uploads/post/xxx.jpg', ...]
     * 仅返回安全的站内 uploads 相对路径；外链/路径穿越自动丢弃。
     */
    function parse_comment_images($raw) {
        if (empty($raw)) return [];
        if (is_array($raw)) {
            $arr = $raw;
        } elseif (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $arr = $decoded;
            } else {
                // 老单图字符串：直接当 1 张
                $arr = [$raw];
            }
        } else {
            return [];
        }
        $out = [];
        foreach ($arr as $p) {
            if (!is_string($p)) continue;
            $p = trim($p);
            if ($p === '') continue;
            if (strpos($p, 'uploads/') !== 0) continue;
            if (!preg_match('#^uploads/[A-Za-z0-9_./\-]+$#', $p)) continue;
            $out[] = $p;
        }
        return $out;
    }
}

if (!function_exists('normalize_post_attachments')) {
    /**
     * 把 posts.attachments 字段（JSON）归一化为统一结构数组：
     *   [{name, url, size, reply_visible}] ；兼容旧数据（缺字段时使用默认值）
     */
    function normalize_post_attachments($raw) {
        if (empty($raw)) return [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) return [];
        } else {
            $decoded = $raw;
        }
        if (!is_array($decoded)) return [];
        $out = [];
        foreach ($decoded as $item) {
            if (!is_array($item) || empty($item['url'])) continue;
            $out[] = [
                'name' => $item['name'] ?? basename($item['url']),
                'url' => $item['url'],
                'size' => $item['size'] ?? 0,
                'reply_visible' => !empty($item['reply_visible']) ? 1 : 0,
            ];
        }
        return $out;
    }
}

if (!function_exists('can_view_hidden_media')) {
    /**
     * 判断当前用户是否可查看该帖「回复可见」的图片/附件：
     * 作者、管理员/超管、已对该帖发表过评论者 => 可见
     */
    function can_view_hidden_media($post) {
        if (!Auth::check()) return false;
        if (is_admin()) return true;
        $uid = Auth::id();
        if (!empty($post['user_id']) && $post['user_id'] == $uid) return true;
        $count = Model::table('comments')
            ->where('post_id', $post['id'])
            ->where('user_id', $uid)
            ->where('status', 1)
            ->count();
        return $count > 0;
    }
}

if (!function_exists('user_display_name')) {
    /**
     * 用户前台显示名（统一规则，与 templates/post/_comment.php 渲染评论作者时一致）。
     *   1. status=0（已注销）→ 「已注销用户」
     *   2. 填了昵称（nickname 非空）→ 显示昵称
     *   3. 未填昵称 → 显示用户名
     *   ⚠️ 历史 bug：直接用 Auth::user()['nickname'] 给通知 content 拼前缀，
     *       当用户没填昵称时就变成 ` 评论了你的帖子...`（前缀为空字符串），
     *       导致通知中心看起来"少了发送者名字"。改用本函数即可统一处理。
     * @param array|null $user 用户数组（含 status / nickname / username）
     * @return string
     */
    function user_display_name($user) {
        if (empty($user) || !is_array($user)) return '已注销用户';
        $isDeleted = isset($user['status']) && (int)$user['status'] === 0;
        $nick = trim((string)($user['nickname'] ?? ''));
        $uname = trim((string)($user['username'] ?? ''));
        if ($isDeleted || ($nick === '' && $uname === '')) return '已注销用户';
        if ($nick !== '') return $nick;
        return $uname;
    }
}

/**
 * 评论通知跳转 URL（绝对 URL + 必带主评论分页 + 锚到具体 comment）。
 *
 * 历史 bug：
 *   旧实现 = url('post/show', ['id' => $id]) . '#comment-' . $cid
 *   1) 没带 page 参数。当该评论不在第 1 页主评论页（每页 15 条）时，从通知点过去
 *      只能看到第 1 页页面的顶层评论，#comment-N 在 DOM 里根本不存在 → 锚点丢了
 *      浏览器落到页面顶部，用户体感"点了查看但没跳到提醒位置"。
 *   2) permalink 启用时 url() 返回相对路径（如 /c/5/123-test），再拼
 *      '#comment-N' 整体被浏览器按当前页语境解释；如果当前页与目标页路径相似
 *      又带 hash，浏览器可能原地"软跳转"，hash 被当成相对引用 → 定位也丢失。
 *
 * 修复：
 *   - 沿 parent 链向上找 root 顶层评论（与 buildCommentTree 同源）。
 *   - 计算 root 在「顶层评论按 id 升序」中的索引位置，对应到分页到哪一页。
 *   - 用 url(..., true) 强制返回绝对 URL（含 scheme+host），不受当前页路径
 *     与 permalink 设置影响，绝对稳定地跳到目标帖子页。
 *
 * @param int $postId    帖子 id
 * @param int $commentId 评论 id（0 = 仅跳到帖子首页，无锚点）
 * @return string        "https://domain/c/5/123-title?page=2#comment-N" 或空串
 */
if (!function_exists('comment_target_url')) {
    function comment_target_url($postId, $commentId = 0) {
        $postId = (int)$postId;
        $commentId = (int)$commentId;
        if ($postId <= 0) return '';
        if ($commentId <= 0) {
            return url('post/show', ['id' => $postId], true);
        }
        // 沿 parent 链向上找 root 顶层评论（guard 50 防异常循环）
        $cur = $commentId; $guard = 0; $rootId = $commentId;
        while ($cur && $guard < 50) {
            $rows = Model::query('SELECT id, parent_id FROM comments WHERE id = ? LIMIT 1', [$cur]);
            $row  = $rows[0] ?? null;
            if (!$row || empty($row['parent_id'])) break;
            $cur = (int)$row['parent_id'];
            if ($cur > 0) $rootId = $cur;
            $guard++;
        }
        // 计算 root 在顶层评论（parent_id IS NULL OR 0）按 id 升序的页码
        $page = 1;
        if ($rootId > 0) {
            $idx  = (int)Model::scalar(
                'SELECT COUNT(*) FROM comments
                 WHERE post_id = ? AND status = 1
                   AND (parent_id IS NULL OR parent_id = 0)
                   AND id <= ?',
                [$postId, $rootId]
            );
            $page = max(1, (int)ceil($idx / COMMENT_PER_PAGE));
        }
        return url('post/show', ['id' => $postId, 'page' => $page], true) . '#comment-' . $commentId;
    }
}

if (!function_exists('role_display_name')) {
    /**
     * 角色前台显示名：严格取自后台「角色权限」里 roles 表的 name 字段。
     * 后台编辑角色名称后，前台个人主页 / 个人中心会同步显示，做到一一对应。
     * 查不到（如旧库缺 roles 数据）时回退到内置中文默认映射，保证不报错。
     * @param string $role 角色 code（与 users.role、roles.code 一致）
     * @return string
     */
    function role_display_name($role) {
        static $map = null;
        if ($map === null) {
            $map = [];
            try {
                $rows = Model::table('roles')->get();
                foreach ($rows as $r) {
                    if (!empty($r['code'])) $map[$r['code']] = $r['name'];
                }
            } catch (Exception $e) {
                $map = [];
            }
        }
        if (isset($map[$role]) && $map[$role] !== '') return $map[$role];
        $defaults = [
            'guest' => '游客',
            'user' => '会员',
            'certified_user' => '认证用户',
            'moderator' => '版主',
            'admin' => '管理员',
            'super_admin' => '超级管理员',
            'webmaster' => '站长',
        ];
        return $defaults[$role] ?? (string)$role;
    }
}

if (!function_exists('role_show_badge')) {
    /**
     * 该角色是否在前台（个人主页 / 个人中心）展示身份徽章。
     * 版主、管理员、超级管理员、站长均展示；普通/认证用户不展示，保持原有视觉习惯。
     * 如需让后台自定义角色也显示，调整此白名单即可。
     * @param string $role 角色 code
     * @return bool
     */
    function role_show_badge($role) {
        return in_array($role, ['moderator', 'admin', 'super_admin', 'webmaster'], true);
    }
}

if (!function_exists('register_mode')) {
    /**
     * 注册模式：open=开放注册 / closed=关闭注册 / invite=邀请注册。
     * 后台未配置时默认 open（开放注册），保证升级前已安装站点行为不发生突变。
     * @return string
     */
    function register_mode() {
        $m = trim((string)setting('register_mode', ''));
        if ($m === '') return 'open';
        return in_array($m, ['open', 'closed', 'invite'], true) ? $m : 'open';
    }
}

if (!function_exists('invite_allowed_roles')) {
    /**
     * 返回允许发起邀请的角色 code 数组（后台「邀请权限」勾选结果）。
     * @return array
     */
    function invite_allowed_roles() {
        $raw = trim((string)setting('invite_allowed_roles', ''));
        if ($raw === '') return [];
        return array_values(array_filter(array_map('trim', explode(',', $raw)), function ($r) {
            return $r !== '';
        }));
    }
}

if (!function_exists('can_invite')) {
    /**
     * 当前用户是否拥有「邀请新用户注册」权限。
     * 规则：
     *  - 未登录 → false
     *  - 注册模式不是 invite → false
     *  - 否则仅当该用户角色出现在后台「邀请权限」勾选列表时 → true
     * 后台未勾选任何角色 → false（前台「邀请」按钮消失 + 邀请页返回 403）。
     * @param array|null $user 用户数组（含 id / role）
     * @return bool
     */
    function can_invite($user) {
        if (empty($user) || empty($user['id'])) return false;
        if (register_mode() !== 'invite') return false;
        $role = $user['role'] ?? '';
        if ($role === '') return false;
        $allowed = invite_allowed_roles();
        if (empty($allowed)) return false;
        return in_array($role, $allowed, true);
    }
}

/**
 * 页脚默认数据（参考「新片场」截图）
 *  - 当 settings 表里 site_footer_* 键缺失/为空字符串时，前台 layout 自动 fallback 到这里
 *  - 站长在后台「页脚设置」填了内容后自动覆盖；填回空字符串/删除键 → 恢复这里
 *  - 数据结构：
 *      site_name        站名（用于 logo 旁文字）
 *      site_logo        站 logo（相对 uploads/）
 *      site_intro       站点简介（≤1 行）
 *      columns[]        4 栏菜单
 *          title
 *          links[]       {name, url}，每栏最多 5 个
 *      socials[]        社交媒体（默认 3 个，可继续添加）
 *          icon          字体图标类名（fa-wechat / fa-weibo / fa-qq / fa-github ...）
 *          type          link | qrcode
 *          value         链接 URL 或二维码相对路径
 *          title         hover 提示
 *      copyright        版权行（© xxx）
 *      icp              备案号行
 *      contact_extra    联系方式行（电话/邮箱/地址等）
 *      reserved_html    预留 HTML 块（用户填充才生效；空时前台不渲染）
 */
if (!function_exists('default_footer_data')) {
    function default_footer_data() {
        return [
            'site_name' => '**论坛',
            'site_logo' => '',
            'site_intro' => '**论坛是一个专注于内容分享与社区交流的轻量级论坛系统，致力打造简洁、易用、温暖的讨论空间。',
            'columns' => [
                [
                    'title' => '网站导航',
                    'links' => [
                        ['name' => '首页', 'url' => 'home/index'],
                        ['name' => '热门', 'url' => 'home/index?sort=hot'],
                        ['name' => '精华', 'url' => 'home/index?sort=essence'],
                        ['name' => '认证中心', 'url' => 'certification/index'],
                        ['name' => '消息通知', 'url' => 'notification/index'],
                    ],
                ],
                [
                    'title' => '关于',
                    'links' => [
                        ['name' => '关于我们', 'url' => '#about'],
                        ['name' => '加入我们', 'url' => '#join'],
                        ['name' => '使用帮助', 'url' => '#help'],
                        ['name' => '用户协议', 'url' => '#terms'],
                        ['name' => '隐私政策', 'url' => '#privacy'],
                    ],
                ],
                [
                    'title' => '活动',
                    'links' => [
                        ['name' => '站务公告', 'url' => '#announce'],
                        ['name' => '认证活动', 'url' => '#cert-event'],
                        ['name' => '校园活动', 'url' => '#campus'],
                        ['name' => '主题征集', 'url' => '#collect'],
                        ['name' => '更多活动', 'url' => '#more'],
                    ],
                ],
                [
                    'title' => '社区',
                    'links' => [
                        ['name' => '社区规范', 'url' => '#rule'],
                        ['name' => '新手指南', 'url' => '#guide'],
                        ['name' => '功能介绍', 'url' => '#feature'],
                        ['name' => '问题反馈', 'url' => '#feedback'],
                        ['name' => '友情链接', 'url' => '#friend'],
                    ],
                ],
            ],
            'socials' => [
                ['icon' => 'fa-wechat', 'type' => 'qrcode', 'value' => '', 'title' => '微信公众号'],
                ['icon' => 'fa-weibo',  'type' => 'link',    'value' => 'https://weibo.com/', 'title' => '官方微博'],
                ['icon' => 'fa-qq',     'type' => 'link',    'value' => 'https://wpa.qq.com/', 'title' => '官方QQ'],
            ],
            'copyright' => 'Copyright © 2013 - ' . date('Y') . ' **论坛. All rights reserved.',
            'icp' => '京ICP备0000000号-1',
            'police_record' => '', // 公安备案号默认空 → 前台不渲染该行（占位也不显示）
            'contact_extra' => '联系邮箱：service@example.com',
        ];
    }
}

/**
 * 取最终生效的页脚数据：用户配置 + 默认值合并
 *  - 优先取 settings 表的 site_footer_columns / socials / copyright 三个键
 *  - 单字段缺失或为空字符串时，自动 fallback 到 default_footer_data() 对应字段
 *  - police_record（公安备案号）：默认空字符串 → 前台不渲染（占位也不显示）
 */
if (!function_exists('get_footer_data')) {
    function get_footer_data() {
        $def = default_footer_data();
        $stored = [];
        try {
            foreach (Model::query("SELECT key_name, value FROM settings WHERE key_name IN ('site_footer_columns','site_footer_socials','site_footer_copyright','site_footer_site')") as $r) {
                $stored[$r['key_name']] = $r['value'];
            }
        } catch (Throwable $e) { $stored = []; }

        // 列、社交：从 JSON 解析
        $cols = json_decode($stored['site_footer_columns'] ?? '', true);
        if (!is_array($cols) || empty($cols)) $cols = $def['columns'];
        $socials = json_decode($stored['site_footer_socials'] ?? '', true);
        if (!is_array($socials) || empty($socials)) $socials = $def['socials'];

        // 版权块：解析 JSON，否则直接取默认
        $cp = json_decode($stored['site_footer_copyright'] ?? '', true);
        if (!is_array($cp)) $cp = [];

        // 站点信息：解析 JSON，单字段空时返回空字符串（前台 layout 自行决定是否 fallback 到默认）
        $siteCfg = json_decode($stored['site_footer_site'] ?? '', true);
        if (!is_array($siteCfg)) $siteCfg = [];

        return [
            // site_name 透传用户值（空 = 用户主动清空，前台不显示默认站名）
            'site_name'    => isset($siteCfg['name'])  ? trim((string)$siteCfg['name'])  : '',
            'site_logo'    => isset($siteCfg['logo'])  ? trim((string)$siteCfg['logo'])  : '',
            'site_intro'   => isset($siteCfg['intro']) ? trim((string)$siteCfg['intro']) : '',
            'columns'      => $cols,
            'socials'      => $socials,
            'copyright'    => isset($cp['copyright']) && $cp['copyright'] !== '' ? $cp['copyright'] : $def['copyright'],
            'icp'          => isset($cp['icp']) && $cp['icp'] !== '' ? $cp['icp'] : $def['icp'],
            'police_record'=> isset($cp['police_record']) ? trim((string)$cp['police_record']) : '',
            'contact_extra'=> isset($cp['contact_extra']) && $cp['contact_extra'] !== '' ? $cp['contact_extra'] : $def['contact_extra'],
        ];
    }
}

/**
 * 将图标类名归一化为 Font Awesome 6 的写法（项目整体从 4.7 升级至 6.7.x）。
 *
 * 接受的输入形式：
 *  - FA4 类前缀写法：fa fa-home
 *  - FA6 标准写法：   fa-solid fa-house / fa-regular fa-user / fa-brands fa-github
 *  - FA5 缩写：       fas fa-home / far fa-user / fab fa-github
 *  - 裸图标名：       home / user / weibo
 *  - 旧 FA4 类名重命名后：fa fa-home / fa fa-github / fa fa-weibo（→ fa-solid fa-house / fa-brands fa-github / fa-brands fa-weibo）
 *  - 非 FA 图标：     iconfont-weibo_line → 原样返回（iconfont 兜底）
 *
 * 输出形式：FA6 标准类名，如 "fa-solid fa-house" / "fa-brands fa-github"。
 *
 * @param string $raw 原始图标类名
 * @return string 归一后的 FA6 类名
 */
if (!function_exists('fa_icon_class')) {
    /**
     * FA4 → FA6 重命名映射（旧名 => 新名）。
     * 适用于常见板块/导航/社交图标，未命中则尝试原名（FA4 与 FA6 同名图标很多）。
     * 数据来源：FA 官方 4.x → 6.x changelog + icons.json。
     */
    function _fa4_to_fa6_renamed() {
        return [
            // 老 FA4 类名 => FA6 新类名
            'home'                => 'house',
            'gear'                => 'gear',            // FA6 仍保留 gear；cog 是新名
            'cog'                 => 'gear',           // 反向
            'wrench'              => 'wrench',
            'pencil'              => 'pencil',
            'pencil-square-o'     => 'pen-to-square',
            'edit'                => 'pen-to-square',
            'sign-out'            => 'right-from-bracket',
            'sign-in'             => 'right-to-bracket',
            'sign-in-alt'         => 'right-to-bracket',
            'sign-out-alt'        => 'right-from-bracket',
            'trash-o'             => 'trash-can',
            'trash'               => 'trash',          // FA6 保留 trash；trash-can 是新名
            'file-text-o'         => 'file-lines',
            'file-text'           => 'file-lines',
            'file-image-o'        => 'file-image',
            'image'               => 'image',
            'picture-o'           => 'image',
            'photo'               => 'image',
            'video-camera'        => 'video',
            'mobile'              => 'mobile-screen',
            'mobile-phone'        => 'mobile-screen',
            'phone'               => 'phone',
            'weibo'               => 'weibo',
            'weixin'              => 'weixin',         // FA6 保留
            'qq'                  => 'qq',
            'envelope-o'          => 'envelope',
            'envelope-open-o'     => 'envelope-open',
            'bell-o'              => 'bell',
            'star-o'              => 'star',
            'star-half-o'         => 'star-half',
            'star-half-alt'       => 'star-half',
            'heart-o'             => 'heart',
            'user-o'              => 'user',
            'user-circle-o'       => 'user',
            'user-circle'         => 'user',
            'users'               => 'users',
            'search'              => 'magnifying-glass',
            'search-plus'         => 'magnifying-glass-plus',
            'search-minus'        => 'magnifying-glass-minus',
            'times'               => 'xmark',
            'times-circle'        => 'circle-xmark',
            'times-circle-o'      => 'circle-xmark',
            'close'               => 'xmark',
            'check'               => 'check',
            'check-circle'        => 'circle-check',
            'check-circle-o'      => 'circle-check',
            'check-square-o'      => 'square-check',
            'plus'                => 'plus',
            'plus-circle'         => 'circle-plus',
            'plus-square-o'       => 'square-plus',
            'minus'               => 'minus',
            'minus-circle'        => 'circle-minus',
            'minus-square-o'      => 'square-minus',
            'info-circle'         => 'circle-info',
            'info'                => 'circle-info',
            'question'            => 'question',
            'question-circle'     => 'circle-question',
            'question-circle-o'   => 'circle-question',
            'warning'             => 'triangle-exclamation',
            'exclamation'         => 'circle-exclamation',
            'exclamation-circle'  => 'circle-exclamation',
            'exclamation-triangle' => 'triangle-exclamation',
            'thumbs-o-up'         => 'thumbs-up',
            'thumbs-o-down'       => 'thumbs-down',
            'arrow-circle-o-down' => 'circle-down',
            'arrow-circle-o-up'   => 'circle-up',
            'arrow-circle-o-right'=> 'circle-right',
            'arrow-circle-o-left' => 'circle-left',
            'caret-down'          => 'caret-down',
            'caret-up'            => 'caret-up',
            'caret-left'          => 'caret-left',
            'caret-right'         => 'caret-right',
            'chevron-down'        => 'chevron-down',
            'chevron-up'          => 'chevron-up',
            'chevron-left'        => 'chevron-left',
            'chevron-right'       => 'chevron-right',
            'angle-down'          => 'angle-down',
            'angle-up'            => 'angle-up',
            'angle-left'          => 'angle-left',
            'angle-right'         => 'angle-right',
            'arrow-down'          => 'arrow-down',
            'arrow-up'            => 'arrow-up',
            'arrow-left'          => 'arrow-left',
            'arrow-right'         => 'arrow-right',
            'refresh'             => 'arrows-rotate',
            'qrcode'              => 'qrcode',         // FA6 保留
            'bar-chart-o'         => 'chart-column',
            'line-chart'          => 'chart-line',
            'pie-chart'           => 'chart-pie',
            'globe'               => 'globe',
            'link'                => 'link',
            'unlink'              => 'link-slash',
            'chain'               => 'link',
            'paperclip'           => 'paperclip',
            'bookmark-o'          => 'bookmark',
            'tag'                 => 'tag',
            'tags'                => 'tags',
            'folder-o'            => 'folder',
            'folder-open-o'       => 'folder-open',
            'folder'              => 'folder',
            'file-o'              => 'file',
            'files-o'             => 'files',
            'cloud-download'      => 'cloud-arrow-down',
            'cloud-upload'        => 'cloud-arrow-up',
            'download'            => 'download',
            'upload'              => 'upload',
            'print'               => 'print',
            'share-alt'           => 'share-nodes',
            'share'               => 'share',
            'reply'               => 'reply',
            'reply-all'           => 'reply-all',
            'comment-o'           => 'comment',
            'commenting-o'        => 'comment',
            'comments-o'          => 'comments',
            'thumb-tack'          => 'thumbtack',
            'bolt'                => 'bolt',
            'bullhorn'            => 'bullhorn',
            'fire'                => 'fire',
            'flag'                => 'flag',
            'trophy'              => 'trophy',
            'graduation-cap'      => 'graduation-cap',
            'book'                => 'book',
            'newspaper-o'         => 'newspaper',
            'lightbulb-o'         => 'lightbulb',
            'microphone'          => 'microphone',
            'camera'              => 'camera',
            'music'               => 'music',
            'film'                => 'film',
            'map-marker'          => 'location-dot',
            'map-o'               => 'map',
            'map'                 => 'map',
            'compass'             => 'compass',
            'database'            => 'database',
            'server'              => 'server',
            'code'                => 'code',
            'plug'                => 'plug',
            'shopping-cart'       => 'cart-shopping',
            'shopping-bag'        => 'bag-shopping',
            'credit-card'         => 'credit-card',
            'money'               => 'money-bill',
            'gift'                => 'gift',
            'list-ul'             => 'list-ul',
            'list-ol'             => 'list-ol',
            'list'                => 'list',
            'list-alt'            => 'list',
            'th-list'             => 'table-list',
            'th'                  => 'table-cells',
            'th-large'            => 'table-cells-large',
            'table'               => 'table',
            'columns'             => 'table-columns',
            'eye'                 => 'eye',
            'eye-slash'           => 'eye-slash',
            'eye-dropper'         => 'eye-dropper',
            'lock'                => 'lock',
            'unlock'              => 'lock-open',
            'unlock-alt'          => 'lock-open',
            'key'                 => 'key',
            'shield'              => 'shield',
            'ban'                 => 'ban',
            'bell-slash'          => 'bell-slash',
            'wifi'                => 'wifi',
            'rss'                 => 'rss',
            'feed'                => 'rss',
            'spinner'             => 'spinner',
            'cogs'                => 'gears',
            'rocket'              => 'rocket',
            'plane'               => 'plane',
            'ship'                => 'ship',
            'car'                 => 'car',
            'taxi'                => 'taxi',
            'bus'                 => 'bus',
            'train'               => 'train',
            'subway'              => 'train-subway',
            'bicycle'             => 'bicycle',
            'motorcycle'          => 'motorcycle',
            'truck'               => 'truck',
            'anchor'              => 'anchor',
            'binoculars'          => 'binoculars',
            'bomb'                => 'bomb',
            'crosshairs'          => 'crosshairs',
            'dashboard'           => 'gauge',
            'tachometer'          => 'gauge',
            'cube'                => 'cube',
            'cubes'               => 'cubes',
            'archive'             => 'box-archive',
            'briefcase'           => 'briefcase',
            'calendar'            => 'calendar',
            'calendar-o'          => 'calendar',
            'clock-o'             => 'clock',
            'hourglass'           => 'hourglass',
            'hourglass-start'     => 'hourglass-start',
            'hourglass-half'      => 'hourglass-half',
            'hourglass-end'       => 'hourglass-end',
            'history'             => 'clock-rotate-left',
            'futbol-o'            => 'futbol',
            'soccer-ball-o'       => 'futbol',
            'basketball-ball'     => 'basketball',
            'gamepad'             => 'gamepad',
            'paw'                 => 'paw',
            'bug'                 => 'bug',
            'tree'                => 'tree',
            'coffee'              => 'mug-saucer',
            'beer'                => 'beer-mug-empty',
            'birthday-cake'       => 'cake-candles',
            'cutlery'             => 'utensils',
            'pizza-slice'         => 'pizza-slice',
            'snowflake-o'         => 'snowflake',
            'sun-o'               => 'sun',
            'moon-o'              => 'moon',
            'cloud'               => 'cloud',
            'star-half-empty'     => 'star-half-stroke',
            'star-half-full'      => 'star-half',
        ];
    }

    function fa_icon_class($raw) {
        $raw = trim((string)$raw);
        if ($raw === '') return '';

        // 0) 入口自愈：历史脏数据中可能残留 fa--xxx 双横杠（如「fa-brands fa--fawikipedia-w」），
        //    折叠为 fa-xxx，避免后续归一再次产出 fa---xxx 三横杠。合法 fa-house / fa-weibo 不动。
        $raw = preg_replace('/\bfa--(?:fa-)?/', 'fa-', $raw);

        // 判定家族（brands / regular / solid），默认 solid
        $family = ''; // fas|far|fab|fal|fat
        if (preg_match('/\bfa-brands\b|\bfab\b/', $raw))        $family = 'fa-brands';
        elseif (preg_match('/\bfa-regular\b|\bfar\b/', $raw))   $family = 'fa-regular';
        elseif (preg_match('/\bfa-light\b|\bfal\b/', $raw))     $family = 'fa-light';
        elseif (preg_match('/\bfa-thin\b|\bfat\b/', $raw))      $family = 'fa-thin';
        elseif (preg_match('/\bfa-duotone\b/', $raw))           $family = 'fa-duotone';
        elseif (preg_match('/\bfa-solid\b|\bfas\b/', $raw))     $family = 'fa-solid';

        // 已知品牌白名单（输入是旧类名 fa-weibo / fa-github / fa-qq 等 → 强制 brands）
        static $brandOldNames = [
            'weibo','weixin','qq','wechat','github','google','google-plus','google-plus-square','google-wallet',
            'facebook','facebook-f','facebook-square','twitter','twitter-square','instagram','linkedin',
            'linkedin-in','youtube','youtube-square','youtube-play','pinterest','pinterest-p','pinterest-square',
            'tumblr','tumblr-square','reddit','reddit-square','telegram','tiktok','discord','snapchat',
            'whatsapp','vimeo','vimeo-square','vimeo-v','dribbble','dribbble-square','behance','behance-square',
            'vk','twitch','flickr','steam','spotify','amazon','apple','microsoft','android','linux',
            'android','chrome','firefox','safari','opera','edge','paypal','stripe','bitcoin','btc','ethereum',
            'discord','slack','line','line-chart','medium','product-hunt','stack-overflow','stack-exchange',
            'codepen','github-alt','github-square','gitlab','jsfiddle','symfony','vuejs','react','angular',
            'node','node-js','nodejs','npm','suse','redhat','ubuntu','centos','fedora','debian','mint',
            'windows','playstation','xbox','nintendo-switch','itch-io','itchio','etsy','shopware',
            'amazon-pay','cc-visa','cc-mastercard','cc-amex','cc-paypal','cc-stripe','cc-discover',
            'cc-jcb','cc-diners-club','cc-apple-pay','google-pay','apple-pay','paypal','btc','btc-square',
            'youtube-v','youtube-brands','telegram-plane','telegram-square','line-messenger','line-square',
            'lastfm','soundcloud','spotify','apple-music','itunes','itunes-note','deezer','bandcamp',
            'mixcloud','500px','accusoft','adn','adversal','affiliatetheme','airbnb','algolia',
            'amilia','angrycreative','app-store','app-store-ios','apper-systems','asymmetrik',
            'audible','autoprefixer','avianex','aviato','aws','bimobject','bitbucket','bitcoin',
            'bitly','blackberry','black-tie','blogger','blogger-b','bootstrap','btc','buffer',
            'buysellads','buymeacoffee','canadian-maple-leaf','cc-amazon-pay','cc-visa','centercode',
            'chrome','chromecast','cloudscale','cloudsmith','cloudversify','codepen','codiepie',
            'confluence','connectdevelop','contao','cpanel','creative-commons','creative-commons-by',
            'creative-commons-nc','creative-commons-nc-eu','creative-commons-nc-jp','creative-commons-pd',
            'creative-commons-remix','creative-commons-sa','creative-commons-sampling','creative-commons-share',
            'creative-commons-zero','critical-role','css3','css3-alt','cuttlefish','d-and-d','dashcube',
            'delicious','deploydog','deskpro','dev','deviantart','dhl','diaspora','digg','digital-ocean',
            'discord','discourse','dochub','docker','draft2digital','dribbble','dribbble-square','dropbox',
            'drupal','dyalog','earlybirds','ebay','edge','elementor','ello','ember','empire','envira',
            'erlang','ethereum','etsy','expeditedssl','facebook','facebook-f','facebook-messenger',
            'facebook-square','fantasy-flight-games','fedex','fedora','figma','firefox','firstdraft',
            'first-order','first-order-alt','fisheye','flickr','flipboard','fly','fonticons','fonticons-fi',
            'fort-awesome','fort-awesome-alt','forumbee','foursquare','freebsd','fulcrum','galactic-republic',
            'galactic-senate','get-pocket','gg','gg-circle','git','git-alt','git-square','github',
            'github-alt','github-square','gitkraken','gitlab','gitter','glide','glide-g','gofore','goodreads',
            'goodreads-g','google','google-drive','google-pay','google-play','google-plus','google-plus-g',
            'google-plus-square','google-wallet','gratipay','grav','gripfire','grunt','guilded','gulp',
            'hacker-news','hacker-news-square','hackerrank','hips','hire-a-helper','hive','hooli','hornbill',
            'hotjar','houzz','html5','hubspot','ideal','imdb','innosoft','instagram','instagram-square',
            'instalod','intercom','internet-explorer','invision','ioxhost','itch-io','itunes','itunes-note',
            'java','jenkins','jira','joget','joomla','js','jsfiddle','jsquare','keycdn','keybase',
            'kickstarter','kickstarter-k','korvue','laravel','lastfm','leanpub','less','line','linkedin',
            'linkedin-in','linkedin-square','linux','lyft','magento','mailchimp','mandalorian','markdown',
            'mastodon','maxcdn','mdb','medapps','medium','medium-m','medrt','meetup','megaport','mendeley',
            'microblog','microsoft','mix','mixcloud','mixer','mixcloud','mizuni','modx','monero','napster',
            'neos','nfc-directional','nfc-symbol','nimblr','node','node-js','nodejs','npm','ns8','nutritionix',
            'odnoklassniki','odnoklassniki-square','old-republic','opencart','openid','opera','orcid',
            'osi','page4','pagelines','palfed','patreon','paypal','perbyte','periscope','phabricator',
            'phoenix-framework','phoenix-squadron','php','pied-piper','pied-piper-alt','pied-piper-hat',
            'pied-piper-pp','pied-piper-square','pinterest','pinterest-p','pinterest-square','playstation',
            'product-hunt','pushed','python','qq','quinscape','quora','r-project','raspberry-pi','ravelry',
            'react','reacteurope','readme','rebel','red-river','reddit','reddit-alien','reddit-square',
            'redhat','renren','replyd','researchgate','resolving','rev','rocketchat','rockrms','rust',
            'safari','salesforce','sass','schlix','scribd','searchengin','sellcast','sellsy','servicestack',
            'shirtsinbulk','shopify','shopware','simplybuilt','sistrix','sith','sketch','skyatlas','skype',
            'slack','slack-hash','slideshare','snapchat','snapchat-ghost','snapchat-square','soundcloud',
            'sourcetree','speakap','spotify','squarespace','stack-exchange','stack-overflow','stackpath',
            'staylinked','steam','steam-square','steam-symbol','sticker-mule','strava','stripe','stripe-s',
            'studiovinari','stumbleupon','stumbleupon-circle','superpowers','supple','suse','swift',
            'symfony','teamspeak','telegram','telegram-plane','telegram-square','tencent','themeco',
            'themeisle','think-peaks','tiktok','trade-federation','trello','tripadvisor','trove','tumblr',
            'tumblr-square','twitch','twitter','twitter-square','typo3','uber','ubuntu','uikit',
            'umbrella','uncharted','uniregistry','unity','unsplash','untappd','ups','usb','usps','ussunnah',
            'vaadin','viacoin','viadeo','viadeo-square','viber','viber-square','vimeo','vimeo-square',
            'vimeo-v','vine','vk','vnv','vuejs','watchman-monitoring','waze','weebly','weibo','weixin',
            'whatsapp','whatsapp-square','whmcs','wikipedia-w','windows','wix','wizards-of-the-coast',
            'wolf-pack-battalion','wordpress','wordpress-simple','wpbeginner','wpexplorer','wpforms',
            'wpressr','xbox','xing','xing-square','yahoo','yammer','yandex','yandex-international',
            'yarn','yelp','yoast','youtube','youtube-square','zhihu',
        ];
        static $brandOldNamesSet = null;
        if ($brandOldNamesSet === null) $brandOldNamesSet = array_flip($brandOldNames);

        // 1) 已是 FA6 形式（含 fa-solid/fa-regular/fa-brands 等前缀） → 归一输出
        if ($family !== '') {
            // 提取图标名
            // 注意：\b(fa)\b 会误剥 "fa-home" 里的 fa，导致 fa--home 这种非法类名。
            // 改用 (?=\s|$) 限定：只剥「独立成词的 fa」（后跟空白或结尾），保留 fa-home / fa-solid 等复合词中的 fa。
            $clean = preg_replace('/\b(?:fa-solid|fa-regular|fa-light|fa-thin|fa-duotone|fa-brands|fas|far|fal|fat|fab)\b|\bfa\b(?=\s|$)/', ' ', $raw);
            $clean = trim(preg_replace('/\s+/', ' ', $clean));
            $iconName = '';
            foreach (explode(' ', $clean) as $p) {
                $p = trim($p);
                if ($p === '') continue;
                if (strpos($p, 'fa-') === 0) { $iconName = substr($p, 3); break; }
                $iconName = $p; break;
            }
            if ($iconName === '') return $raw;
            // 别名映射（FA4 老类名 → FA6 新类名）
            $map = _fa4_to_fa6_renamed();
            if (isset($map[$iconName])) $iconName = $map[$iconName];
            return $family . ' fa-' . $iconName;
        }

        // 2) 含 "fa" 但没家族前缀 → 视为 FA4 类名解析
        if (preg_match('/\bfa\b/', $raw) && strpos($raw, 'fa-') !== false) {
            // 例 "fa fa-home" 或 "fa-home" 或 "fa fa-weibo"
            // 只剥独立的 fa 词（后跟空白或结尾），保留 "fa-home" 复合词中的 fa，避免产生 fa--home 非法类名
            $clean = preg_replace('/\bfa\b(?=\s|$)/', ' ', $raw);
            $clean = trim(preg_replace('/\s+/', ' ', $clean));
            $iconName = '';
            foreach (explode(' ', $clean) as $p) {
                $p = trim($p);
                if ($p === '') continue;
                if (strpos($p, 'fa-') === 0) { $iconName = substr($p, 3); break; }
                $iconName = $p; break;
            }
            if ($iconName === '') return $raw;
            $map = _fa4_to_fa6_renamed();
            if (isset($map[$iconName])) { $new = $map[$iconName]; return (isset($brandOldNamesSet[$iconName]) ? 'fa-brands' : 'fa-solid') . ' fa-' . $new; }
            // 未在映射表内：若属品牌白名单则 brands，否则 solid
            return (isset($brandOldNamesSet[$iconName]) ? 'fa-brands' : 'fa-solid') . ' fa-' . $iconName;
        }

        // 3) 裸名（home / weibo / github / user）
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/i', $raw)) {
            $map = _fa4_to_fa6_renamed();
            if (isset($map[$raw])) { $new = $map[$raw]; return (isset($brandOldNamesSet[$raw]) ? 'fa-brands' : 'fa-solid') . ' fa-' . $new; }
            return (isset($brandOldNamesSet[$raw]) ? 'fa-brands' : 'fa-solid') . ' fa-' . $raw;
        }

        // 4) 其它非 FA 图标（iconfont-* 等）原样返回
        return $raw;
    }
}

if (!function_exists('captcha_config')) {
    /**
     * 验证码配置（从 settings 表读取，带默认值）。
     * 同时负责建表自愈：首次调用确保 captcha_tokens / captcha_logs 存在。
     */
    function captcha_ensure_tables()
    {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $pdo = Database::pdo();
            $pdo->exec("CREATE TABLE IF NOT EXISTS `captcha_tokens` (
                `id` BIGINT NOT NULL AUTO_INCREMENT,
                `token` VARCHAR(64) NOT NULL,
                `x` INT NOT NULL DEFAULT 0,
                `y` INT NOT NULL DEFAULT 0,
                `scene` VARCHAR(20) NOT NULL DEFAULT '',
                `used` TINYINT NOT NULL DEFAULT 0,
                `verified` TINYINT NOT NULL DEFAULT 0,
                `expires_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_token` (`token`),
                KEY `idx_scene_time` (`scene`,`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `captcha_logs` (
                `id` BIGINT NOT NULL AUTO_INCREMENT,
                `ip` VARCHAR(64) NOT NULL DEFAULT '',
                `scene` VARCHAR(20) NOT NULL DEFAULT '',
                `result` VARCHAR(20) NOT NULL DEFAULT '',
                `detail` VARCHAR(255) DEFAULT NULL,
                `user_id` BIGINT DEFAULT NULL,
                `created_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_ip_scene` (`ip`,`scene`,`created_at`),
                KEY `idx_scene_time` (`scene`,`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {
            // 建表失败不致命，后续校验会返回错误提示
        }
    }

    /**
     * 读取验证码配置（总开关 + 类型 + 场景 + 滑块难度）。
     * 频率限制 / 失败锁定 / 攻击日志相关字段已删除（2026-08-27 用户反馈）。
     */
    function captcha_config()
    {
        $scenes = json_decode((string) setting('captcha_scenes', '{}'), true);
        if (!is_array($scenes)) $scenes = [];
        return [
            'enabled' => setting('captcha_enabled', '0') == '1',
            'type'    => setting('captcha_type', 'slider'),
            'scenes'  => $scenes,
        ];
    }

    /**
     * 某场景是否在后台开启了验证码（总开关 + 场景开关同时满足）。
     */
    function captcha_scene_on($scene)
    {
        $cfg = captcha_config();
        return !empty($cfg['enabled']) && !empty($cfg['scenes'][$scene]);
    }

    /**
     * 验证码有效期（秒）。
     * 默认 60 秒（2026-08-31 由 300 秒下调：前端占位条有倒计时，60 秒足够拖动+提交，
     * 同时压缩了 token 可被重放的窗口）。需要调整时可在 settings 表加 captcha_ttl 覆盖，
     * 不必改代码。非法值（0/负数/非数字）一律回落到 60。
     */
    function captcha_ttl()
    {
        $ttl = (int) setting('captcha_ttl', 60);
        return $ttl > 0 ? $ttl : 60;
    }

    /**
     * 生成滑块拼图验证码。
     * 返回 bg/piece 的 base64 PNG、缺口纵坐标 y、画布尺寸与拼图尺寸。
     * 正确横坐标 x 仅存服务端（不返回前端），前端只能"肉眼对齐"，防脚本直接读取答案。
     * expires_in 会一并返回给前端，供占位条做倒计时与到期自动重置。
     */
    function captcha_create($scene = '')
    {
        captcha_ensure_tables();
        if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
            throw new Exception('服务器未启用 GD 扩展，无法生成滑块验证码');
        }
        $w = 300; $h = 150; $pw = 42; $ph = 42;
        $minX = 40; $maxX = $w - $pw - 10;
        $x = random_int($minX, $maxX);
        $minY = 10; $maxY = $h - $ph - 10;
        $y = random_int($minY, $maxY);

        $bg = imagecreatetruecolor($w, $h);
        $base = imagecolorallocate($bg, rand(190, 240), rand(190, 240), rand(190, 240));
        imagefill($bg, 0, 0, $base);
        for ($i = 0; $i < 14; $i++) {
            $c = imagecolorallocate($bg, rand(90, 230), rand(90, 230), rand(90, 230));
            if (rand(0, 1)) {
                imagefilledellipse($bg, rand(0, $w), rand(0, $h), rand(18, 60), rand(18, 60), $c);
            } else {
                imagefilledrectangle($bg, rand(0, $w), rand(0, $h), rand(0, $w), rand(0, $h), $c);
            }
        }
        for ($i = 0; $i < 6; $i++) {
            $c = imagecolorallocate($bg, rand(0, 170), rand(0, 170), rand(0, 170));
            imageline($bg, rand(0, $w), rand(0, $h), rand(0, $w), rand(0, $h), $c);
        }

        // 拼图块：从背景扣出 (x,y) 区域
        $piece = imagecreatetruecolor($pw, $ph);
        imagealphablending($piece, false);
        imagesavealpha($piece, true);
        $trans = imagecolorallocatealpha($piece, 0, 0, 0, 127);
        imagefill($piece, 0, 0, $trans);
        imagecopy($piece, $bg, 0, 0, $x, $y, $pw, $ph);

        // 背景上的"缺口"：半透明遮罩 + 白色边框，提示落点
        $gap = imagecolorallocatealpha($bg, 0, 0, 0, 80);
        imagefilledrectangle($bg, $x, $y, $x + $pw, $y + $ph, $gap);
        $border = imagecolorallocate($bg, 255, 255, 255);
        imagerectangle($bg, $x, $y, $x + $pw, $y + $ph, $border);

        ob_start(); imagepng($bg); $bgPng = ob_get_clean();
        ob_start(); imagepng($piece); $piecePng = ob_get_clean();
        imagedestroy($bg); imagedestroy($piece);

        $token = bin2hex(random_bytes(16));
        $ttl = captcha_ttl();
        $expires = date('Y-m-d H:i:s', time() + $ttl);
        Model::table('captcha_tokens')->insert([
            'token' => $token, 'x' => $x, 'y' => $y, 'scene' => $scene,
            'used' => 0, 'verified' => 0, 'expires_at' => $expires, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        return [
            'token' => $token,
            'bg'    => 'data:image/png;base64,' . base64_encode($bgPng),
            'piece'  => 'data:image/png;base64,' . base64_encode($piecePng),
            'y'     => $y, 'w' => $w, 'h' => $h, 'pw' => $pw, 'ph' => $ph, 'expires_in' => $ttl,
        ];
    }

    /**
     * 校验拼图位置。
     * @param bool $consume  true=校验成功则标记 token 已用（业务 action 调用）；false=只读校验（前端预检，不消耗）。
     */
    function captcha_verify_token($token, $x, $consume = true)
    {
        captcha_ensure_tables();
        if (!$token) return [false, '请完成滑块验证'];
        $row = Model::table('captcha_tokens')->where('token', $token)->first();
        if (!$row) return [false, '验证码已失效，请重试'];
        if ((int) $row['used'] === 1) return [false, '验证码已被使用，请刷新重试'];
        if (strtotime($row['expires_at']) < time()) return [false, '验证码已过期，请重试'];
        $dx = abs((int) $x - (int) $row['x']);
        if ($dx > 6) return [false, '拼图未对齐，请拖动滑块完成验证'];
        if ($consume) {
            Model::table('captcha_tokens')->where('id', $row['id'])->update(['used' => 1, 'verified' => 1]);
        }
        return [true, ''];
    }

    /**
     * 业务 action 内调用：读取本次请求的 captcha_token / captcha_x 并完成校验。
     * 场景未开启 → 直接通过。返回 [ok, msg]，由调用方决定 $this->error($msg)。
     */
    function captcha_guard($scene)
    {
        $cfg = captcha_config();
        if (empty($cfg['enabled']) || empty($cfg['scenes'][$scene])) return [true, ''];
        $token = input('captcha_token');
        $x = input('captcha_x');
        if ($token === null || $token === '' || $x === null || $x === '') {
            return [false, '请完成滑块验证'];
        }
        list($ok, $msg) = captcha_verify_token($token, $x, true);
        return [$ok, $msg];
    }

    /**
     * 大数字优雅显示，避免侧边栏/个人主页等窄位溢出。
     * 规则：
     *   - < 10,000        原样输出（9999 / 532）
     *   - < 1,000,000     X.X K 一位小数，floor 取值（不四舍五入）
     *                     10000 → 10.0K；11999 → 11.9K；12000 → 12.0K；
     *                     999999 → 999.9K
     *   - >= 1,000,000    X.X M 一位小数，floor 取值（不四舍五入）
     *                     1000000 → 1.0M；1199999 → 11.9M；1200000 → 12.0M
     * 小数末位零统一保留为 .0K / .0M，便于扫描对齐；负数和 0 都按 0 显示。
     *
     * @param int|float $n
     * @param int $decimals 小数位数（默认 1）
     * @return string
     */
    function format_count($n, $decimals = 1)
    {
        $n = (int)$n;
        if ($n < 10000) return (string)$n;
        if ($n < 1000000) {
            $unit = 1000;
            $suffix = 'K';
            // 12123 → 12123 / 10 = 1212 → floor = 1212 → 12.1
            // 11999 → 11999 / 10 = 1199 → 11.9
            // 10000 → 1000 → 10.0
            $v = floor($n / $unit * pow(10, $decimals)) / pow(10, $decimals);
            return number_format($v, $decimals) . $suffix;
        }
        $unit = 1000000;
        $suffix = 'M';
        // 1199999 → 11.9999 → floor(11.9999*10) = 119 → 11.9
        // 1200000 → 12.0
        $v = floor($n / $unit * pow(10, $decimals)) / pow(10, $decimals);
        return number_format($v, $decimals) . $suffix;
    }
}

/**
 * 帖子列表项是否带高亮(pinned) class。
 * 规则：
 *  - 全局置顶(pin_scope=2)：任意页面都高亮（品牌红）
 *  - 本版置顶(pin_scope=1)：仅 $highlightLocal=true 的上下文（首页 / 本版页面）高亮；搜索 / 个人页等不高亮
 *  - 普通帖 / 自助置顶：不高亮
 * 加粗由 CSS 控制：仅本版页面列表容器带 .is-board 时加粗，首页高亮但不加粗。
 * @param array $post 帖子行
 * @param bool $highlightLocal 是否高亮本版置顶（首页 / 本版页面传 true；搜索 / 个人页默认 false）
 * @return string 'pinned' 或 ''
 */
function post_pinned_class($post, $highlightLocal = false) {
    $scope = (int)($post['pin_scope'] ?? 0);
    if ($scope === 2) return 'pinned pinned-global';            // 全局置顶：任意页面高亮 + 加粗
    if ($scope === 1 && $highlightLocal) return 'pinned pinned-section'; // 本版置顶：仅首页/本版高亮；仅本版页面加粗
    return '';
}
