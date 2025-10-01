<?php
declare(strict_types=1);

namespace CodeX\Providers;

use CodeX\Application;
use CodeX\Provider;

class Container extends Provider
{
    public function register(): void
    {
        $container = $this->application->container;
        $container->instance(Application::class, $this->application);
        $container->instance(\CodeX\Container::class, $container);
    }
}