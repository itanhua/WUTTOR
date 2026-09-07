<?php
/**
 * 论坛 - 单入口文件
 * 所有请求经过此处分发到对应控制器
 */

// 定义常量
define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');
define('CORE_PATH', ROOT_PATH . '/core');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('TEMPLATE_PATH', ROOT_PATH . '/templates');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// 错误报告（生产环境改为 0）
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php_error.log');

// 时区
date_default_timezone_set('Asia/Shanghai');

// 会话
session_start();

// 加载核心文件。
// 重要：opcache_invalidate 必须放在 require_once 之前才有意义——
//   PHP 函数一旦被 PHP-FPM worker 进程 require_once 定义进符号表，
//   后续 invalidate 只是让下次 _新_ 的 require 拿到新字节码，对已定义函数无效。
//   所以改动 helpers.php 后，**必须重启 PHP-FPM** 才能让新函数（slugify 等）真正生效。
// 这里额外做一层"文件被修改则强制 opcache_reset"——配合 PHP-FPM 短 keepalive，
// 大多数请求会在 worker 自然轮换时拿到新代码，免去手动重启。
$helpersFile = CORE_PATH . '/helpers.php';
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate($helpersFile, true);
    // 用 storage/cache 里的 mtime 持久化记录，跟 PHP-FPM 进程解耦；
    // 文件 mtime 变化时主动 reset 整个 OPcache，搭配 PHP-FPM worker 自然轮换可让新函数生效。
    $mtimeCacheFile = STORAGE_PATH . '/cache/helpers_mtime.txt';
    $cur = @filemtime($helpersFile);
    if ($cur) {
        $prev = is_file($mtimeCacheFile) ? (int)@file_get_contents($mtimeCacheFile) : 0;
        if ($prev > 0 && $prev !== $cur && function_exists('opcache_reset')) {
            @opcache_reset();
        }
        if (!is_dir(STORAGE_PATH . '/cache')) {
            @mkdir(STORAGE_PATH . '/cache', 0755, true);
        }
        @file_put_contents($mtimeCacheFile, (string)$cur);
    }
}
require_once $helpersFile;
// 健康检查：helpers.php 改动后，新加的函数（如 render_emoji）在 PHP-FPM 老 worker 进程里
// 仍可能缺失——因为 require_once 见到文件已 include 会跳过整个文件，不会重新执行函数声明。
// 这里快速检测关键函数，缺失时立即给出明确的修复指引，避免用户看到一个莫名其妙的
// "Call to undefined function" 500 错误。
$_missingHelpers = [];
foreach (['render_emoji'] as $_fn) {
    if (!function_exists($_fn)) $_missingHelpers[] = $_fn;
}
if ($_missingHelpers) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "服务器配置未更新：helpers.php 新增的函数尚未生效\n"
       . "缺失函数：" . implode(', ', $_missingHelpers) . "\n\n"
       . "原因：PHP-FPM worker 进程已加载过旧版 helpers.php，require_once 会跳过整个文件，\n"
       . "      OPcache reset / invalidate 都不能让旧 worker 进程加载新函数定义。\n\n"
       . "修复方法（任选其一）：\n"
       . "  1) 宝塔面板 → 软件商店 → PHP → 选中对应版本 →『重启』\n"
       . "  2) 命令行：systemctl reload php-fpm  或  kill -USR2 $(pidof php-fpm | awk '{print $1}')\n"
       . "  3) 直接 kill 全部 PHP-FPM worker 让 master 重新拉起：\n"
       . "       pkill -USR2 php-fpm\n";
    exit;
}
require_once CORE_PATH . '/Database.php';
require_once CORE_PATH . '/Config.php';
require_once CORE_PATH . '/Response.php';
require_once CORE_PATH . '/Mailer.php';
require_once CORE_PATH . '/Auth.php';
// 当 core 核心文件被修改后（如新增方法），主动让 OPcache 放弃旧字节码，避免线上/重启服务前
// 仍加载未更新的类、报 "Call to undefined method"。代价极小（每请求只检查 1 个文件）。
if (function_exists('opcache_invalidate')) opcache_invalidate(CORE_PATH . '/Auth.php', true);
require_once CORE_PATH . '/Middleware.php';
require_once CORE_PATH . '/Router.php';
// Model.php：加 opcache_invalidate 兜底，避免 PHP-FPM worker 复用旧字节码（旧 Model::insert 不
// 自动加反引号，与新版 quoteIdent 行为不符；FPM 自然轮换时可让新 worker 拿到新行为）。
if (function_exists('opcache_invalidate')) opcache_invalidate(CORE_PATH . '/Model.php', true);
require_once CORE_PATH . '/Model.php';
require_once CORE_PATH . '/Controller.php';
require_once CORE_PATH . '/View.php';
require_once CORE_PATH . '/PointService.php';
// 充值服务（卡密/活动/订单/支付配置共用）。新加的核心文件单独 require，避开
// autoloader 只认 *Controller 后缀的规则；同步 invalidate 防止 PHP-FPM 复用旧字节码。
require_once CORE_PATH . '/RechargeService.php';
if (function_exists('opcache_invalidate')) opcache_invalidate(CORE_PATH . '/RechargeService.php', true);
// 抽奖主题积分随机分配算法（2026-09-01）。同 RechargeService 规则单独 require + invalidate。
require_once CORE_PATH . '/LotteryService.php';
if (function_exists('opcache_invalidate')) opcache_invalidate(CORE_PATH . '/LotteryService.php', true);

