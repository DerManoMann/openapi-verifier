<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters;

use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class PSR17Middleware
{
    protected $psrHttpFactory;

    public function __construct()
    {
        $psr17Factory = new Psr17Factory();
        $this->psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    }
}
