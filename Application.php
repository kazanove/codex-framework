<?php

declare(strict_types=1);

namespace CodeX;

use CodeX\Http\Response;
use ErrorException;
use Psr\Container\ContainerInterface;
use ReflectionException;
use RuntimeException;
use Throwable;

class Application
{
    private static Application $instance;
    public readonly ContainerInterface $container;
    public array $config = [];
    public readonly string $dirApp;
    private array $providers = [];
    private array $shutdownCallbacks = [];

    public function __construct(string $dirApp)
    {
        error_reporting(-1);
        ini_set('display_errors', 'off');
        set_error_handler([$this, 'handlerError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handlerShutdown']);
        $dirApp = rtrim($dirApp, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!file_exists($configCorePath = __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'core.php')) {
            throw new RuntimeException('Файл конфигурации ядра не найден: ' . $configCorePath);
        }
        if (!is_array($this->config = include $configCorePath)) {
            throw new RuntimeException('Файл конфигурации ядра не является массивом: ' . $configCorePath);
        }

        if (file_exists($configAppPath = $dirApp . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'core.php')) {
            if (!is_array($configApp = include $configAppPath)) {
                throw new RuntimeException('Файл конфигурации приложения не является массивом: ' . $configAppPath);
            }
            $this->config = array_replace_recursive($this->config, $configApp);
        }
        $this->dirApp = $dirApp;
        $containerClass = $this->config['container'] ?? Container::class;
        if (!class_exists($containerClass)) {
            throw new RuntimeException('Класс контейнера не найден: ' . $containerClass);
        }
        $this->container = new $containerClass();
        foreach ($this->config['providers'] ?? [] as $providerClass) {
            $provider = new $providerClass($this);
            $this->providers[] = $provider;
            if (method_exists($provider, 'register')) {
                $provider->register();
            }
        }
        self::$instance = $this;
    }

    public static function getInstance(): Application
    {
        if (self::$instance === null) {
            throw new \RuntimeException("Application не инициализирован. Создайте экземпляр через new Application().");
        }
        return self::$instance;
    }

    /**
     * @throws ErrorException
     */
    public function handlerError(int $severity, string $message, string $file, int $line): void
    {
        if (error_reporting() & $severity) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
    }

    /**
     * @throws ReflectionException
     */
    public function handler(): void
    {

        foreach ($this->config['middleware'] ?? [] as $middlewareClass) {
            if (!class_exists($middlewareClass)) {
                throw new RuntimeException('Класс промежуточного ПО не найден: ' . $middlewareClass);
            }

            if (!method_exists($middlewareClass, 'handle')) {
                throw new RuntimeException('Промежуточное программное обеспечение должно иметь метод handle: ' . $middlewareClass);
            }

            $this->container->call([$middlewareClass, 'handle']);
        }
        foreach ($this->providers as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot();
            }
        }
    }

    /**
     * @throws ErrorException
     * @throws ReflectionException
     */
    public function handlerShutdown(): void
    {
        if(PHP_SAPI === 'cli'){
            return;
        }
        foreach ($this->shutdownCallbacks as $callback) {
            $callback();
        }

        foreach ($this->config['shutdown'] ?? [] as $callback) {
            if (is_callable($callback)) {
                $callback();
            } elseif (is_array($callback) && count($callback) === 2) {
                [$class, $method] = $callback;
                if (method_exists($class, $method)) {
                    $this->container->call($callback);
                }
            }
        }
        if ($error = error_get_last()) {
            $exception = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
            try {
                $this->handleException($exception);
            } catch (Throwable $e) {
                $this->logError($e);
                http_response_code(500);
                echo '<h2>Критическая ошибка приложения.</h2>';
            }
        }
    }

    public function handleException(Throwable $e): void
    {
        if ($this->config['app']['debug'] ?? false) {
            // Режим разработки — показываем всё
            echo '<h2 style="color:red">CodeX Error</h2>';
            echo '<pre>';
            echo 'Message: ' . $e->getMessage() . "\n";
            echo 'File: ' . $e->getFile() . "\n";
            echo 'Line: ' . $e->getLine() . "\n";
            echo 'Trace:' . "\n" . $e->getTraceAsString();
            echo '</pre>';
        } else {
            $this->logError($e);
            http_response_code(500);
            echo '<h2>Что-то пошло не так.</h2>';
        }
    }

    private function logError(Throwable $e): void
    {
        $logPath = $this->config['app']['log_path'] ?? __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'app.log';
        $logDir = dirname($logPath);

        if (!is_dir($logDir) && !mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            throw new RuntimeException(sprintf('Каталог «%s» не был создан', $logDir));
        }

        $message = sprintf("[%s] %s: %s in %s:%d\nStack trace:\n%s\n---\n", date('Y-m-d H:i:s'), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
        file_put_contents($logPath, $message, FILE_APPEND | LOCK_EX);
    }

    public function onShutdown(callable $callback): void
    {
        if (is_callable($callback)) {
            $this->shutdownCallbacks[] = $callback;
        } else {
            throw new RuntimeException('Обратный вызов выключения должен быть доступен для вызова');
        }
    }

}