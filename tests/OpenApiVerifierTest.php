<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests;

use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\Verifier\OpenApiSchemaMismatchException;
use Radebatz\OpenApi\Verifier\OpenApiVerifier;

class OpenApiVerifierTest extends TestCase
{
    protected static function json(array $schema): array
    {
        return ['content' => ['application/json' => ['schema' => $schema]]];
    }

    protected static function requiring(string $property): array
    {
        return ['type' => 'object', 'required' => [$property]];
    }

    protected function verifier(): OpenApiVerifier
    {
        $specification = [
            'openapi' => '3.0.0',
            'servers' => [
                ['url' => 'https://api.example.com/{version}', 'variables' => ['version' => ['default' => 'v1']]],
            ],
            'paths' => [
                '/users' => ['get' => ['responses' => ['200' => self::json(['type' => 'array'])]]],
                '/users/me' => ['get' => ['responses' => ['200' => self::json(self::requiring('me'))]]],
                '/users/{id}' => ['get' => ['responses' => [
                    '200' => self::json(self::requiring('id')),
                    '4XX' => self::json(self::requiring('error')),
                    'default' => self::json(self::requiring('fault')),
                ]]],
                '/users/{id}/posts/{post}' => ['get' => ['responses' => ['200' => self::json(self::requiring('post'))]]],
                '/problems' => ['get' => ['responses' => ['200' => ['content' => [
                    'application/problem+json' => ['schema' => self::requiring('title')],
                    'text/plain' => ['schema' => ['type' => 'string']],
                ]]]]],
            ],
        ];

        return OpenApiVerifier::fromSpecification(json_decode(json_encode($specification)));
    }

    public static function responses(): iterable
    {
        // path, status, body, content type, expected: true verified, false nothing to verify, null mismatch
        yield 'literal path' => ['/users', 200, '[]', null, true];
        yield 'literal wins over template' => ['/users/me', 200, '{"me":1}', null, true];
        yield 'literal wins, so its schema applies' => ['/users/me', 200, '{"id":1}', null, null];
        yield 'template' => ['/users/42', 200, '{"id":42}', null, true];
        yield 'template mismatch' => ['/users/42', 200, '{"name":"joe"}', null, null];
        yield 'two placeholders' => ['/users/42/posts/7', 200, '{"post":7}', null, true];
        yield 'query string ignored' => ['/users/42?expand=posts', 200, '{"id":42}', null, true];
        yield 'server base path stripped' => ['/v1/users/42', 200, '{"id":42}', null, true];
        yield 'no leading slash' => ['users/42', 200, '{"id":42}', null, true];
        yield 'unknown path' => ['/nope', 200, '{}', null, false];
        yield 'status range' => ['/users/42', 404, '{"error":"gone"}', null, true];
        yield 'status range mismatch' => ['/users/42', 404, '{}', null, null];
        yield 'status default' => ['/users/42', 500, '{"fault":"boom"}', null, true];
        yield 'json suffix media type' => ['/problems', 200, '{"title":"x"}', 'application/problem+json; charset=utf-8', true];
        yield 'json suffix media type mismatch' => ['/problems', 200, '{}', 'application/problem+json', null];
        yield 'non-json media type skipped' => ['/problems', 200, 'hello', 'text/plain', false];
        yield 'unmatched content type falls back to json' => ['/users/42', 200, '{"id":42}', 'text/html; charset=UTF-8', true];
    }

    #[Test]
    #[DataProvider('responses')]
    public function verifyResponseBody(string $path, int $statusCode, string $body, ?string $contentType, ?bool $expected): void
    {
        if (null === $expected) {
            $this->expectException(OpenApiSchemaMismatchException::class);
        }

        $this->assertSame($expected, $this->verifier()->verifyResponseBody('get', $path, $statusCode, $body, $contentType));
    }

    #[Test]
    public function verifyTakesThePsrMessages(): void
    {
        $response = new Response(200, ['Content-Type' => 'application/json'], '{"id":42}');

        $this->assertTrue($this->verifier()->verify(new Request('GET', 'https://api.example.com/v1/users/42'), $response));
    }

    #[Test]
    public function pathTemplateOverridesTheRequestPath(): void
    {
        $response = new Response(200, [], '{"post":7}');

        $this->assertTrue($this->verifier()->verify(new Request('GET', '/whatever'), $response, '/users/{id}/posts/{post}'));
    }
}
