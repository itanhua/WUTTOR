<?php
/**
 * 论坛 - 安装程序
 * 安装完成后生成 install.lock 文件，禁止再次访问
 */

session_start();
date_default_timezone_set('Asia/Shanghai');

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INSTALL_PATH', __DIR__);
define('LOCK_FILE', __DIR__ . '/install.lock');

// 已安装则禁止访问
if (file_exists(LOCK_FILE)) {
    $step = $_GET['step'] ?? '';
    if ($step !== 'done') {
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>已安装</title>';
        echo '<style>body{font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f5f5f5;margin:0;padding:40px;color:#333;}.box{max-width:520px;margin:40px auto;background:#fff;padding:40px;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.06);text-align:center;}h1{font-size:20px;margin:0 0 12px;}p{color:#888;margin:0 0 20px;line-height:1.7;}a{display:inline-block;padding:10px 28px;background:#ea6f5a;color:#fff;text-decoration:none;border-radius:4px;font-size:14px;}a:hover{background:#d9604e;}</style>';
        echo '</head><body><div class="box"><h1>论坛已安装</h1>';
        echo '<p>安装文件已被锁定，如需重新安装请先手动删除 install/install.lock 文件。</p>';
        echo '<a href="../index.php">访问首页</a></div></body></html>';
        exit;
    }
}

$step = $_GET['step'] ?? '1';

// 错误处理
error_reporting(E_ALL);
ini_set('display_errors', '1');

/**
 * 简单页面渲染
 */
function render($title, $content, $step = null)
{
    $steps = ['环境检查' => 1, '参数配置' => 2, '执行安装' => 3, '安装完成' => 4];
    $nav = '<div class="steps">';
    $i = 1;
    foreach ($steps as $name => $n) {
        $cls = '';
        if ($step !== null && $step == $i) $cls = 'active';
        elseif ($step !== null && $i < $step) $cls = 'done';
        $nav .= '<span class="step ' . $cls . '"><span class="step-num">' . $i . '</span><span class="step-name">' . $name . '</span></span>';
        $i++;
    }
    $nav .= '</div>';

    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . $title . ' - 论坛安装</title>';
    echo '<link rel="stylesheet" href="style.css">';
    echo '</head><body><div class="container">';
    echo '<div class="header"><h1>论坛安装向导</h1><p>欢迎使用，请按步骤完成安装配置</p></div>';
    echo $nav;
    echo '<div class="content">' . $content . '</div>';
    echo '<div class="footer">论坛 · PHP + MySQL</div>';
    echo '</div></body></html>';
}

switch ($step) {
    case '1':
        step1();
        break;
    case '2':
        step2();
        break;
    case '3':
        step3();
        break;
    case '4':
    case 'done':
        step4();
        break;
    default:
        step1();
}

/**
 * 步骤1：环境检查
 */
function step1()
{
    $checks = [];

    // PHP 版本
    $phpOk = version_compare(PHP_VERSION, '7.2.0', '>=');
    $checks[] = ['PHP 版本 >= 7.2', PHP_VERSION, $phpOk];

    // PDO 扩展
    $pdoOk = extension_loaded('pdo_mysql');
    $checks[] = ['PDO MySQL 扩展', $pdoOk ? '已安装' : '未安装', $pdoOk];

    // mbstring
    $mbOk = extension_loaded('mbstring');
    $checks[] = ['mbstring 扩展', $mbOk ? '已安装' : '未安装', $mbOk];

    // GD 库
    $gdOk = extension_loaded('gd');
    $checks[] = ['GD 扩展（图像处理）', $gdOk ? '已安装' : '未安装', $gdOk];

    // 配置目录可写
    $configOk = is_writable(CONFIG_PATH);
    $checks[] = ['config/ 目录可写', $configOk ? '可写' : '不可写', $configOk];

    // public/uploads 可写
    $uploadDir = ROOT_PATH . '/public/uploads';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    $uploadOk = is_writable($uploadDir);
    $checks[] = ['public/uploads/ 目录可写', $uploadOk ? '可写' : '不可写', $uploadOk];

    // storage 可写
    $storageDir = ROOT_PATH . '/storage';
    if (!is_dir($storageDir . '/logs')) @mkdir($storageDir . '/logs', 0755, true);
    $storageOk = is_writable($storageDir);
    $checks[] = ['storage/ 目录可写', $storageOk ? '可写' : '不可写', $storageOk];

    // install 目录可写（用于生成 lock）
    $installOk = is_writable(__DIR__);
    $checks[] = ['install/ 目录可写', $installOk ? '可写' : '不可写', $installOk];

    $allPass = true;
    foreach ($checks as $c) if (!$c[2]) $allPass = false;

    $html = '<table class="check-table"><thead><tr><th>检查项</th><th>当前状态</th><th>结果</th></tr></thead><tbody>';
    foreach ($checks as $c) {
        $html .= '<tr><td>' . $c[0] . '</td><td>' . $c[1] . '</td><td class="' . ($c[2] ? 'ok' : 'fail') . '">' . ($c[2] ? '通过' : '未通过') . '</td></tr>';
    }
    $html .= '</tbody></table>';

    if ($allPass) {
        $html .= '<div class="actions"><a href="?step=2" class="btn btn-primary">下一步</a></div>';
    } else {
        $html .= '<div class="notice notice-error">存在检查项未通过，请先修复环境后再继续安装。</div>';
        $html .= '<div class="actions"><a href="?step=1" class="btn">重新检查</a></div>';
    }

    render('环境检查', $html, 1);
}

