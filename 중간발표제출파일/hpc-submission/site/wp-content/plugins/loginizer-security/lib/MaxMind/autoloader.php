<?php
function loginizer_maxmind_autoloader($class){
    $prefix = 'LoginizerMaxMind\\Db\\';
    $base_dir = __DIR__ . '/Db/';

    if(strncmp($class, $prefix, strlen($prefix)) !== 0){
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relative_path = str_replace('\\', '/', $relative) . '.php';
    $file = $base_dir . $relative_path;

    if(file_exists($file)){
        include_once $file;
    }
}
spl_autoload_register('loginizer_maxmind_autoloader');

