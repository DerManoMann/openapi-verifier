<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters;

use Illuminate\Contracts\Http\Kernel;
use Radebatz\OpenApi\Verifier\VerifiesOpenApi;

trait LaravelOpenApiResponseVerifier
{
    use VerifiesOpenApi;

    public function registerOpenApiVerifier(?string $specification = null)
    {
        if ($specification) {
            $this->openapiSpecification = $specification;
        }

        // try loader
        $specificationLoader = $this->getOpenApiSpecificationLoader();

        if (!$specificationLoader) {
            // try some default filenames
            foreach (['openapi.json', 'openapi.yaml'] as $specfile) {
                if (file_exists($specification = app_path('../tests/' . $specfile))) {
                    $this->openapiSpecification = $specification;
                    break;
                }
            }

            // try loader again
            $specificationLoader = $this->getOpenApiSpecificationLoader();
        }

        if (!$specificationLoader) {
            $openApi = \OpenApi\scan(app_path());
            $this->openapiSpecification = json_decode($openApi->toJson());
        }

        // and finally!
        if ($this->getOpenApiSpecificationLoader()) {
            $this->app->instance('openapi-verifier', $this);
            $this->app[Kernel::class]->pushMiddleware(LaravelOpenApiVerifierMiddleware::class);
        }
    }
}
