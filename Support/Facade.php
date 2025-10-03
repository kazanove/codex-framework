<?php
declare(strict_types=1);

namespace CodeX\Support;

use CodeX\Application;
use ReflectionException;
use RuntimeException;

class Facade
{
    private static array $resolvedInstance=[];

    /**
     * @throws ReflectionException
     */
    public static function __callStatic($method, $args)
    {
        $instance = static::getFacadeRoot();
        if (! $instance) {
            throw new RuntimeException('Фасадный корень не установлен.');
        }
        return $instance->$method(...$args);
    }

    /**
     * @throws ReflectionException
     */
    public static function getFacadeRoot()
    {
        return static::resolveFacadeInstance(static::getFacadeAccessor());
    }

    /**
     * @throws ReflectionException
     */
    protected static function resolveFacadeInstance($name)
    {
        if (is_object($name)) {
            return $name;
        }

        try {
            return static::$resolvedInstance[$name] ?? (
            static::$resolvedInstance[$name] = Application::getInstance()->container->make($name)
            );
        } catch (\Throwable $e) {
            throw new RuntimeException("Не удалось разрешить фасад {$name}: " . $e->getMessage(), 0, $e);
        }
    }
    protected static function getFacadeAccessor()
    {
        throw new RuntimeException('Facade не реализует метод getFacadeAccessor.');
    }
}