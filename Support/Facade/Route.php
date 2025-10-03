<?php
declare(strict_types=1);

namespace CodeX\Support\Facade;

use CodeX\Router;
use CodeX\Support\Facade;

/**
 * @method static middleware(string $string)
 * @method static post(string $string, array $array)
 * @method static get(string $string, string[]|\Closure $array)
 */
class Route extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Router::class;
    }
}