<?php
/**
 * 响应类
 */
class Response
{
    public static function json($data, $status = 200)
    {
        // 关闭错误显示，避免 PHP warning/notices 污染 JSON 响应体
        @ini_set('display_errors', '0');
        @ini_set('html_errors', '0');

        // 丢弃此前所有输出缓冲（包括 PHP warning、session notice、include 流等杂质）
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // 编码：失败则降级为错误响应
        $body = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            $body = json_encode(['code' => 500, 'message' => '响应编码失败：' . json_last_error_msg()], JSON_UNESCAPED_UNICODE);
        }

        // 防御性二次清洗：万一 echo 前后有 BOM/NUL/控制字符混入，强制剥离
        $body = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $body);
        if (substr($body, 0, 3) === "\xEF\xBB\xBF") {
            $body = substr($body, 3);
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $body;
        exit;
    }

    public static function success($data = null, $message = '操作成功')
    {
        self::json(['code' => 0, 'message' => $message, 'data' => $data]);
    }

    public static function error($message = '操作失败', $code = 1, $status = 400)
    {
        self::json(['code' => $code, 'message' => $message], $status);
    }
}
