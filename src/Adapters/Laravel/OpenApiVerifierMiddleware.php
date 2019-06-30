<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters\Laravel;

use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\Verifier\Adapters\Middleware;
use Radebatz\OpenApi\Verifier\OpenApiSchemaMismatchException;
use Radebatz\OpenApi\Verifier\VerifiesOpenApi;

/**
 * Terminating middleware to verify OpenApi responses,.
 */
class OpenApiVerifierMiddleware extends Middleware
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
            if ($verifier instanceof TestCase) {
                $verifier->fail(sprintf(
                    '%s:%s%s%s%s%s%s%s',
                    $oasme->getMessage(),
                    PHP_EOL,
                    $oasme->getErrorSummary(),
                    PHP_EOL,
                    '',
                    PHP_EOL,
                    (string) $psrRequest->getBody(),
                    PHP_EOL
                ));
            }

            throw $oasme;
        }

        return $response;
    }
}
