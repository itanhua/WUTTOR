<?php
/**
 * 滑块拼图验证码 —— 公开预检接口
 *   captcha/create ：生成拼图（背景+拼图块 PNG、缺口信息），返回 token；正确横坐标仅存服务端
 *   captcha/check  ：非消耗式校验（只读，不改 used），供前端拖动结束后即时反馈；真正消耗在业务 action 的 captcha_guard
 * 两个接口都不走 csrf 中间件（属于公开预检；真正的写操作仍由各自 action 的 csrf + captcha_guard 防护）。
 */
class CaptchaController extends Controller
{
    // 允许的场景（与后台 settings 的 captcha_scenes 键一致）
    private $allowedScenes = ['register', 'login', 'post', 'comment'];

    public function create()
    {
        $scene = trim((string) input('scene', ''));
        if (!in_array($scene, $this->allowedScenes, true)) {
            $scene = 'global';
        }

        // 频率限制 / 失败锁定 已移除（2026-08-27 用户反馈）——直接生成拼图。

        try {
            $data = captcha_create($scene);
            $this->success($data);
        } catch (Throwable $e) {
            $this->error('验证码生成失败：' . $e->getMessage());
        }
    }

    public function check()
    {
        $scene = trim((string) input('scene', ''));
        $token = trim((string) input('token', ''));
        $x = input('x');

        // 非消耗式校验：只判断对齐，不改 used（真实消耗在业务 action 的 captcha_guard）
        list($ok, $msg) = captcha_verify_token($token, $x, false);
        if ($ok) {
            $this->success(['ok' => true]);
        } else {
            $this->error($msg);
        }
    }
}
