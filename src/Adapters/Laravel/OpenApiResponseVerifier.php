<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters\Laravel;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Psr\Http\Message\ResponseInterface;
use Radebatz\OpenApi\Verifier\Adapters\AbstractOpenApiResponseVerifier;
use Radebatz\OpenApi\Verifier\OpenApiSchemaMismatchException;
use Radebatz\OpenApi\Verifier\VerifiesOpenApi;

/**
 * Assumes to be used in a Laravel `Illuminate\Foundation\Testing\TestCase`.
 */
trait OpenApiResponseVerifier
{
    use AbstractOpenApiResponseVerifier;
    use VerifiesOpenApi;

    public function registerOpenApiVerifier(?Application $app = null, ?string $specification = null): void
    {
        $app = $app ?: $this->app;

        $this->prepareOpenApiSpecificationLoader('app', $specification);

        if ($this->getOpenApiSpecificationLoader()) {
            // the kernel resolves the middleware by class name, for `handle()` and `terminate()` alike
            $app->instance(OpenApiVerifierMiddleware::class, new OpenApiVerifierMiddleware(
                $this->getOpenApiVerifier(),
                fn (OpenApiSchemaMismatchException $oasme, ResponseInterface $response) => $this->failSchemaMismatch($oasme, $response)
            ));
            $app[Kernel::class]->pushMiddleware(OpenApiVerifierMiddleware::class);
        }
    }
}
