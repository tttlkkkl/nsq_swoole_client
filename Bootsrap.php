<?php
/**
 *
 * Date: 17-3-25
 * Time: 下午8:29
 * author :李华 yehong0000@163.com
 */
function __autoloader($className)
{
    $dir = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', '/', $className) . '.php';
    require($dir);
}

spl_autoload_register("__autoloader", true);