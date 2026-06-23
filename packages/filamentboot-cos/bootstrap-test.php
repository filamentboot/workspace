<?php

/**
 * COS 包测试 bootstrap
 *
 * 使用演示项目的 vendor/autoload.php 作为基础，
 * 再注册 COS 包自身的 PSR-4 命名空间。
 */

// 查找主仓库 vendor（上溯三级：cos/ -> packages/ -> worktree/ -> project/）
$vendorPaths = [
    __DIR__.'/vendor/autoload.php',
    __DIR__.'/../../vendor/autoload.php',
    __DIR__.'/../../../vendor/autoload.php',
    '/home/john/projects/personal/filament-admin/vendor/autoload.php',
];

$autoloadLoaded = false;
foreach ($vendorPaths as $path) {
    if (file_exists($path)) {
        require $path;
        $autoloadLoaded = true;
        break;
    }
}

if (! $autoloadLoaded) {
    throw new RuntimeException('无法找到 vendor/autoload.php，请先运行 composer install');
}

// 注册 COS 包 src/ 的 PSR-4 命名空间
spl_autoload_register(function (string $class) {
    $prefix  = 'Filamentboot\\FilamentbootCos\\';
    $baseDir = __DIR__.'/src/';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file          = $baseDir.str_replace('\\', '/', $relativeClass).'.php';

    if (file_exists($file)) {
        require $file;
    }
}, prepend: true);
