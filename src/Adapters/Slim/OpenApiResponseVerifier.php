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

            $container['openapi-verifier'] = $this;
            $app->add(new OpenApiVerifierMiddleware($container));
        }
    }
}
