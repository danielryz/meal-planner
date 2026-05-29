<?php

declare(strict_types=1);

namespace App\Core;

final class Autoloader
{
    public static function register(string $basePath): void
    {
        spl_autoload_register(static function (string $className) use ($basePath): void {
            $prefix = 'App\\';

            if (strncmp($className, $prefix, strlen($prefix)) !== 0) {
                return;
            }

            $relativeClass = substr($className, strlen($prefix));
            $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
            $filePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;

            if (!is_file($filePath)) {
                $parts = explode(DIRECTORY_SEPARATOR, $relativePath);
                $parts[0] = strtolower($parts[0]);
                $filePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
            }

            if (is_file($filePath)) {
                require_once $filePath;
            }
        });
    }
}
