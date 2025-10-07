<?php
declare(strict_types=1);

namespace CodeX\Middleware;

use CodeX\Http\Response;

class SecurityHeaders extends \CodeX\Middleware
{
    /**
     */
    public function handle(Response $response): Response
    {

//        $response->header('cache-control', 'max-age=180, public');
//        $response->header('X-Content-Type-Options', 'nosniff');
//        $response->header('Content-Security-Policy', 'frame-ancestors');
//        $response->header('X-XSS-Protection', '1; mode=block');
//        $response->header('Referrer-Policy', 'no-referrer-when-downgrade');
//        $response->header('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        return $response;
    }
}