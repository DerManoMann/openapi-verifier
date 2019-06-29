<?php

namespace Radebatz\OpenApi\Verifier\Adapters\Laravel;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Radebatz\OpenApi\Verifier\VerifiesOpenApi;

/**
 * Assumes to be used in a Laravel `Illuminate\Foundation\Testing\TestCase`.
 */
trait OpenApiResponseVerifier
{
    use VerifiesOpenApi;

    public function registerOpenApiVerifier(?Application $app = null, ?string $specification = null)
    {
        $app = $app ?: $this->app;
        $appPath = $app['path'];

        if ($specification) {
            $this->openapiSpecification = $specification;
        }

        // try loader
        $specificationLoader = $this->getOpenApiSpecificationLoader();

        if (!$specificationLoader) {
            // try some default filenames
            foreach (['openapi.json', 'openapi.yaml'] as $specfile) {
                if (file_exists($specification = $appPath . '/../tests/' . $specfile)) {
                    $this->openapiSpecification = $specification;
                    break;
                }
            }

            // try loader again
            $specificationLoader = $this->getOpenApiSpecificationLoader();
        }

        if (!$specificationLoader) {
            $openApi = \OpenApi\scan($appPath);
            $this->openapiSpecification = json_decode($openApi->toJson());
        }

        // and finally!
        if ($this->getOpenApiSpecificationLoader()) {
            $app->instance('openapi-verifier', $this);
            $app[Kernel::class]->pushMiddleware(OpenApiVerifierMiddleware::class);
        }
    }
}
