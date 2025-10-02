<?php
declare(strict_types=1);

namespace CodeX;

class Middleware
{
    public function __construct(protected Application $application)
    {

    }
}