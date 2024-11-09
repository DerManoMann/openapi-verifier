<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier;

use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

trait VerifiesOpenApi
{
    /** @var OpenApiSpecificationLoader */
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
     * The optional path override will enforce using the given path to lookup the spec, instead
     * of taking the path from the given `$request`.
     * This is useful in cases of dynamic path elements where the configured route contains
     * placeholders (typically something like `{id}`).
     *
     * @param  string|null $path optional path override
     * @return bool        `true` if the content has been validated, `false` if not (no matching schema)
     *
     * @throws OpenApiSchemaMismatchException
     */
    public function verifyOpenApi(ServerRequestInterface $request, ResponseInterface $response, ?string $path = null): bool
    {
        return $this->verifyOpenApiResponseBody(
            $request->getMethod(),
            $path ?? $request->getUri()->getPath(),
            $response->getStatusCode(),
            (string) $response->getBody()
        );
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

    public function failSchemaMismatch(OpenApiSchemaMismatchException $oasme, ResponseInterface $response)
    {
        if ($this instanceof TestCase) {
            $this->fail(sprintf(
                '%s:%s%s%s%s%s%s%s',
                $oasme->getMessage(),
                PHP_EOL,
                $oasme->getErrorSummary(),
                PHP_EOL,
                '',
                PHP_EOL,
                (string) $response->getBody(),
                PHP_EOL
            ));
        }
    }
}
