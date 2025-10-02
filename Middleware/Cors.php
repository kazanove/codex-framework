<?php
declare(strict_types=1);

namespace CodeX\Middleware;

use CodeX\Http\Response;
use CodeX\Middleware;

class Cors extends Middleware
{
    public function handle(Response $response): Response
    {
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->header('Access-Control-Max-Age', '86400'); // 24 часа
        return $response;
    }
}