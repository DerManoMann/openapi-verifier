<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters\Psr18;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Radebatz\OpenApi\Verifier\OpenApiVerifier;

/**
 * A PSR-18 client verifying every response it receives against the specification.
 *
 * Throws `OpenApiSchemaMismatchException` on a mismatch, which is not a PSR-18 client exception:
 * the request was sent, the response just does not match.
 */
final class OpenApiVerifyingClient implements ClientInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly OpenApiVerifier $verifier,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $response = $this->client->sendRequest($request);

        $this->verifier->verify($request, $response);

        return $response;
    }
}