/**
 * 步骤2：参数配置
 */
function step2()
{
    // 保留上次提交的值
    $v = $_SESSION['install_config'] ?? [];
    $dbHost = $v['db_host'] ?? '127.0.0.1';
    $dbPort = $v['db_port'] ?? '3306';
    $dbName = $v['db_name'] ?? 'tanhua_forum';
    $dbUser = $v['db_user'] ?? 'root';
    $dbPass = $v['db_pass'] ?? '';
    $dbPrefix = $v['db_prefix'] ?? '';
    $siteTitle = $v['site_title'] ?? '论坛';
    $siteSubtitle = $v['site_subtitle'] ?? '一个自由交流的社区';
    $adminUser = $v['admin_user'] ?? 'admin';
    $adminPass = $v['admin_pass'] ?? '';
    $adminEmail = $v['admin_email'] ?? '';

    $html = '<form method="post" action="?step=3" class="install-form">';

    $html .= '<div class="form-section"><h3>数据库配置</h3>';
    $html .= '<div class="form-row"><label>数据库主机</label><input type="text" name="db_host" value="' . htmlspecialchars($dbHost) . '" required></div>';
    $html .= '<div class="form-row"><label>端口</label><input type="text" name="db_port" value="' . htmlspecialchars($dbPort) . '" required></div>';
    $html .= '<div class="form-row"><label>数据库名</label><input type="text" name="db_name" value="' . htmlspecialchars($dbName) . '" required><span class="hint">不存在将自动创建</span></div>';
    $html .= '<div class="form-row"><label>数据库用户名</label><input type="text" name="db_user" value="' . htmlspecialchars($dbUser) . '" required></div>';
    $html .= '<div class="form-row"><label>数据库密码</label><input type="password" name="db_pass" value="' . htmlspecialchars($dbPass) . '"></div>';
    $html .= '<div class="form-row"><label>表前缀</label><input type="text" name="db_prefix" value="' . htmlspecialchars($dbPrefix) . '" placeholder="留空则不使用前缀"></div>';
    $html .= '</div>';

    $html .= '<div class="form-section"><h3>站点信息</h3>';
    $html .= '<div class="form-row"><label>网站标题</label><input type="text" name="site_title" value="' . htmlspecialchars($siteTitle) . '" required></div>';
    $html .= '<div class="form-row"><label>网站副标题</label><input type="text" name="site_subtitle" value="' . htmlspecialchars($siteSubtitle) . '"></div>';
    $html .= '</div>';

    $html .= '<div class="form-section"><h3>管理员账号</h3>';
    $html .= '<div class="form-row"><label>管理员用户名</label><input type="text" name="admin_user" value="' . htmlspecialchars($adminUser) . '" required></div>';
    $html .= '<div class="form-row"><label>管理员密码</label><input type="password" name="admin_pass" value="' . htmlspecialchars($adminPass) . '" required><span class="hint">至少 6 位</span></div>';
    $html .= '<div class="form-row"><label>确认密码</label><input type="password" name="admin_pass_confirm" required></div>';
    $html .= '<div class="form-row"><label>管理员邮箱</label><input type="email" name="admin_email" value="' . htmlspecialchars($adminEmail) . '" required></div>';
    $html .= '</div>';

    $html .= '<div class="actions"><a href="?step=1" class="btn">上一步</a><button type="submit" class="btn btn-primary">开始安装</button></div>';
    $html .= '</form>';

    render('参数配置', $html, 2);
}

