<?php

declare(strict_types=1);

namespace CodeX\Providers;

use CodeX\Provider;

class Response extends Provider
{
    public function register(): void
    {
        $this->application->container->singleton(\CodeX\Http\Response::class, function ():\CodeX\Http\Response {
            return new \CodeX\Http\Response();
        });
    }
}