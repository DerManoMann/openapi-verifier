<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters\Slim;

use Psr\Container\ContainerInterface;
use Radebatz\OpenApi\Verifier\OpenApiSchemaMismatchException;
use Radebatz\OpenApi\Verifier\VerifiesOpenApi;

class OpenApiVerifierMiddleware
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $next)
    {
        $response = $next($request, $response);

        /** @var VerifiesOpenApi $verifier */
        $verifier = $this->container->get('openapi-verifier');

        try {
            $verifier->verifyOpenApi($request, $response);
        } catch (OpenApiSchemaMismatchException $oasme) {
            $verifier->failSchemaMismatch($oasme, $response);

            throw $oasme;
        }

        return $response;
    }
}
