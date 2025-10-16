<?php

declare(strict_types=1);

namespace CodeX\Router;

class Route
{
    private static array $routes = [];

    private string $compiledPattern;
    private array $paramNames;

    public function __construct(private Definition $definition)
    {
        // Предварительная компиляция шаблона для производительности
        $this->compiledPattern = $this->compileRoute($definition->uri);
        $this->paramNames = $this->getParamNames($definition->uri);

        // Сохраняем маршрут в статический массив
        self::$routes[] = $this;
    }

    /**
     * Возвращает определение маршрута
     */
    public function getDefinition(): Definition
    {
        return $this->definition;
    }

    /**
     * Проверяет, соответствует ли путь маршруту
     */
    public function matches(string $path, ?array &$params = null): bool
    {
        if (!preg_match($this->compiledPattern, $path, $matches)) {
            return false;
        }

        array_shift($matches); // Удаляем полное совпадение

        // Быстрая проверка количества параметров
        if (count($this->paramNames) !== count($matches)) {
            $params = [];
            return true;
        }

        $params = array_combine($this->paramNames, $matches);
        return true;
    }

    /**
     * Добавляет тег к маршруту
     */
    public function tag(string $tag): self
    {
        return new self($this->definition->withTag($tag));
    }

    /**
     * Проверяет, имеет ли маршрут указанный тег
     */
    public function hasTag(string $tag): bool
    {
        return $this->definition->hasTag($tag);
    }

    /**
     * Возвращает все теги маршрута
     */
    public function getTags(): array
    {
        return $this->definition->getTags();
    }

    /**
     * Получает все зарегистрированные маршруты
     */
    public static function getAll(): array
    {
        return self::$routes;
    }

    /**
     * Находит маршрут по URI и методу
     */
    public static function find(string $uri, string $method): ?self
    {
        foreach (self::$routes as $route) {
            if ($route->getDefinition()->uri === $uri) {
                return $route;
            }
        }
        return null;
    }

    /**
     * Компилирует шаблон маршрута в регулярное выражение
     */
    private function compileRoute(string $uri): string
    {
        // Экранируем всё, кроме параметров
        $pattern = preg_quote($uri, '/');

        // Заменяем {parameter} на ([^\/]+)
        // Заменяем {parameter:regex} на (regex)
        $pattern = preg_replace_callback('/\\\{([a-zA-Z0-9_]+)(?::([^}]+))?\\\}/', function ($matches) {
            $constraint = $matches[2] ?? '[^\/]+';
            return '(' . $constraint . ')';
        }, $pattern);

        return '/^' . $pattern . '$/u';
    }

    /**
     * Извлекает имена параметров из URI
     */
    private function getParamNames(string $uri): array
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $uri, $matches);
        return $matches[1] ?? [];
    }
}