<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters\Laravel;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Radebatz\OpenApi\Verifier\OpenApiSchemaMismatchException;
use Radebatz\OpenApi\Verifier\OpenApiVerifier;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Terminating middleware verifying every response against the specification.
 *
 * Laravel is not PSR-15, so the request and response are converted to PSR-7 first. The route's
 * uri is passed as the path template, since Laravel has already matched it.
 */
final class OpenApiVerifierMiddleware
{
    private readonly PsrHttpFactory $psrHttpFactory;

    /**
     * @param (\Closure(OpenApiSchemaMismatchException, ResponseInterface): void)|null $onMismatch
     */
    public function __construct(
        private readonly OpenApiVerifier $verifier,
        private readonly ?\Closure $onMismatch = null,
    ) {
        $psr17Factory = new Psr17Factory();
        $this->psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    }

    public function handle(Request $request, \Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $psrRequest = $this->psrHttpFactory->createRequest($request);
        $psrResponse = $this->psrHttpFactory->createResponse($response);

        try {
            // what `route()` calls; its docblock claims a route is always there, but a request that
            // matched none, a 404 for example, has none
            $route = ($request->getRouteResolver())();
            $this->verifier->verify($psrRequest, $psrResponse, $route instanceof Route ? $route->uri() : null);
        } catch (OpenApiSchemaMismatchException $oasme) {
            if ($this->onMismatch instanceof \Closure) {
                ($this->onMismatch)($oasme, $psrResponse);
            }

            throw $oasme;
        }
    }
}