// 控制器自动加载：允许控制器之间互相调用静态方法（如 AuthController 调用 EmailController）
spl_autoload_register(function ($class) {
    if (substr($class, -10) === 'Controller') {
        $file = APP_PATH . '/Controllers/' . $class . '.php';
        if (file_exists($file)) require_once $file;
    }
});

// 安装检测：未安装则跳转安装页
if (!file_exists(CONFIG_PATH . '/database.php') || !file_exists(ROOT_PATH . '/install/install.lock')) {
    // 如果当前不在安装目录，跳转
    $script = basename($_SERVER['SCRIPT_NAME']);
    if ($script !== 'install.php' && strpos($_SERVER['REQUEST_URI'] ?? '', '/install/') === false) {
        header('Location: install/');
        exit;
    }
}

// 加载配置
Config::load('database');
Config::load('site');

// 初始化数据库连接
Database::init();

// 初始化认证
Auth::init();

// ========== 伪静态 URL 处理（首页不显示 index.php） ==========
// 检测是否为旧 URL（带 /index.php 或 ?r=）：
//   - 旧 URL 形态：/index.php?r=...  或 /index.php/post/show/123  或 /?r=...
//   - pretty URL 形态：/post/123、/c/5、/u/8、/
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$isOldStyle = (basename($reqPath) === 'index.php') || (strpos($queryString, 'r=') !== false);

if ($isOldStyle && permalink_structure() !== 'default') {
    $routeParam = isset($_GET['r']) ? (string)$_GET['r'] : '';
    if (in_array($routeParam, ['post/show', 'home/index', 'user/profile'], true)) {
        $pretty = _url_make_pretty($routeParam, $_GET, permalink_structure());
        if ($pretty !== null) {
            // 清理 query string：去掉 r= 和已经编进路径的 id/cat
            $path = is_array($pretty) ? $pretty['path'] : $pretty;
            $newQs = $queryString;
            $newQs = preg_replace('/(^|&)r=[^&]*/', '', $newQs);
            if ($routeParam === 'post/show' || $routeParam === 'user/profile') {
                $newQs = preg_replace('/(^|&)id=[^&]*/', '', $newQs);
            } elseif ($routeParam === 'home/index') {
                $newQs = preg_replace('/(^|&)cat=\d+/', '', $newQs);
            }
            $newQs = trim($newQs, '&');
            $redirect = $path;
            if ($newQs !== '') $redirect .= '?' . $newQs;
            header('Location: ' . $redirect, true, 301);
            exit;
        }
    }
    // /index.php（无 r=）→ 跳到 /
    if ($routeParam === '' && in_array(basename($reqPath), ['index.php'], true)) {
        header('Location: /', true, 301);
        exit;
    }
}

