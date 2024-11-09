<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters\Laravel;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Radebatz\OpenApi\Verifier\Adapters\AbstractOpenApiResponseVerifier;
use Radebatz\OpenApi\Verifier\VerifiesOpenApi;

/**
 * Assumes to be used in a Laravel `Illuminate\Foundation\Testing\TestCase`.
 */
trait OpenApiResponseVerifier
{
    use AbstractOpenApiResponseVerifier, VerifiesOpenApi;

    public function registerOpenApiVerifier(?Application $app = null, ?string $specification = null)
    {
        $app = $app ?: $this->app;

        $this->prepareOpenApiSpecificationLoader('app', $specification);

        if ($this->getOpenApiSpecificationLoader()) {
            $app->instance(OpenApiVerifierMiddleware::OPENAPI_VERFIER_CONTAINER_KEY, $this);
            $app[Kernel::class]->pushMiddleware(OpenApiVerifierMiddleware::class);
        }
    }
}
