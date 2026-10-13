<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests;

use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\Verifier\OpenApiSchemaMismatchException;
use Radebatz\OpenApi\Verifier\OpenApiVerifier;
use Radebatz\OpenApi\Verifier\Schema\SchemaError;
use Radebatz\OpenApi\Verifier\Schema\SchemaValidator;

final class OpenApiVerifierTest extends TestCase
{
    /**
     * @param  array<string, mixed> $schema
     * @return array<string, mixed>
     */
    protected static function json(array $schema): array
    {
        return ['content' => ['application/json' => ['schema' => $schema]]];
    }

    /**
     * @return array<string, mixed>
     */
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

        return OpenApiVerifier::fromSpecification(json_decode((string) json_encode($specification)));
    }

    /**
     * @return iterable<array<mixed>>
     */
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
        yield 'unmatched content type falls back to json, mismatch' => ['/users/42', 200, '{"name":"joe"}', 'text/html', null];
        yield 'unmatched content type, body not json, skipped' => ['/users/42', 200, '<p>hi</p>', 'application/xml', false];
        yield 'json content type, body not json, mismatch' => ['/users/42', 200, '<p>hi</p>', 'application/json', null];
        yield 'no content type, body not json, mismatch' => ['/users/42', 200, '<p>hi</p>', null, null];
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

    /**
     * @return iterable<array<mixed>>
     */
    public static function dialects(): iterable
    {
        $ref = ['$ref' => '#/components/schemas/thing'];

        // version, schema, body, expected: true verified, null mismatch
        yield '3.0 nullable type, null' => ['3.0.0', ['type' => 'string', 'nullable' => true], 'null', true];
        yield '3.0 nullable type, wrong type' => ['3.0.0', ['type' => 'string', 'nullable' => true], '1', null];
        yield '3.0 not nullable, null' => ['3.0.0', ['type' => 'string'], 'null', null];
        yield '3.0 nullable enum, null' => ['3.0.0', ['type' => 'string', 'enum' => ['a', 'b'], 'nullable' => true], 'null', true];
        yield '3.0 nullable enum, not listed' => ['3.0.0', ['type' => 'string', 'enum' => ['a', 'b'], 'nullable' => true], '"c"', null];
        yield '3.0 nullable without type, null' => ['3.0.0', $ref + ['nullable' => true], 'null', true];
        yield '3.0 nullable without type, the schema' => ['3.0.0', $ref + ['nullable' => true], '{"id":1}', true];
        yield '3.0 nullable without type, neither' => ['3.0.0', $ref + ['nullable' => true], '{}', null];
        yield '3.0 swagger-php null branch, null' => ['3.0.0', ['oneOf' => [$ref, ['type' => 'object', 'nullable' => true, 'enum' => [null]]]], 'null', true];
        yield '3.0 swagger-php null branch, the schema' => ['3.0.0', ['oneOf' => [$ref, ['type' => 'object', 'nullable' => true, 'enum' => [null]]]], '{"id":1}', true];
        yield '3.0 exclusiveMinimum true, at the bound' => ['3.0.0', ['type' => 'integer', 'minimum' => 5, 'exclusiveMinimum' => true], '5', null];
        yield '3.0 exclusiveMinimum true, above' => ['3.0.0', ['type' => 'integer', 'minimum' => 5, 'exclusiveMinimum' => true], '6', true];
        yield '3.0 exclusiveMinimum false, at the bound' => ['3.0.0', ['type' => 'integer', 'minimum' => 5, 'exclusiveMinimum' => false], '5', true];
        yield '3.0 exclusiveMaximum true, at the bound' => ['3.0.0', ['type' => 'integer', 'maximum' => 5, 'exclusiveMaximum' => true], '5', null];
        yield '3.0 property named nullable is left alone' => ['3.0.0', ['type' => 'object', 'required' => ['nullable'], 'properties' => ['nullable' => ['type' => 'boolean']]], '{"nullable":true}', true];
        yield '3.0 property named nullable, missing' => ['3.0.0', ['type' => 'object', 'required' => ['nullable'], 'properties' => ['nullable' => ['type' => 'boolean']]], '{}', null];
        yield '3.1 type list, null' => ['3.1.0', ['type' => ['string', 'null']], 'null', true];
        yield '3.1 nullable is not a keyword' => ['3.1.0', ['type' => 'string', 'nullable' => true], 'null', null];
        yield '3.1 numeric exclusiveMinimum' => ['3.1.0', ['type' => 'integer', 'exclusiveMinimum' => 5], '5', null];
        yield '3.1 reference' => ['3.1.0', $ref, '{"id":1}', true];
    }

    /**
     * @param array<string, mixed> $schema
     */
    #[Test]
    #[DataProvider('dialects')]
    public function verifyDialect(string $version, array $schema, string $body, ?bool $expected): void
    {
        $specification = [
            'openapi' => $version,
            'paths' => ['/thing' => ['get' => ['responses' => ['200' => self::json($schema)]]]],
            'components' => ['schemas' => ['thing' => ['type' => 'object', 'required' => ['id']]]],
        ];

        if (null === $expected) {
            $this->expectException(OpenApiSchemaMismatchException::class);
        }

        $verifier = OpenApiVerifier::fromSpecification(json_decode((string) json_encode($specification)));
        $this->assertSame($expected, $verifier->verifyResponseBody('get', '/thing', 200, $body));
    }

    #[Test]
    public function translationLeavesTheSpecificationAlone(): void
    {
        $specification = json_decode((string) json_encode([
            'openapi' => '3.0.0',
            'paths' => ['/thing' => ['get' => ['responses' => ['200' => self::json(['$ref' => '#/components/schemas/thing'])]]]],
            'components' => ['schemas' => ['thing' => ['type' => 'string', 'nullable' => true]]],
        ]));
        $before = json_encode($specification);

        $verifier = OpenApiVerifier::fromSpecification($specification);
        $this->assertTrue($verifier->verifyResponseBody('get', '/thing', 200, 'null'));
        $this->assertTrue($verifier->verifyResponseBody('get', '/thing', 200, 'null'));

        $this->assertSame($before, json_encode($specification));
    }

    #[Test]
    public function aValidatorGetsTheSchemaAsWrittenAndTheVersion(): void
    {
        $validator = new class () implements SchemaValidator {
            public ?string $version = null;

            public ?\stdClass $schema = null;

            public function validate(mixed $data, \stdClass $schema, string $openApiVersion): array
            {
                $this->version = $openApiVersion;
                $this->schema = $schema;

                return [new SchemaError('', 'custom', 'always fails')];
            }
        };

        $specification = json_decode((string) json_encode([
            'openapi' => '3.0.3',
            'paths' => ['/thing' => ['get' => ['responses' => ['200' => self::json(['type' => 'string', 'nullable' => true])]]]],
        ]));

        try {
            OpenApiVerifier::fromSpecification($specification, $validator)->verifyResponseBody('get', '/thing', 200, '"x"');
            $this->fail('No exception thrown');
        } catch (OpenApiSchemaMismatchException $openApiSchemaMismatchException) {
            $this->assertSame('custom', $openApiSchemaMismatchException->getErrors()[0]->keyword);
        }

        $this->assertSame('3.0.3', $validator->version);
        $this->assertTrue($validator->schema->nullable);
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
