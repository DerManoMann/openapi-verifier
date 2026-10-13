<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters\Guzzle;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Radebatz\OpenApi\Verifier\OpenApiVerifier;

/**
 * Guzzle handler middleware verifying every response against the specification.
 *
 * A mismatch rejects the response's promise, so a synchronous request throws
 * `OpenApiSchemaMismatchException`.
 *
 * ```php
 * $stack = HandlerStack::create();
 * $stack->push(new OpenApiVerifierMiddleware($verifier), 'openapi-verifier');
 * $client = new Client(['handler' => $stack]);
 * ```
 */
final class OpenApiVerifierMiddleware
{
    public function __construct(private readonly OpenApiVerifier $verifier)
    {
    }

    public function __invoke(callable $handler): callable
    {
        return fn (RequestInterface $request, array $options) => $handler($request, $options)->then(
            function (ResponseInterface $response) use ($request): ResponseInterface {
                $this->verifier->verify($request, $response);

                return $response;
            }
        );
    }
}
