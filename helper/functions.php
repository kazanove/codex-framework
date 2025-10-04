<?php
declare(strict_types=1);

use CodeX\Application;

/**
 * @throws ReflectionException
 */
function url(string $path = ''): string
{
    $base = Application::getInstance()->container->make(\CodeX\Http\Request::class)->getUri();
    $path = ltrim($path, '/');
    return $path ? rtrim($base, '/') . '/' . $path : $base;
}