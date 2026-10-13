<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Radebatz\OpenApi\Verifier\Schema\OpisSchemaValidator;
use Radebatz\OpenApi\Verifier\Schema\SchemaValidator;

/**
 * Verifies responses against an OpenAPI specification.
 *
 * Needs no test case and no framework, so anything that sees a request and its response can use it.
 */
final class OpenApiVerifier
{
    private readonly SchemaValidator $schemaValidator;

    public function __construct(
        private readonly OpenApiSpecificationLoader $specificationLoader,
        ?SchemaValidator $schemaValidator = null,
    ) {
        $this->schemaValidator = $schemaValidator ?? new OpisSchemaValidator();
    }

    /**
     * @param object|string $specification the specification object or filename
     */
    public static function fromSpecification(object|string $specification, ?SchemaValidator $schemaValidator = null): self
    {
        return new self(new OpenApiSpecificationLoader($specification), $schemaValidator);
    }

    public function getSpecificationLoader(): OpenApiSpecificationLoader
    {
        return $this->specificationLoader;
    }

    /**
     * Verify the response to the given request.
     *
     * The path is matched against the specification's path templates; `$pathTemplate`, typically
     * the route pattern of a framework, skips that and is used as given.
     *
     * @return bool `true` if the body has been validated, `false` if not (no matching schema)
     *
     * @throws OpenApiSchemaMismatchException
     */
    public function verify(RequestInterface $request, ResponseInterface $response, ?string $pathTemplate = null): bool
    {
        $body = $response->getBody();
        $content = (string) $body;
        if ($body->isSeekable()) {
            // the caller reads the body next, and casting left the stream at its end
            $body->rewind();
        }

        return $this->verifyResponseBody(
            $request->getMethod(),
            $pathTemplate ?? $request->getUri()->getPath(),
            $response->getStatusCode(),
            $content,
            $response->getHeaderLine('Content-Type') ?: null
        );
    }

    /**
     * Verify a response body for the given request method, path and status code.
     *
     * A body that is not JSON is not validated if its content type is not JSON either.
     *
     * @return bool `true` if the body has been validated, `false` if not (no matching schema, or not JSON)
     *
     * @throws OpenApiSchemaMismatchException
     */
    public function verifyResponseBody(string $method, string $path, int $statusCode, string $body, ?string $contentType = null): bool
    {
        if (!($schema = $this->specificationLoader->getResponseSchemaFor($method, $path, $statusCode, $contentType)) instanceof \stdClass) {
            return false;
        }

        $data = json_decode($body);
        if (null === $data && JSON_ERROR_NONE !== json_last_error() && !$this->claimsJson($contentType)) {
            return false;
        }

        if ([] !== ($errors = $this->schemaValidator->validate($data, $schema, $this->specificationLoader->getOpenApiVersion()))) {
            throw (new OpenApiSchemaMismatchException(sprintf('Schema mismatch: %s[%s]:%s', $method, $statusCode, $path)))
                ->setErrors($errors);
        }

        return true;
    }

    /**
     * Whether the content type is JSON, or missing, in which case the body is taken to be JSON.
     */
    private function claimsJson(?string $contentType): bool
    {
        $type = strtolower(trim(explode(';', (string) $contentType)[0]));

        return '' === $type || 'application/json' === $type || str_ends_with($type, '+json');
    }
}