/**
 * 步骤3：执行安装
 */
function step3()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?step=2');
        exit;
    }

    // 收集配置
    $config = [
        'db_host' => trim($_POST['db_host'] ?? ''),
        'db_port' => trim($_POST['db_port'] ?? '3306'),
        'db_name' => trim($_POST['db_name'] ?? ''),
        'db_user' => trim($_POST['db_user'] ?? ''),
        'db_pass' => $_POST['db_pass'] ?? '',
        'db_prefix' => trim($_POST['db_prefix'] ?? ''),
        'site_title' => trim($_POST['site_title'] ?? ''),
        'site_subtitle' => trim($_POST['site_subtitle'] ?? ''),
        'admin_user' => trim($_POST['admin_user'] ?? ''),
        'admin_pass' => $_POST['admin_pass'] ?? '',
        'admin_pass_confirm' => $_POST['admin_pass_confirm'] ?? '',
        'admin_email' => trim($_POST['admin_email'] ?? ''),
    ];

    $_SESSION['install_config'] = $config;

    // 校验
    $errors = [];
    if (!$config['db_host']) $errors[] = '数据库主机不能为空';
    if (!$config['db_name']) $errors[] = '数据库名不能为空';
    if (!$config['db_user']) $errors[] = '数据库用户名不能为空';
    if (!$config['site_title']) $errors[] = '网站标题不能为空';
    if (!$config['admin_user']) $errors[] = '管理员用户名不能为空';
    if (strlen($config['admin_pass']) < 6) $errors[] = '管理员密码至少 6 位';
    if ($config['admin_pass'] !== $config['admin_pass_confirm']) $errors[] = '两次输入的密码不一致';
    if (!$config['admin_email'] || !filter_var($config['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = '管理员邮箱格式不正确';

    if ($errors) {
        $html = '<div class="notice notice-error"><ul><li>' . implode('</li><li>', $errors) . '</li></ul></div>';
        $html .= '<div class="actions"><a href="?step=2" class="btn">返回修改</a></div>';
        render('参数错误', $html, 2);
        exit;
    }

    // 执行安装步骤，捕获错误
    $logs = [];
    try {
        // 1. 创建数据库（如不存在）
        $logs[] = '正在创建数据库 ' . $config['db_name'] . ' ...';
        try {
            $pdo = new PDO(
                "mysql:host={$config['db_host']};port={$config['db_port']};charset=utf8mb4",
                $config['db_user'],
                $config['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['db_name']}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $logs[] = '<span class="log-ok">数据库创建/确认成功</span>';
        } catch (PDOException $e) {
            throw new Exception('数据库连接失败：' . $e->getMessage());
        }

        // 2. 连接数据库
        $pdo = new PDO(
            "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
            $config['db_user'],
            $config['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $logs[] = '已连接数据库';

        // 3. 执行建表 SQL
        $logs[] = '正在创建数据表 ...';
        $prefix = $config['db_prefix'];
        $sqlFile = __DIR__ . '/schema.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception('建表脚本 schema.sql 不存在');
        }
        $sqlContent = file_get_contents($sqlFile);
        // 替换表前缀占位符 {prefix}
        $sqlContent = str_replace('{prefix}', $prefix, $sqlContent);
        // 去掉所有 SQL 注释行（以 -- 开头的整行），避免与下方语句粘连导致被 explode 误合并
        $sqlContent = preg_replace('/^\s*--.*$/m', '', $sqlContent);

        // 拆分多条 SQL 执行
        $statements = array_filter(array_map('trim', explode(";\n", $sqlContent)));
        $count = 0;
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || strpos($stmt, '--') === 0) continue;
            try {
                $pdo->exec($stmt);
                if (preg_match('/^\s*CREATE\s+TABLE/i', $stmt)) $count++;
            } catch (PDOException $e) {
                // 忽略已存在的错误
                if (strpos($e->getMessage(), 'already exists') === false && $e->getCode() != '42S01') {
                    throw new Exception('建表失败：' . $e->getMessage() . ' | SQL: ' . substr($stmt, 0, 80));
                }
            }
        }
        $logs[] = '<span class="log-ok">已创建 ' . $count . ' 张数据表</span>';

        // 4. 创建管理员账号（已存在则更新密码）
        $logs[] = '正在创建管理员账号 ...';
        $adminTable = $prefix . 'users';
        $adminPassHash = password_hash($config['admin_pass'], PASSWORD_DEFAULT);
        $now = date('Y-m-d H:i:s');
        $checkAdmin = $pdo->prepare("SELECT id FROM {$adminTable} WHERE username = ?");
        $checkAdmin->execute([$config['admin_user']]);
        if ($checkAdmin->fetch()) {
            $upd = $pdo->prepare("UPDATE {$adminTable} SET password_hash = ?, email = ?, role = 'super_admin', is_certified = 1, status = 1, updated_at = ? WHERE username = ?");
            $upd->execute([$adminPassHash, $config['admin_email'], $now, $config['admin_user']]);
            $logs[] = '<span class="log-ok">管理员账号已存在，已更新密码</span>';
        } else {
            $stmt = $pdo->prepare("INSERT INTO {$adminTable} (username, email, password_hash, nickname, role, is_certified, status, last_login_at, created_at, updated_at) VALUES (?, ?, ?, ?, 'super_admin', 1, 1, ?, ?, ?)");
            $stmt->execute([
                $config['admin_user'],
                $config['admin_email'],
                $adminPassHash,
                $config['admin_user'],
                $now, $now, $now,
            ]);
            $logs[] = '<span class="log-ok">管理员账号创建成功</span>';
        }

        // 5. 初始化默认板块（已有则跳过）
        $logs[] = '正在初始化默认板块 ...';
        $catTable = $prefix . 'categories';
        $catCount = (int)$pdo->query("SELECT COUNT(*) FROM {$catTable}")->fetchColumn();
        if ($catCount == 0) {
            $cats = [
                ['综合讨论', '自由交流各类话题', 0, 1],
                ['技术分享', '技术文章与经验分享', 0, 2],
                ['认证专区', '仅已认证用户可发帖', 1, 3],
                ['站务公告', '站点公告与反馈', 0, 4],
            ];
            foreach ($cats as $cat) {
                $pdo->exec("INSERT INTO {$catTable} (name, description, icon, is_certification_required, sort_order, post_count, status, created_at) VALUES ('{$cat[0]}', '{$cat[1]}', '', {$cat[2]}, {$cat[3]}, 0, 1, '{$now}')");
            }
            $logs[] = '<span class="log-ok">已创建 4 个默认板块</span>';
        } else {
            $logs[] = '<span class="log-ok">板块已存在，跳过初始化</span>';
        }

        // 6. 初始化角色权限
        $logs[] = '正在初始化角色权限 ...';
        $roleTable = $prefix . 'roles';
        $permTable = $prefix . 'permissions';
        $roles = [
            ['guest', '游客', '未登录访问者'],
            ['user', '普通用户', '已注册登录用户'],
            ['certified_user', '已认证用户', '通过实名认证的用户'],
            ['moderator', '版主', '版块管理者'],
            ['admin', '管理员', '全局内容与用户管理'],
            ['super_admin', '超级管理员', '系统最高权限'],
        ];
        foreach ($roles as $r) {
            $pdo->exec("INSERT IGNORE INTO {$roleTable} (name, code, description, created_at) VALUES ('{$r[1]}', '{$r[0]}', '{$r[2]}', '{$now}')");
        }

        $perms = [
            ['浏览帖子', 'post.view', '帖子'],
            ['发布帖子', 'post.create', '帖子'],
            ['编辑自己帖子', 'post.edit_own', '帖子'],
            ['删除自己帖子', 'post.delete_own', '帖子'],
            ['本版置顶', 'post.pin_section', '帖子'],
            ['本版加精', 'post.essence_section', '帖子'],
            ['本版删除', 'post.delete_section', '帖子'],
            ['本版移动', 'post.move_section', '帖子'],
            ['全局置顶', 'post.pin_global', '帖子'],
            ['发布评论', 'comment.create', '评论'],
            ['删除评论', 'comment.delete_section', '评论'],
            ['提交认证', 'certification.apply', '认证'],
            ['审核认证', 'certification.review', '认证'],
            ['用户管理', 'user.manage', '用户'],
            ['删除用户', 'user.delete', '用户'],
            ['图片上传', 'image.upload', '上传'],
            ['附件上传', 'attachment.upload', '上传'],
            ['板块管理', 'category.manage', '板块'],
            ['系统设置', 'system.manage', '系统'],
        ];
        foreach ($perms as $p) {
            $pdo->exec("INSERT IGNORE INTO {$permTable} (name, code, module, created_at) VALUES ('{$p[0]}', '{$p[1]}', '{$p[2]}', '{$now}')");
        }
        $logs[] = '<span class="log-ok">权限码已初始化</span>';

        // 写入站长角色（兼容旧版升级、便于全新安装也具备站长）
        try {
            $pdo->exec("INSERT IGNORE INTO {$roleTable} (name, code, description, created_at) VALUES ('站长', 'webmaster', '权限最高，可管理所有用户；自身 role/status 不可改', '{$now}')");
        } catch (Exception $e) {}

        // 初始化各角色默认权限矩阵（role_permissions）：
        //   super_admin/admin → 全部；moderator → 板块级+自己；user → 自己+评论；guest → 仅浏览；
        //   webmaster → 全部（即便 Auth::can 已豁免，仍写入便于展示一致）
        $rpTable = $prefix . 'role_permissions';
        $roleIdByCode = [];
        foreach ($pdo->query("SELECT id, code FROM {$roleTable}")->fetchAll(PDO::FETCH_ASSOC) as $r) $roleIdByCode[$r['code']] = (int)$r['id'];
        $permIdByCode = [];
        foreach ($pdo->query("SELECT id, code FROM {$permTable}")->fetchAll(PDO::FETCH_ASSOC) as $p) $permIdByCode[$p['code']] = (int)$p['id'];
        $insertRP = $pdo->prepare("INSERT IGNORE INTO {$rpTable} (role_id, permission_id, created_at) VALUES (?, ?, ?)");
        $allPermKeys = array_keys($permIdByCode);
        $roleDefaults = [
            'super_admin' => $allPermKeys,
            'admin'       => $allPermKeys,
            'moderator'   => [
                'post.view', 'post.create', 'post.edit_own', 'post.delete_own',
                'post.pin_section', 'post.essence_section', 'post.delete_section', 'post.move_section',
                'comment.create', 'comment.delete_section',
                'certification.apply', 'search',
                'report.create', 'social.message',
                'profile.edit', 'notification.view',
                'category.view', 'user.view_public',
            ],
            'certified_user' => [
                'post.view', 'post.create', 'post.edit_own', 'post.delete_own',
                'comment.create', 'comment.delete_own',
                'certification.apply', 'search',
                'report.create', 'social.message', 'social.like', 'social.collect', 'social.follow',
                'profile.edit', 'notification.view', 'certification.badge',
                'category.view', 'category.certified_post', 'user.view_public',
            ],
            'user' => [
                'post.view', 'post.create', 'post.edit_own', 'post.delete_own',
                'comment.create', 'comment.delete_own',
                'certification.apply', 'search',
                'report.create', 'social.message', 'social.like', 'social.collect', 'social.follow',
                'profile.edit', 'notification.view',
                'category.view', 'user.view_public',
            ],
            'guest' => [
                'post.view', 'comment.view', 'category.view', 'user.view_public', 'search',
            ],
            'webmaster' => $allPermKeys,
        ];
        $rpWritten = 0;
        foreach ($roleDefaults as $code => $permCodes) {
            if (!isset($roleIdByCode[$code])) continue;
            $rid = $roleIdByCode[$code];
            foreach ($permCodes as $pc) {
                if (!isset($permIdByCode[$pc])) continue;
                $insertRP->execute([$rid, $permIdByCode[$pc], $now]);
                $rpWritten += $insertRP->rowCount();
            }
        }
        $logs[] = '<span class="log-ok">角色权限矩阵已初始化（写入 ' . $rpWritten . ' 行 role_permissions）</span>';
        $logs[] = '<span class="log-ok">角色权限初始化完成</span>';

        // 6.5 初始化认证项配置（已有则跳过）
        $logs[] = '正在初始化认证项 ...';
        $itemTable = $prefix . 'certification_items';
        $itemCount = (int)$pdo->query("SELECT COUNT(*) FROM {$itemTable}")->fetchColumn();
        if ($itemCount == 0) {
            $items = [
                ['real_name', '真实姓名', 'text', '', 1, 1],
                ['id_card', '身份证号', 'text', '', 1, 2],
                ['phone', '联系电话', 'text', '', 1, 3],
                ['id_card_front', '身份证正面照', 'image', '', 1, 4],
                ['id_card_back', '身份证反面照', 'image', '', 1, 5],
                ['hand_photo', '手持身份证照', 'image', '', 1, 6],
                ['extra_note', '补充说明', 'textarea', '', 0, 7],
            ];
            foreach ($items as $it) {
                $pdo->exec("INSERT INTO {$itemTable} (name,label,type,options,required,sort_order,status,created_at) VALUES ('{$it[0]}','{$it[1]}','{$it[2]}','{$it[3]}',{$it[4]},{$it[5]},1,'{$now}')");
            }
            $logs[] = '<span class="log-ok">认证项初始化完成</span>';
        } else {
            $logs[] = '<span class="log-ok">认证项已存在，跳过初始化</span>';
        }
        // 默认认证图标 SVG（已存在则更新）
        $defaultBadge = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="11" fill="#ea6f5a"/><path d="M9.5 16.2l-3-3 1.4-1.4 1.6 1.6 5.6-5.6 1.4 1.4z" fill="#fff"/></svg>';
        $stmt = $pdo->prepare("INSERT INTO {$prefix}settings (key_name,value,description,updated_at) VALUES ('cert_badge_svg',?, '认证通过后图标SVG',?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)");
        $stmt->execute([$defaultBadge, $now]);
        $logs[] = '<span class="log-ok">认证图标已就绪</span>';

        // 7. 写入配置文件
        $logs[] = '正在生成配置文件 ...';
        $dbConfig = <<<PHP
<?php
return [
    'host' => '{$config['db_host']}',
    'port' => '{$config['db_port']}',
    'database' => '{$config['db_name']}',
    'username' => '{$config['db_user']}',
    'password' => '{$config['db_pass']}',
    'prefix' => '{$prefix}',
    'charset' => 'utf8mb4',
];
PHP;
        file_put_contents(CONFIG_PATH . '/database.php', $dbConfig);

        $siteConfig = <<<PHP
<?php
return [
    'title' => '{$config['site_title']}',
    'subtitle' => '{$config['site_subtitle']}',
    'description' => '{$config['site_title']} - {$config['site_subtitle']}',
    'installed_at' => '{$now}',
    'version' => '1.0.0',
];
PHP;
        file_put_contents(CONFIG_PATH . '/site.php', $siteConfig);
        $logs[] = '<span class="log-ok">配置文件生成成功</span>';

        // 8. 生成安装锁
        file_put_contents(LOCK_FILE, "installed at {$now}\n");
        $logs[] = '<span class="log-ok">安装锁定文件已生成</span>';

        $logs[] = '<span class="log-ok">安装完成！</span>';

        $html = '<div class="install-log">' . implode('<br>', $logs) . '</div>';
        $html .= '<div class="notice notice-success">恭喜，论坛安装成功！请点击下方按钮访问网站。</div>';
        $html .= '<div class="actions"><a href="../index.php" class="btn btn-primary">访问首页</a>';
        $html .= '<a href="?step=done" class="btn">查看完成说明</a></div>';

        render('安装完成', $html, 4);
    } catch (Exception $e) {
        $logs[] = '<span class="log-fail">错误：' . $e->getMessage() . '</span>';
        $html = '<div class="install-log">' . implode('<br>', $logs) . '</div>';
        $html .= '<div class="notice notice-error">安装过程中出现错误，请检查配置后重试。</div>';
        $html .= '<div class="actions"><a href="?step=2" class="btn">返回修改</a></div>';
        render('安装失败', $html, 3);
    }
}

/**
 * 步骤4：完成
 */
function step4()
{
    if (!file_exists(LOCK_FILE)) {
        header('Location: ?step=1');
        exit;
    }
    $html = '<div class="notice notice-success">';
    $html .= '<h3>安装已完成</h3>';
    $html .= '<p>论坛已成功安装。为了安全，建议：</p>';
    $html .= '<ul style="text-align:left;line-height:2;">';
    $html .= '<li>1. 删除或重命名 install 目录（可选，已自动加锁）</li>';
    $html .= '<li>2. 请牢记管理员账号密码</li>';
    $html .= '<li>3. 登录后台进行更多设置</li>';
    $html .= '</ul>';
    $html .= '</div>';
    $html .= '<div class="actions"><a href="../index.php" class="btn btn-primary">访问首页</a></div>';
    render('安装完成', $html, 4);
}
