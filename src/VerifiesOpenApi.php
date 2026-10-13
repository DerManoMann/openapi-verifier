<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;

trait VerifiesOpenApi
{
    protected ?OpenApiSpecificationLoader $openapiSpecificationLoader = null;

    /**
     * Verify the response body for the given request method, path and status code.
     *
     * @return bool `true` if the content has been validated, `false` if not (no matching schema)
     *
     * @throws OpenApiSchemaMismatchException
     */
    public function verifyOpenApiResponseBody(string $method, string $path, int $statusCode, string $body, ?string $contentType = null): bool
    {
        return $this->getOpenApiVerifier()->verifyResponseBody($method, $path, $statusCode, $body, $contentType);
    }

    /**
     * Verify the response body for the given request and response.
     *
     * The path is matched against the specification's path templates. The optional `$path`
     * skips that and is used as given, typically the route pattern of a framework, which
     * contains placeholders like `{id}`.
     *
     * @param  string|null $path optional path override
     * @return bool        `true` if the content has been validated, `false` if not (no matching schema)
     *
     * @throws OpenApiSchemaMismatchException
     */
    public function verifyOpenApi(RequestInterface $request, ResponseInterface $response, ?string $path = null): bool
    {
        return $this->getOpenApiVerifier()->verify($request, $response, $path);
    }

    public function getOpenApiVerifier(): OpenApiVerifier
    {
        if (!($loader = $this->getOpenApiSpecificationLoader()) instanceof OpenApiSpecificationLoader) {
            throw new \LogicException('No OpenAPI specification to verify against');
        }

        return new OpenApiVerifier($loader);
    }

    public function getOpenApiSpecificationLoader(): ?OpenApiSpecificationLoader
    {
        // isset() is false for a declared but uninitialized typed property, where a read would throw
        if (!$this->openapiSpecificationLoader && isset($this->openapiSpecification) && $this->openapiSpecification) {
            $this->openapiSpecificationLoader = new OpenApiSpecificationLoader($this->openapiSpecification);
        }

        return $this->openapiSpecificationLoader;
    }

    /**
     * Fail the current test with the mismatch, its error summary and the response body.
     *
     * Does nothing outside a PHPUnit test case.
     */
    public function failSchemaMismatch(OpenApiSchemaMismatchException $oasme, ResponseInterface $response): void
    {
        if ($this instanceof TestCase) {
            $this->fail(implode(PHP_EOL, [
                $oasme->getMessage() . ':',
                (string) $oasme->getErrorSummary(),
                '',
                (string) $response->getBody(),
            ]));
        }
    }
}
