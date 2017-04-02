<?php
/**
 *
 * Date: 17-3-25
 * Time: 下午8:29
 * author :李华 yehong0000@163.com
 */
function __nsqAutoLoader($className)
{
    $dir = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', '/', $className) . '.php';
    if(is_file($dir)){
        require($dir);
    }else{
        return false;
    }
}

spl_autoload_register("__nsqAutoLoader");