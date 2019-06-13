<?php

namespace Radebatz\OpenApi\Verifier\Adapters;

use Radebatz\OpenApi\Verifier\OpenApiVerificationException;
use Radebatz\OpenApi\Verifier\VerifiesOpenApi;

/**
 * Terminating middleware to verify OpenApi responses,.
 */
class LaravelOpenApiVerifierMiddleware
{
    public function handle($request, \Closure $next)
    {
        return $next($request);
    }

    /**
     * @throws OpenApiVerificationException
     */
    public function terminate($request, $response)
    {
        /** @var VerifiesOpenApi $verifier */
        $verifier = app('openapi-verifier');

        try {
            $verifier->verifyResponse($request->method(), $request->path(), $response->getStatusCode(), $response->content());
        } catch (OpenApiVerificationException $oave) {
            $verifier->fail(sprintf('%s: %s', $oave->getMessage(), implode(', ', $oave->getErrors())));
        }
    }
}
