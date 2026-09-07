<?php
/**
 * 配置管理类
 */
class Config
{
    protected static $items = [];

    public static function load($name)
    {
        $file = CONFIG_PATH . '/' . $name . '.php';
        if (file_exists($file)) {
            self::$items[$name] = require $file;
        }
    }

    public static function get($key, $default = null)
    {
        $keys = explode('.', $key);
        $value = self::$items;
        foreach ($keys as $k) {
            if (!isset($value[$k])) return $default;
            $value = $value[$k];
        }
        return $value;
    }

    public static function set($key, $value)
    {
        $keys = explode('.', $key);
        $arr = &self::$items;
        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $arr[$k] = $value;
            } else {
                if (!isset($arr[$k])) $arr[$k] = [];
                $arr = &$arr[$k];
            }
        }
    }

    public static function all()
    {
        return self::$items;
    }
}