// 解析 pretty URL：当 r= 未传时尝试从 REQUEST_URI 反推
if (!isset($_GET['r']) || $_GET['r'] === '') {
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $parsePath = $reqPath;
    if ($basePath !== '' && strpos($parsePath, $basePath) === 0) {
        $parsePath = substr($parsePath, strlen($basePath));
    }
    $parsed = _parse_pretty_url($parsePath);
    if ($parsed !== null) {
        $_GET['r'] = $parsed['route'];
        foreach ($parsed['params'] as $k => $v) {
            if (!isset($_GET[$k])) $_GET[$k] = $v;
        }
    }
}

// 获取路由
$route = $_GET['r'] ?? ($_SERVER['PATH_INFO'] ?? '');
$route = trim($route, '/');

// 默认路由
if ($route === '') {
    $route = 'home/index';
}

// 解析路由
$parts = explode('/', $route);
$controllerName = ucfirst($parts[0] ?? 'home') . 'Controller';
$action = $parts[1] ?? 'index';
$params = array_slice($parts, 2);

// 防御：action 名称只允许 [a-zA-Z0-9_]（避免 URL 里残留 ?、&、空格等导致 "方法 xxx 不存在" 误报，
// 同时挡住一些弱扫脚本）。不合法直接 404。
if ($action !== '' && !preg_match('/^[a-zA-Z0-9_]+$/', $action)) {
    http_response_code(404);
    if (isAjax()) {
        Response::json(['code' => 404, 'message' => '接口不存在'], 404);
    } else {
        View::render('errors/404', [], 404);
    }
    exit;
}

// 控制器文件路径
$controllerFile = APP_PATH . '/Controllers/' . $controllerName . '.php';

if (!file_exists($controllerFile)) {
    http_response_code(404);
    if (isAjax()) {
        Response::json(['code' => 404, 'message' => '接口不存在'], 404);
    } else {
        View::render('errors/404', [], 404);
    }
    exit;
}

// require_once 之前先 invalidate 该 controller 的 OPcache 缓存：
// 业务 controller 经常被修改（新增导出/导入等方法），require_once 见到类已定义会直接跳过，
// 导致 ReflectionClass 拿到的是 PHP-FPM 内存里旧版的类、报"方法 xxx 不存在"。
// 仅对当前请求的 controller 文件做 invalidate，性能开销可忽略。
if (function_exists('opcache_invalidate')) opcache_invalidate($controllerFile, true);
// 防 OPcache 卡住改了的 view 模板：浏览器看到 <script>...</script> 里的 JS 注释中包含
// `</script>` 字面量时会提前结束脚本（HTML 解析器只看 </script> 不管 JS 上下文），导致整个
// inline JS 块解析失败。一次 invalidate + 后续的 require/include 仍走 PHP 内核，但要确保
// 模板文件改动后 OPcache 下次读到的不是旧字节码。
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate(APP_PATH . '/../templates/post/show.php', true);
}
require_once $controllerFile;

// 反射执行
try {
    $reflection = new ReflectionClass($controllerName);
    if (!$reflection->hasMethod($action)) {
        throw new Exception("方法 {$action} 不存在");
    }
    $controller = $reflection->newInstance();
    $method = $reflection->getMethod($action);

    // 检查中间件注解（通过 controller 的 $middleware 属性）
    $controller->runMiddleware($action);

    // 调用方法
    $method->invokeArgs($controller, $params);
} catch (Throwable $e) {
    // 同时捕获 Exception 与 Error（含 TypeError 等），避免白屏无日志
    @file_put_contents(
        STORAGE_PATH . '/logs/app_error.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $route . ' :: ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString() . "\n",
        FILE_APPEND
    );
    if (isAjax()) {
        Response::json(['code' => 500, 'message' => $e->getMessage()], 500);
    } else {
        http_response_code(500);
        echo '<h1>服务器错误</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}
