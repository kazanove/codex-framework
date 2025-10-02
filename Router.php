<?php

declare(strict_types=1);

namespace CodeX;

use CodeX\Http\Request;
use CodeX\Http\Response;
use CodeX\Router\Definition;
use CodeX\Router\Route;
use ReflectionException;

class Router
{
    private array $routes = [];
    private array $middlewareStack = [];
    private const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    public function __construct(private readonly Application $application)
    {
    }

    // ========== HTTP Methods ========== //

    public function get(string $uri, string|array|\Closure $action): self
    {
        return $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, string|array|\Closure $action): self
    {
        return $this->addRoute('POST', $uri, $action);
    }

    public function put(string $uri, string|array|\Closure $action): self
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    public function patch(string $uri, string|array|\Closure $action): self
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    public function delete(string $uri, string|array|\Closure $action): self
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    public function options(string $uri, string|array|\Closure $action): self
    {
        return $this->addRoute('OPTIONS', $uri, $action);
    }

    // ========== Middleware & Groups ========== //

    public function middleware(array|string $middleware): self
    {
        $this->middlewareStack = array_merge($this->middlewareStack, (array)$middleware);
        return $this;
    }

    public function group(array $attributes, callable $callback): void
    {
        $previousMiddleware = $this->middlewareStack;

        if (isset($attributes['middleware'])) {
            $this->middlewareStack = array_merge($this->middlewareStack, (array)$attributes['middleware']);
        }

        // Поддержка префикса URI
        if (isset($attributes['prefix'])) {
            $this->applyPrefix($attributes['prefix'], $callback);
        } else {
            $callback($this);
        }

        $this->middlewareStack = $previousMiddleware;
    }

    private function applyPrefix(string $prefix, callable $callback): void
    {
        $originalAddRoute = \Closure::bind(function (string $method, string $uri, $action) use ($prefix) {
            $this->addRoute($method, $prefix . $uri, $action);
        }, $this, self::class);

        $wrappedCallback = function (Router $router) use ($callback, $originalAddRoute) {
            // Переопределяем addRoute временно
            $router->addRoute = $originalAddRoute;
            $callback($router);
        };

        $wrappedCallback($this);
    }

    // ========== Core Logic ========== //

    private function addRoute(string $method, string $uri, string|array|\Closure $action): self
    {
        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            throw new \InvalidArgumentException("HTTP method {$method} is not supported");
        }

        $definition = new Definition($uri, $action, $this->middlewareStack);
        $this->routes[$method][] = new Route($definition);
        return $this;
    }

    public function dispatcher(): Response
    {
        $request = $this->application->container->make(Request::class);
        $method = $request->getMethod();
        $path = $request->getPathInfo();

        // Быстрая проверка: есть ли маршруты для метода?
        if (empty($this->routes[$method])) {
            return $this->handleNotFound($request);
        }

        foreach ($this->routes[$method] as $route) {
            if ($route->matches($path, $params)) {
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

        $action = $definition->getRawAction();

        // Обработка Closure
        if ($definition->isClosure()) {
            $result = $action();
            return $this->makeResponse($result);
        }

        // Обработка контроллеров
        [$controller, $method] = $this->parseAction($action);
        $controllerInstance = $this->application->container->make($controller);
        $result = $this->callControllerMethod($controllerInstance, $method, $params, $request);

        return $this->makeResponse($result);
    }

    private function makeResponse(mixed $result): Response
    {
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
            return array_map('trim', explode('@', $action));
        }

        if (is_array($action) && count($action) === 2) {
            return $action;
        }

        throw new \InvalidArgumentException('Неподдерживаемый формат действия маршрута');
    }

    private function callControllerMethod(object $controller, string $method, array $routeParams, Request $request)
    {
        $reflection = new \ReflectionMethod($controller, $method);
        $args = [];

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            // Внедрение через DI
            if ($type && !$type->isBuiltin()) {
                $className = $type->getName();
                if ($this->application->container->has($className)) {
                    $args[] = $this->application->container->make($className);
                    continue;
                }
            }

            // Параметры маршрута
            if (isset($routeParams[$name])) {
                $args[] = $routeParams[$name];
                continue;
            }

            // Специальные параметры
            if ($name === 'request') {
                $args[] = $request;
                continue;
            }

            // Значение по умолчанию
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            throw new \RuntimeException("Не удалось разрешить параметр \${$name} для " . get_class($controller) . "::$method()");
        }

        return $reflection->invokeArgs($controller, $args);
    }

    private function handleNotFound(Request $request): Response
    {
        // Проверяем, определён ли кастомный обработчик 404
        if (isset($this->application->config['error_pages']['404'])) {
            $notFoundHandler = $this->application->config['error_pages']['404'];

            // Поддержка Closure
            if ($notFoundHandler instanceof \Closure) {
                $result = $notFoundHandler($request);
                return $this->makeResponse($result);
            }

            // Поддержка [Controller::class, 'method']
            if (is_array($notFoundHandler) && count($notFoundHandler) === 2) {
                [$controller, $method] = $notFoundHandler;
                $controllerInstance = $this->application->container->make($controller);
                $result = $this->callControllerMethod($controllerInstance, $method, [], $request);
                return $this->makeResponse($result);
            }

            // Поддержка строки (путь к файлу или Controller@method)
            if (is_string($notFoundHandler)) {
                // Проверяем, является ли это файлом
                if (file_exists($notFoundHandler)) {
                    $response = $this->application->container->make(Response::class);
                    $response->setContent(file_get_contents($notFoundHandler));
                    return $response;
                }

                // Иначе считаем, что это Controller@method
                [$controller, $method] = $this->parseAction($notFoundHandler);
                $controllerInstance = $this->application->container->make($controller);
                $result = $this->callControllerMethod($controllerInstance, $method, [], $request);
                return $this->makeResponse($result);
            }
        }

        // Стандартная страница 404 по умолчанию
        $response = $this->application->container->make(Response::class);
        $response->setStatusCode(404);
        $response->setContent('<h1>404 Not Found</h1>');
        return $response;
    }

    private function applyMiddleware(array $middleware): void
    {
        foreach ($middleware as $alias) {
            $middlewareClass = $this->resolveMiddleware($alias);

            if (!method_exists($middlewareClass, 'handle')) {
                throw new \RuntimeException("Middleware {$middlewareClass} должен иметь метод handle");
            }

            $this->application->container->call([$middlewareClass, 'handle']);
        }
    }

    private function resolveMiddleware(string $alias): string
    {
        // Поддержка алиасов из конфига
        if (isset($this->application->config['route_middleware'][$alias])) {
            return $this->application->config['route_middleware'][$alias];
        }

        // Прямое указание класса
        if (class_exists($alias)) {
            return $alias;
        }

        throw new \RuntimeException("Middleware не найден: {$alias}");
    }
}