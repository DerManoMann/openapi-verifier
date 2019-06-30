<?php

namespace Radebatz\OpenApi\Verifier\Adapters\Slim;

use Radebatz\OpenApi\Verifier\Adapters\Middleware;

class OpenApiVerfierMiddleware extends Middleware
{
    public function __invoke($request, $response, $next)
    {
        $response = $next($request, $response);

        return $response;
    }
}
