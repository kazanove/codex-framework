<?php
declare(strict_types=1);

use CodeX\Application;
use CodeX\Session\Flash;

/**
 * @throws ReflectionException
 */
function url(string $path = ''): string
{
    $base = Application::getInstance()->container->make(\CodeX\Http\Request::class)->getUri();
    $path = ltrim($path, '/');
    return $path ? rtrim($base, '/') . '/' . $path : $base;
}
function flash(string $key, mixed $default = null): mixed
{
    return Flash::get($key, $default);
}
function app(string $service)
{
    return Application::getInstance()->container->make($service);
}