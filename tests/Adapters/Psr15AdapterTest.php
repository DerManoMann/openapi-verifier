<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests\Adapters;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Radebatz\OpenApi\Verifier\Adapters\Psr15\OpenApiVerifierMiddleware;
use Radebatz\OpenApi\Verifier\OpenApiSchemaMismatchException;
use Radebatz\OpenApi\Verifier\OpenApiVerifier;

class Psr15AdapterTest extends TestCase
{
    protected function handle(string $body, ?\Closure $onMismatch = null): ResponseInterface
    {
        $middleware = new OpenApiVerifierMiddleware(
            OpenApiVerifier::fromSpecification(__DIR__ . '/../specifications/users.json'),
            $onMismatch
        );

        $handler = new class($body) implements RequestHandlerInterface {
            public function __construct(private readonly string $body)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, ['Content-Type' => 'application/json'], $this->body);
            }
        };

        return $middleware->process(new ServerRequest('GET', '/user/1'), $handler);
    }

    #[Test]
    public function passesAMatchingResponseOn(): void
    {
        $body = '{"data":{"id":1,"name":"joe","email":"joe@cool.com"}}';

        $this->assertSame($body, (string) $this->handle($body)->getBody());
    }

    #[Test]
    public function throwsOnAMismatch(): void
    {
        $this->expectException(OpenApiSchemaMismatchException::class);

        $this->handle('{"data":{"id":1,"name":"joe"}}');
    }

    #[Test]
    public function callsOnMismatchFirst(): void
    {
        $seen = null;

        try {
            $this->handle('{"data":{"id":1,"name":"joe"}}', function (OpenApiSchemaMismatchException $oasme) use (&$seen): void {
                $seen = $oasme->getErrorSummary();
            });
            $this->fail('No exception thrown');
        } catch (OpenApiSchemaMismatchException) {
        }

        $this->assertStringContainsString('The property email is required', (string) $seen);
    }
}
