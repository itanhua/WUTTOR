<?php
/**
 * 数据库连接类（PDO 单例）
 */
class Database
{
    protected static $pdo = null;

    public static function init()
    {
        if (self::$pdo !== null) return;

        $config = Config::get('database');
        if (!$config) return; // 安装阶段未配置

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        );

        try {
            self::$pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die('数据库连接失败：' . $e->getMessage());
        }
    }

    public static function pdo()
    {
        if (self::$pdo === null) self::init();
        return self::$pdo;
    }

    /**
     * 测试连接（安装时使用）
     */
    public static function testConnection($host, $port, $database, $username, $password)
    {
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return true;
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

    /**
     * 创建数据库（若不存在）
     */
    public static function createDatabase($host, $port, $database, $username, $password)
    {
        try {
            $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            return true;
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }
}
