<?php
/**
 * 上传控制器 - 处理头像、帖子图片、认证图片上传
 */
class UploadController extends Controller
{
    protected $middleware = [
        ['middleware' => 'auth'],
        ['middleware' => 'banned'],
    ];

    protected $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    protected $maxSize = 5242880; // 5MB

    /**
     * 通用图片上传
     * type: avatar / post / certification
     */
    public function image()
    {
        $type = input('type', 'post');
        // 新增 'qrcode'：页脚社交媒体二维码，仅站长可传（绕过普通图片上传权限）
        // 'recharge_qr'：充值中心收款码，仅站长
        // 'emoji'：自定义表情包图片（SVG/WebP/PNG ≤50KB），仅站长
        $allowedTypes = ['avatar', 'post', 'certification', 'qrcode', 'recharge_qr', 'emoji'];
        if (!in_array($type, $allowedTypes)) $this->error('无效的上传类型');

        if (empty($_FILES['file'])) $this->error('请选择文件');
        $file = $_FILES['file']; // 必须在 empty/error 检查前赋值，PHP 8 未定义变量会抛 TypeError

        // 权限分流：qrcode / recharge_qr / emoji 仅站长；其余走通用图片上传权限
        if (in_array($type, ['qrcode', 'recharge_qr', 'emoji'], true)) {
            if (!is_webmaster()) $this->error('仅站长可上传此类型图片');
        } else {
            if (!can_upload_image()) $this->error('当前角色无权上传图片');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) $this->error('上传失败，错误码：' . ($file['error'] ?: '未知'));

        // emoji 自定义表情：限 SVG/WebP/PNG、≤50KB；按短代码 code 命名
        if ($type === 'emoji') {
            $emojiMax = 50 * 1024;
            if ($file['size'] > $emojiMax) $this->error('表情文件不能超过 50KB');
            $emojiAllowed = ['image/svg+xml', 'image/webp', 'image/png'];
            if (!in_array(strtolower($file['type']), $emojiAllowed, true)) $this->error('表情仅支持 SVG / WEBP / PNG 格式');

            $code = trim((string)input('code', ''));
            if ($code === '' || !preg_match('/^[a-z0-9_]{1,32}$/', $code)) {
                $this->error('短代码格式错误（仅小写字母/数字/下划线，1-32 字符）');
            }

            $extMap = ['svg' => 'svg', 'webp' => 'webp', 'png' => 'png'];
            $extLower = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $ext = $extMap[$extLower] ?? 'png';

            $dir = PUBLIC_PATH . '/uploads/emoji/' . date('Ym');
            if (!is_dir($dir)) @mkdir($dir, 0755, true);

            // 同 code 重传时加随机后缀防重名覆盖
            $filename = $code . '.' . $ext;
            if (file_exists($dir . '/' . $filename)) {
                $filename = $code . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            }
            $savePath = $dir . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $savePath)) {
                $this->error('文件保存失败，请检查目录权限');
            }
            $relativePath = 'uploads/emoji/' . date('Ym') . '/' . $filename;
            $this->success(['url' => $relativePath, 'code' => $code, 'ext' => $ext], '上传成功');
        }

        if ($file['size'] > $this->maxSize) $this->error('文件不能超过 5MB');
        if (!in_array($file['type'], $this->allowedTypes)) $this->error('仅支持 JPG/PNG/GIF/WEBP 格式');

        $ext = $this->getExtension($file['name']);
        $dir = PUBLIC_PATH . '/uploads/' . $type . '/' . date('Ym');
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $savePath = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $savePath)) {
            $this->error('文件保存失败，请检查目录权限');
        }

        $relativePath = 'uploads/' . $type . '/' . date('Ym') . '/' . $filename;

        $this->success(['url' => $relativePath], '上传成功');
    }

    /**
     * 头像上传（更新当前用户头像）
     */
    public function avatar()
    {
        $_POST['type'] = 'avatar';
        // 复用 image 上传逻辑
        $this->image();

        // image() 已 exit，这里不会执行
    }

    protected function getExtension($filename)
    {
        $parts = pathinfo($filename);
        $ext = strtolower($parts['extension'] ?? 'jpg');
        $map = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'gif' => 'gif', 'webp' => 'webp'];
        return $map[$ext] ?? 'jpg';
    }

    /**
     * 通用附件上传（受角色权限控制）
     * 返回 {url, name, size}
     */
    public function attachment()
    {
        if (!can_upload_attach()) $this->error('当前角色无权上传附件');

        if (empty($_FILES['file'])) $this->error('请选择文件');
        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) $this->error('上传失败，错误码：' . $file['error']);

        $maxSize = 20 * 1024 * 1024; // 20MB
        if ($file['size'] > $maxSize) $this->error('附件不能超过 20MB');

        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', '7z', 'mp3', 'mp4', 'wav'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) $this->error('不支持的文件类型：.' . $ext);

        $dir = PUBLIC_PATH . '/uploads/attachments/' . date('Ym');
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $savePath = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $savePath)) {
            $this->error('文件保存失败，请检查目录权限');
        }

        $relativePath = 'uploads/attachments/' . date('Ym') . '/' . $filename;
        $this->success([
            'url' => $relativePath,
            'name' => $file['name'],
            'size' => $file['size'],
        ], '上传成功');
    }
}
