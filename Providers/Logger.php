<?php
declare(strict_types=1);

namespace CodeX\Providers;

use CodeX\Provider;


class Logger extends Provider
{
    public function register(): void
    {
        $debug = $this->application->config['app']['debug'] ?? false;
        $logPath = $this->application->config['app']['log_path'] ?? dirname(__DIR__) . '/storage/logs/';

        // Основной логгер — пишет в файл
        $this->application->container->singleton(\CodeX\Logger::class, function () use ($debug, $logPath) {
            $minLevel = $debug ? \CodeX\Logger\Level::DEBUG :\CodeX\Logger\Level::WARNING;
            return new \CodeX\Logger('app', $logPath, $minLevel, buffered: false);
        });

        // Для Debug Bar — буферизованный логгер
        if ($debug) {
            $this->application->container->singleton('logger.debug_bar', function () use ($logPath) {
                return new \CodeX\Logger('debug_bar', $logPath, \CodeX\Logger\Level::DEBUG, buffered: true);
            });
        }
    }
}