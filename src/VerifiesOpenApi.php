<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier;

use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

trait VerifiesOpenApi
{
    /** @var OpenApiSpecificationLoader $openapiSpecificationLoader */
    protected $openapiSpecificationLoader = null;

    /**
     * Verify the response body for the given request method, path and status code.
     *
     * @return bool `true` if the content has been validated, `false` if not (no matching schema)
     *
     * @throws OpenApiSchemaMismatchException
     */
    public function verifyOpenApiResponseBody(string $method, string $path, int $statusCode, string $body): bool
    {
        if ($schemaUrl = $this->getOpenApiSpecificationLoader()->getResponseSchemaUrlFor($method, $path, $statusCode)) {
            $retriever = new UriRetriever();
            if ($schema = $retriever->retrieve($schemaUrl)) {
                $validator = new Validator();

                $validator->check(json_decode($body), $schema);

                if (!$validator->isValid()) {
                    throw (new OpenApiSchemaMismatchException(sprintf('Schema mismatch: %s[%s]:%s', $method, $statusCode, $path)))
                        ->setErrors($validator->getErrors());
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Verify the response body for the given request and response.
     *
     * @return bool `true` if the content has been validated, `false` if not (no matching schema)
     *
     * @throws OpenApiSchemaMismatchException
     */
    public function verifyOpenApi(ServerRequestInterface $request, ResponseInterface $response): bool
    {
        return $this->verifyOpenApiResponseBody($request->getMethod(), $request->getUri()->getPath(), $response->getStatusCode(), (string) $response->getBody());
    }

    public function getOpenApiSpecificationLoader(): ?OpenApiSpecificationLoader
    {
        if (!$this->openapiSpecificationLoader) {
            if (property_exists($this, 'openapiSpecification') && $this->openapiSpecification) {
                $this->openapiSpecificationLoader = new OpenApiSpecificationLoader($this->openapiSpecification);
            }
        }

        return $this->openapiSpecificationLoader;
    }
}
