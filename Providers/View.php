<?php
declare(strict_types=1);

namespace CodeX\Providers;

use CodeX\Provider;
use CodeX\View\Compiler;

class View extends Provider
{
    public function register(): void
    {
        $appViewPath = $this->application->dirApp .'storage' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR;
        $frameworkViewPath = ROOT . 'CodeX' . DIRECTORY_SEPARATOR .'storage' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR;
        $cachePath = $this->application->dirApp . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR;

        $this->application->container->singleton(\CodeX\View::class, function () use ($appViewPath, $frameworkViewPath, $cachePath) {
            return new \CodeX\View($appViewPath, $frameworkViewPath, $cachePath);
        });
        Compiler::directive('customDirective', static function ($expression) {
            return "echo 'Пользовательская директива: ' . implode(', ', [$expression]);";
        });
        Compiler::filter('upper', static function ($value) {
            return "strtoupper({$value})";
        });
        Compiler::filter('format', static function ($value, $args) {
            return "sprintf({$args}, {$value})";
        });
    }
}