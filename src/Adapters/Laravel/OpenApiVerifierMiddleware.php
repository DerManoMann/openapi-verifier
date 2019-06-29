<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters\Laravel;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\Verifier\OpenApiSchemaMismatchException;
use Radebatz\OpenApi\Verifier\VerifiesOpenApi;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

/**
 * Terminating middleware to verify OpenApi responses,.
 */
class OpenApiVerifierMiddleware
{
    protected $psrHttpFactory;

    public function __construct()
    {
        $psr17Factory = new Psr17Factory();
        $this->psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    }

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
