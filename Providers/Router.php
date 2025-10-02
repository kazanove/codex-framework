<?php
declare(strict_types=1);

namespace CodeX\Providers;

use CodeX\Provider;
use ReflectionException;


class Router extends Provider
{
    public function register(): void
    {
        $this->application->container->singleton(\CodeX\Router::class, function () {
            return new \CodeX\Router($this->application);
        });
    }

    /**
     * @throws ReflectionException
     */
    public function boot(): void
    {
        $router = $this->application->container->make(\CodeX\Router::class);
        $this->mapRoutes($router);
        $this->application->container->call([\CodeX\Router::class, 'dispatcher']);
    }

    /**
     * @throws ReflectionException
     */
    protected function mapRoutes(): void
    {
        $dirRouting=$this->application->dirApp.'config'.DIRECTORY_SEPARATOR.'routing'.DIRECTORY_SEPARATOR;
        if(file_exists($dirRouting.'web.php')){
            include $dirRouting.'web.php';
        }
    }
}