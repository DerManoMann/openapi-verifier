<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters\Slim;

use Psr\Http\Message\ResponseInterface;
use Radebatz\OpenApi\Verifier\Adapters\AbstractOpenApiResponseVerifier;
use Radebatz\OpenApi\Verifier\Adapters\Psr15\OpenApiVerifierMiddleware;
use Radebatz\OpenApi\Verifier\OpenApiSchemaMismatchException;
use Radebatz\OpenApi\Verifier\VerifiesOpenApi;
use Slim\App;

trait OpenApiResponseVerifier
{
    use AbstractOpenApiResponseVerifier, VerifiesOpenApi;

    /**
     * @template TContainer of \Psr\Container\ContainerInterface|null
     *
     * @param App<TContainer> $app
     */
    public function registerOpenApiVerifier(App $app, ?string $specification = null): void
    {
        $this->prepareOpenApiSpecificationLoader('src', $specification);

        if ($this->getOpenApiSpecificationLoader()) {
            $app->add(new OpenApiVerifierMiddleware(
                $this->getOpenApiVerifier(),
                fn (OpenApiSchemaMismatchException $oasme, ResponseInterface $response) => $this->failSchemaMismatch($oasme, $response)
            ));
        }
    }
}
