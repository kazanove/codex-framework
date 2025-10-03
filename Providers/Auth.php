<?php
declare(strict_types=1);

namespace CodeX\Providers;

use CodeX\Application;
use CodeX\Auth\Guard;
use CodeX\Auth\UserProvider;
use CodeX\Provider;

class Auth extends Provider
{
    public function register(): void
    {
        $this->application->container->singleton(UserProvider::class, function () {
            return new UserProvider();
        });

        $this->application->container->singleton(Guard::class, function ($container) {
            $provider = $container->get(UserProvider::class);
            return new Guard($provider);
        });
    }

    public function boot(): void
    {
        if (!function_exists('auth')) {
            function auth(): Guard
            {
                return Application::getInstance()->container->get(Guard::class);
            }
        }
    }
}