<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier;

use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Verifies responses against an OpenAPI specification.
 *
 * Needs no test case and no framework, so anything that sees a request and its response can use it.
 */
final class OpenApiVerifier
{
    public function __construct(private readonly OpenApiSpecificationLoader $specificationLoader)
    {
    }

    /**
     * @param object|string $specification the specification object or filename
     */
    public static function fromSpecification(object|string $specification): self
    {
        return new self(new OpenApiSpecificationLoader($specification));
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
     * @return bool `true` if the body has been validated, `false` if not (no matching schema)
     *
     * @throws OpenApiSchemaMismatchException
     */
    public function verifyResponseBody(string $method, string $path, int $statusCode, string $body, ?string $contentType = null): bool
    {
        if (!$schemaUrl = $this->specificationLoader->getResponseSchemaUrlFor($method, $path, $statusCode, $contentType)) {
            return false;
        }

        $validator = new Validator();
        $validator->check(json_decode($body), (new UriRetriever())->retrieve($schemaUrl));

        if (!$validator->isValid()) {
            throw (new OpenApiSchemaMismatchException(sprintf('Schema mismatch: %s[%s]:%s', $method, $statusCode, $path)))
                ->setErrors($validator->getErrors());
        }

        return true;
    }
}
