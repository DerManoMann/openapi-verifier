<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests\Adapters;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Radebatz\OpenApi\Verifier\Adapters\Guzzle\OpenApiVerifierMiddleware;
use Radebatz\OpenApi\Verifier\Adapters\Psr18\OpenApiVerifyingClient;
use Radebatz\OpenApi\Verifier\OpenApiSchemaMismatchException;
use Radebatz\OpenApi\Verifier\OpenApiVerifier;

class ClientAdapterTest extends TestCase
{
    protected function verifier(): OpenApiVerifier
    {
        return OpenApiVerifier::fromSpecification(__DIR__ . '/../specifications/users.yaml');
    }

    public static function responses(): iterable
    {
        // path, body, expected error (null: valid)
        yield 'users' => ['/users', '{"data":[{"id":1,"name":"joe","email":"joe@cool.com"}]}', null];
        yield 'users mismatch' => ['/users', '{"data":[{"id":1,"email":"joe@cool.com"}]}', 'The required properties (name) are missing'];
        yield 'user, by template' => ['/user/1', '{"data":{"id":1,"name":"joe","email":"joe@cool.com"}}', null];
        yield 'user mismatch, by template' => ['/user/1', '{"data":{"id":1,"name":"joe"}}', 'The required properties (email) are missing'];
        yield 'not in the specification' => ['/nope', 'anything', null];
    }

    protected function send(callable $send, string $body, ?string $error): void
    {
        if ($error) {
            $this->expectException(OpenApiSchemaMismatchException::class);
        }

        try {
            /** @var ResponseInterface $response */
            $response = $send();
        } catch (OpenApiSchemaMismatchException $oasme) {
            $this->assertStringContainsString($error, (string) $oasme->getErrorSummary());

            throw $oasme;
        }

        // verifying must leave the body for the caller to read
        $this->assertSame($body, $response->getBody()->getContents());
    }

    #[Test]
    #[DataProvider('responses')]
    public function guzzleMiddleware(string $path, string $body, ?string $error): void
    {
        $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], $body)]));
        $stack->push(new OpenApiVerifierMiddleware($this->verifier()), 'openapi-verifier');
        $client = new Client(['handler' => $stack, 'base_uri' => 'http://localhost']);

        $this->send(fn () => $client->get($path), $body, $error);
    }

    #[Test]
    #[DataProvider('responses')]
    public function guzzleMiddlewareAsync(string $path, string $body, ?string $error): void
    {
        $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], $body)]));
        $stack->push(new OpenApiVerifierMiddleware($this->verifier()), 'openapi-verifier');
        $client = new Client(['handler' => $stack, 'base_uri' => 'http://localhost']);

        $this->send(fn () => $client->getAsync($path)->wait(), $body, $error);
    }

    #[Test]
    #[DataProvider('responses')]
    public function psr18Client(string $path, string $body, ?string $error): void
    {
        $inner = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], $body)]))]);
        $client = new OpenApiVerifyingClient($inner, $this->verifier());

        $this->send(fn () => $client->sendRequest(new Request('GET', 'http://localhost' . $path)), $body, $error);
    }
}
