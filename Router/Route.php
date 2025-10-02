<?php
declare(strict_types=1);

namespace CodeX\Router;

class Route
{
    private Definition $definition;

    public function __construct(Definition $definition)
    {
        $this->definition = $definition;
    }

    public function getUri(): string
    {
        return $this->definition->uri;
    }

    public function getDefinition(): Definition
    {
        return $this->definition;
    }

    public function getAction(): string|array|\Closure
    {
        return $this->definition->action;
    }

    public function getMiddleware(): array
    {
        return $this->definition->middleware;
    }

    public function matches(string $path, ?array &$params = null): bool
    {
        $pattern = $this->compileRoute($this->definition->uri);
        if (preg_match($pattern, $path, $matches)) {
            array_shift($matches);
            $paramNames = $this->getParamNames($this->definition->uri);
            if (count($paramNames) !== count($matches)) {
                $params = [];
            } else {
                $params = array_combine($paramNames, $matches);
            }
            return true;
        }
        return false;
    }

    private function compileRoute(string $uri): string
    {
        $pattern = preg_replace_callback('/([^{\}]+)/', static function ($matches) {
            return preg_quote($matches[1], '/');
        }, $uri);

        $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)(?::([^}]+))?\}/', static function ($matches) {
            $constraint = $matches[2] ?? '[^\/]+';
            return '(' . $constraint . ')';
        }, $pattern);

        return '/^' . $pattern . '$/';
    }

    private function getParamNames(string $uri): array
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $uri, $matches);
        return $matches[1] ?? [];
    }
}