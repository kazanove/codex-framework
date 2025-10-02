<?php
declare(strict_types=1);

namespace CodeX;

use CodeX\Http\Request;
use CodeX\Http\Response;
use CodeX\Router\Definition;
use CodeX\Router\Route;
use ReflectionException;
use ReflectionMethod;

class Router
{
    private array $routes = [];
    private ?Route $currentRoute = null;
    private array $middlewareStack = [];

    public function __construct(private readonly Application $application)
    {
    }

    // ========== Middleware методы ========== //

    public function middleware(array|string $middleware): self
    {
        $this->middlewareStack = array_merge($this->middlewareStack, (array)$middleware);
        return $this;
    }

    public function group(callable $callback): void
    {
        $previousMiddleware = $this->middlewareStack;
        $callback($this);
        $this->middlewareStack = $previousMiddleware;
    }

    // ========== Маршрутизация ========== //

    public function get(string $uri, string|array|\Closure $action): self
    {
        $definition = new Definition($uri, $action, $this->middlewareStack);
        $this->addRoute('GET', $definition);
        return $this;
    }

    public function post(string $uri, string|array|\Closure $action): self
    {
        $definition = new Definition($uri, $action, $this->middlewareStack);
        $this->addRoute('POST', $definition);
        return $this;
    }

    private function addRoute(string $method, Definition $definition): void
    {
        $this->routes[$method][] = new Route($definition);
    }

    public function dispatcher(): Response
    {
        $request = $this->application->container->make(Request::class);
        $method = $request->getMethod();
        $path = $request->getPathInfo();

        if (!isset($this->routes[$method])) {
            return $this->handleNotFound($request);
        }

        foreach ($this->routes[$method] as $route) {
            if ($route->matches($path, $params)) {
                $this->currentRoute = $route;
                return $this->handleFoundRoute($route, $params, $request);
            }
        }

        return $this->handleNotFound($request);
    }

    private function handleFoundRoute(Route $route, array $params, Request $request): Response
    {
        $definition = $route->getDefinition();
        // Применяем middleware маршрута
        $this->applyMiddleware($definition->middleware);
        // Обработка Closure
        if ($definition->isClosure()) {
            $closure = $definition->getRawAction();
            $result = $closure();

            if ($result instanceof Response) {
                return $result;
            }

            $response = $this->application->container->make(Response::class);
            $response->setContent((string)$result);
            return $response;
        }

        // Обработка контроллеров
        [$controller, $method] = $this->parseAction($definition->getRawAction());
        $controllerInstance = $this->application->container->make($controller);
        $result = $this->callControllerMethod($controllerInstance, $method, $params, $request);
        if ($result instanceof Response) {
            return $result;
        }
        $response = $this->application->container->make(Response::class);
        $response->setContent((string)$result);
        return $response;
    }

    private function parseAction(string|array $action): array
    {
        if (is_string($action) && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action);
            return [trim($controller), trim($method)];
        }

        if (is_array($action) && count($action) === 2) {
            return $action;
        }

        throw new \InvalidArgumentException('Неподдерживаемый формат действия маршрута');
    }

    private function callControllerMethod($controller, string $method, array $routeParams, Request $request)
    {
        $reflection = new ReflectionMethod($controller, $method);
        $args = [];

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            if ($type && !$type->isBuiltin()) {
                $className = $type->getName();
                if ($this->application->container->has($className)) {
                    $args[] = $this->application->container->make($className);
                    continue;
                }
            }

            if (isset($routeParams[$name])) {
                $args[] = $routeParams[$name];
                continue;
            }

            if ($name === 'request') {
                $args[] = $request;
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            throw new \RuntimeException("Не удалось разрешить параметр \$$name для " . get_class($controller) . "::$method()");
        }

        return $reflection->invokeArgs($controller, $args);
    }

    private function handleNotFound(Request $request): Response
    {
        $response = $this->application->container->make(Response::class);
        $response->setStatusCode(404);
        $response->setContent('<h1>404 Not Found</h1>');
        return $response;
    }

    private function applyMiddleware(array $middleware): void
    {
        foreach ($middleware as $middlewareAlias) {
            if (isset($this->application->config['route_middleware'][$middlewareAlias])) {
                $middlewareClass = $this->application->config['route_middleware'][$middlewareAlias];
            } elseif (class_exists($middlewareAlias)) {
                $middlewareClass = $middlewareAlias;
            } else {
                throw new \RuntimeException('Middleware не найден: ' . $middlewareAlias);
            }

            if (!method_exists($middlewareClass, 'handle')) {
                throw new \RuntimeException('Middleware должен иметь метод handle: ' . $middlewareClass);
            }

            $this->application->container->call([$middlewareClass, 'handle']);
        }
    }
}