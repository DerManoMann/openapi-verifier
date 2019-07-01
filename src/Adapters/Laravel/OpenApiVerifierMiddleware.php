<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters\Laravel;

use Radebatz\OpenApi\Verifier\Adapters\PSR17Middleware;
use Radebatz\OpenApi\Verifier\OpenApiSchemaMismatchException;
use Radebatz\OpenApi\Verifier\VerifiesOpenApi;

/**
 * Terminating middleware to verify OpenApi responses,.
 */
class OpenApiVerifierMiddleware extends PSR17Middleware
{
    public function handle($request, \Closure $next)
    {
        return $next($request);
    }

    public function terminate($request, $response)
    {
        /** @var VerifiesOpenApi $verifier */
        $verifier = app('openapi-verifier');

        try {
            $verifier->verifyOpenApi(
                $psrRequest = $this->psrHttpFactory->createRequest($request),
                $psrResponse = $this->psrHttpFactory->createResponse($response)
            );
        } catch (OpenApiSchemaMismatchException $oasme) {
            $verifier->failSchemaMismatch($oasme, $psrResponse);

            throw $oasme;
        }

        return $response;
    }
}
