<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters\Slim;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Radebatz\OpenApi\Verifier\OpenApiSchemaMismatchException;
use Radebatz\OpenApi\Verifier\VerifiesOpenApi;

class OpenApiVerifierMiddleware
{
    public const OPENAPI_VERFIER_CONTAINER_KEY = 'openapi-verifier';

    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $next = null)
    {
        $response = $next ? $next($request, $response) : $response;
        $response = ($response instanceof RequestHandlerInterface) ? $response->handle($request) : $response;
        $routePath = null;

        /** @var VerifiesOpenApi $verifier */
        $verifier = $this->container->get(OpenApiVerifierMiddleware::OPENAPI_VERFIER_CONTAINER_KEY);

        try {
            $verifier->verifyOpenApi($request, $response, $routePath);
        } catch (OpenApiSchemaMismatchException $oasme) {
            $verifier->failSchemaMismatch($oasme, $response);

            throw $oasme;
        }

        return $response;
    }
}
