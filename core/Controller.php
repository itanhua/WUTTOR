<?php
/**
 * 控制器基类
 */
class Controller
{
    // 中间件配置：['action' => ['auth', 'admin'], 'only' => [...], 'except' => [...]]
    protected $middleware = [];

    /**
     * 是否在 layouts/main.php 中隐藏 footer。
     * 大部分内页（板块页、帖子详情、个人主页、消息通知等）不需要页脚时，
     * 子类 controller 设 $hideFooter = true 即可；首页 HomeController 默认 false 显示。
     * 该值会自动注入到 view data（key=__hideFooter），main layout 据此判断渲染。
     */
    protected $hideFooter = true;

    /**
     * 执行中间件
     */
    public function runMiddleware($action)
    {
        foreach ($this->middleware as $config) {
            $only = $config['only'] ?? [];
            $except = $config['except'] ?? [];
            if (!empty($only) && !in_array($action, $only)) continue;
            if (in_array($action, $except)) continue;
            $mw = $config['middleware'] ?? [];
            if (is_string($mw)) $mw = [$mw];
            foreach ($mw as $m) {
                $this->applyMiddleware($m);
            }
        }
    }

    protected function applyMiddleware($name)
    {
        switch ($name) {
            case 'auth':
                if (!Auth::check()) {
                    if (isAjax()) {
                        Response::error('请先登录', 401, 401);
                    }
                    // 跳登录页时带上 redirect=当前页，登录成功后回跳
                    $back = $_SERVER['REQUEST_URI'] ?? '';
                    $loginUrl = url('auth/login');
                    if ($back !== ''
                        && strpos($back, '/auth/login') === false
                        && strpos($back, '/auth/doLogin') === false
                        && strpos($back, '/auth/doRegister') === false) {
                        $sep = (strpos($loginUrl, '?') === false) ? '?' : '&';
                        $loginUrl .= $sep . 'redirect=' . urlencode($back);
                    }
                    redirect($loginUrl);
                }
                break;
            case 'banned':
                // 禁言检查：封禁用户（超级管理员/站长除外）禁止执行写操作
                if (Auth::check() && Auth::role() !== 'super_admin' && Auth::role() !== 'webmaster' && (!isset(Auth::user()['status']) || Auth::user()['status'] != 1)) {
                    if (isAjax()) {
                        Response::error('账号已被禁言', 403, 403);
                    }
                    http_response_code(403);
                    View::render('errors/403', ['message' => '账号已被禁言，仅可浏览'], 403);
                    exit;
                }
                break;
            case 'guest':
                if (Auth::check()) {
                    redirect(url('home/index'));
                }
                break;
            case 'admin':
                // admin 及以上：内置 admin/super_admin/webmaster，或自定义角色在矩阵中授予了「用户管理」。
                // 改用 is_admin() 而非硬编码 roleLevel 比较，使后台新建的自定义管理角色也能进入后台。
                if (!is_admin()) {
                    if (isAjax()) Response::error('无权限访问', 403, 403);
                    redirect(url('home/index'));
                }
                break;
            case 'super_admin':
                // super_admin 及以上（super_admin / webmaster）均可通过
                if (!is_high_admin()) {
                    if (isAjax()) Response::error('无权限访问', 403, 403);
                    redirect(url('home/index'));
                }
                break;
            case 'moderator':
                if (!is_moderator()) {
                    if (isAjax()) Response::error('无权限访问', 403, 403);
                    redirect(url('home/index'));
                }
                break;
            case 'csrf':
                csrf_check();
                break;
        }
    }

    /**
     * 视图渲染
     */
    protected function view($template, $data = [])
    {
        // 注入页面级标志，layouts/main.php 据此决定渲染 footer / 侧栏 / 其他全局块
        $data['__hideFooter']  = (bool)$this->hideFooter;
        $data['__hideSidebar'] = !empty($data['__hideSidebar']);
        View::render($template, $data);
    }

    protected function json($data, $status = 200)
    {
        Response::json($data, $status);
    }

    protected function success($data = null, $message = '操作成功')
    {
        Response::success($data, $message);
    }

    protected function error($message = '操作失败', $code = 1, $status = 400)
    {
        Response::error($message, $code, $status);
    }
}
