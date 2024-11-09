<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters\Slim;

use Radebatz\OpenApi\Verifier\Adapters\AbstractOpenApiResponseVerifier;
use Radebatz\OpenApi\Verifier\VerifiesOpenApi;
use Slim\App;

trait OpenApiResponseVerifier
{
    use AbstractOpenApiResponseVerifier, VerifiesOpenApi;

    public function registerOpenApiVerifier(?App $app, ?string $specification = null)
    {
        /** @var App $app */
        $app = $app ?: $this->app;

        $this->prepareOpenApiSpecificationLoader('src', $specification);

        if ($this->getOpenApiSpecificationLoader()) {
            $container = $app->getContainer();

            // deal with different container implementations
            if ($container instanceof \ArrayAccess) {
                $container[OpenApiVerifierMiddleware::OPENAPI_VERFIER_CONTAINER_KEY] = $this;
            } elseif (method_exists($container, 'set')) {
                $container->set(OpenApiVerifierMiddleware::OPENAPI_VERFIER_CONTAINER_KEY, $this);
            } else {
                throw new \RuntimeException('Unusable container');
            }
            $app->add(new OpenApiVerifierMiddleware($container));
        }
    }
}
