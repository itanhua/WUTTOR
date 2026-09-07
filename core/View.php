<?php
/**
 * 视图渲染类
 */
class View
{
    protected static $layout = 'main';

    public static function setLayout($layout)
    {
        self::$layout = $layout;
    }

    public static function render($template, $data = [], $status = 200)
    {
        http_response_code($status);
        $file = TEMPLATE_PATH . '/' . $template . '.php';
        if (!file_exists($file)) {
            $file = TEMPLATE_PATH . '/' . $template . '.html';
        }
        if (!file_exists($file)) {
            echo '<h1>模板不存在</h1><p>' . e($template) . '</p>';
            return;
        }

        // 提取数据为变量
        extract($data);

        // 站点配置
        $site = Config::get('site', []);
        $currentUser = Auth::user();

        // 开启输出缓冲
        ob_start();
        include $file;
        $content = ob_get_clean();

        // 如果是 AJAX 或显式指定无布局，直接输出
        if (self::$layout === null || ($data['__raw'] ?? false)) {
            echo $content;
            return;
        }

        // 加载布局
        $layoutFile = TEMPLATE_PATH . '/layouts/' . self::$layout . '.php';
        if (file_exists($layoutFile)) {
            include $layoutFile;
        } else {
            echo $content;
        }
    }

    public static function partial($template, $data = [])
    {
        $file = TEMPLATE_PATH . '/' . $template . '.php';
        if (file_exists($file)) {
            extract($data);
            include $file;
        }
    }
}
