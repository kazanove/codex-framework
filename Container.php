<?php

declare(strict_types=1);

namespace CodeX;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;

class Container implements ContainerInterface
{
    private static ?Container $instance = null;
    private array $instances = [];
    private array $bindings = [];
    private array $resolving = []; // для защиты от циклических зависимостей

    public function __construct()
    {
        self::$instance = $this;
    }

    public static function getInstance(): Container
    {
        if (self::$instance === null) {
            self::$instance = new Container();
        }
        return self::$instance;
    }

    /**
     * Привязать класс как transient (новый экземпляр при каждом вызове).
     */
    public function bind(string $abstract, null|string|Closure $concrete = null): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'share' => false,
        ];
    }

    /**
     * Привязать класс как singleton (один экземпляр на всё приложение).
     */
    public function singleton(string $abstract, null|string|Closure $concrete = null): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'share' => true,
        ];
    }

    /**
     * Зарегистрировать уже созданный экземпляр.
     */
    public function instance(string $abstract, object $concrete): void
    {
        $this->instances[$abstract] = $concrete;
        // Убираем из resolving, если был
        unset($this->resolving[$abstract]);
    }

    /**
     * Проверяет, существует ли запись в контейнере.
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    /**
     * Получает экземпляр из контейнера (PSR-11).
     *
     * @throws RuntimeException если запись не найдена
     */
    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new RuntimeException("Запись [{$id}] не найдена в контейнере.");
        }

        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        return $this->make($id);
    }

    /**
     * Создаёт экземпляр класса (с разрешением зависимостей).
     *
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public function make(string $abstract, array $parameters = []): object
    {
        // Защита от циклических зависимостей
        if (isset($this->resolving[$abstract])) {
            throw new RuntimeException("Циклическая зависимость при разрешении: {$abstract}");
        }

        $this->resolving[$abstract] = true;

        try {
            // Если уже создан — возвращаем
            if (isset($this->instances[$abstract])) {
                return $this->instances[$abstract];
            }

            // Если есть привязка — используем её
            if (isset($this->bindings[$abstract])) {
                $binding = $this->bindings[$abstract];
                $concrete = $binding['concrete'];

                if ($concrete instanceof Closure) {
                    $object = $concrete($this, $parameters);
                } else {
                    $object = $this->build($concrete, $parameters);
                }
            } elseif (class_exists($abstract)) {
                // Автоматическая привязка
                $this->bind($abstract);
                return $this->make($abstract, $parameters);
            } else {
                throw new RuntimeException("Не удаётся разрешить класс: {$abstract}");
            }

            // Сохраняем как singleton, если нужно
            if ($this->bindings[$abstract]['share'] === true) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        } finally {
            unset($this->resolving[$abstract]);
        }
    }

    /**
     * Создаёт экземпляр через Reflection.
     *
     * @throws ReflectionException
     */
    private function build(string $concrete, array $parameters = []): object
    {
        $reflector = new ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new RuntimeException("Класс {$concrete} не может быть создан (абстрактный, интерфейс и т.д.)");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);
        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Разрешает зависимости конструктора.
     *
     * @throws ReflectionException
     */
    private function resolveDependencies(array $parameters, array $provided = []): array
    {
        $resolved = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            // Приоритет: передано явно
            if (array_key_exists($name, $provided)) {
                $resolved[] = $provided[$name];
                continue;
            }

            $type = $parameter->getType();

            // Типизированная зависимость (не скаляр)
            if ($type && !$type->isBuiltin()) {
                $className = $type->getName();
                $resolved[] = $this->make($className);
                continue;
            }

            // Значение по умолчанию
            if ($parameter->isDefaultValueAvailable()) {
                $resolved[] = $parameter->getDefaultValue();
                continue;
            }

            // Невозможно разрешить
            throw new RuntimeException("Невозможно разрешить параметр: \${$name} в " . $parameter->getDeclaringClass()?->getName() . '::__construct()');
        }

        return $resolved;
    }

    /**
     * Вызывает метод или функцию с внедрением зависимостей.
     *
     * Поддерживает:
     * - ['Class', 'method'] — нестатический метод
     * - ['Class', 'staticMethod'] — статический метод
     * - [$instance, 'method']
     *
     * @throws ReflectionException
     */
    public function call(array $callback, array $parameters = []): mixed
    {
        [$classOrInstance, $method] = $callback;

        $reflection = new ReflectionMethod($classOrInstance, $method);

        $dependencies = [];
        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            $type = $param->getType();
            if ($type && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
                continue;
            }

            throw new RuntimeException("Невозможно разрешить параметр \${$name} для метода {$method}");
        }

        if ($reflection->isStatic()) {
            return $reflection->invokeArgs(null, $dependencies);
        }

        $instance = is_string($classOrInstance) ? $this->make($classOrInstance) : $classOrInstance;
        return $reflection->invokeArgs($instance, $dependencies);
    }
}