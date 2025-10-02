<?php
declare(strict_types=1);

namespace CodeX\Providers;

use CodeX\Provider;

class Request extends Provider
{
    public function register(): void
    {
        $this->application->container->singleton(\CodeX\Http\Request::class, function ($container) {
            return \CodeX\Http\Request::createFromGlobals();
        });
    }
}