<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters\Psr15;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Radebatz\OpenApi\Verifier\OpenApiSchemaMismatchException;
use Radebatz\OpenApi\Verifier\OpenApiVerifier;

/**
 * PSR-15 middleware verifying every response against the specification.
 *
 * Throws `OpenApiSchemaMismatchException` on a mismatch. `$onMismatch` is called first, which lets a
 * test case turn the mismatch into a failure of its own.
 */
final class OpenApiVerifierMiddleware implements MiddlewareInterface
{
    /**
     * @param (\Closure(OpenApiSchemaMismatchException, ResponseInterface): void)|null $onMismatch
     */
    public function __construct(
        private readonly OpenApiVerifier $verifier,
        private readonly ?\Closure $onMismatch = null,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        try {
            $this->verifier->verify($request, $response);
        } catch (OpenApiSchemaMismatchException $openApiSchemaMismatchException) {
            if ($this->onMismatch instanceof \Closure) {
                ($this->onMismatch)($openApiSchemaMismatchException, $response);
            }

            throw $openApiSchemaMismatchException;
        }

        return $response;
    }
}
