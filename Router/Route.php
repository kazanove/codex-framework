<?php

declare(strict_types=1);

namespace CodeX\Router;

class Route
{
    private string $compiledPattern;
    private array $paramNames;

    public function __construct(private Definition $definition)
    {
        // Предварительная компиляция шаблона для производительности
        $this->compiledPattern = $this->compileRoute($definition->uri);
        $this->paramNames = $this->getParamNames($definition->uri);
    }

    public function getDefinition(): Definition
    {
        return $this->definition;
    }

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

    private function compileRoute(string $uri): string
    {
        // Экранируем статические части
        $pattern = preg_quote($uri, '/');
        // Заменяем параметры на регулярки
        $pattern = preg_replace('/\\\{([a-zA-Z0-9_]+)(?::([^}]+))?\\\}/', '(?P<$1>$2)', $pattern);
        // Заменяем именованные группы на обычные, если нет ограничений
        $pattern = preg_replace('/\(\?P<([a-zA-Z0-9_]+)>/', '(', $pattern);
        // Добавляем ограничения по умолчанию
        $pattern = preg_replace('/\(\?P<([a-zA-Z0-9_]+)>/', '([^\/]+)', $pattern);

        return '/^' . $pattern . '$/u'; // u = UTF-8 поддержка
    }

    private function getParamNames(string $uri): array
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $uri, $matches);
        return $matches[1] ?? [];
    }
}