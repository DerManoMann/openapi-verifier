<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\Verifier\OpenApiSpecificationLoader;

class OpenApiSpecificationLoaderTest extends TestCase
{
    /**
     * @return iterable<array<mixed>>
     */
    public static function specifications(): iterable
    {
        return [
            [__DIR__ . '/specifications/users.json', true],
            [__DIR__ . '/specifications/users.yaml', true],
            [__DIR__ . '/specifications/nope.json', false],
            [__DIR__ . '/specifications/nope.yaml', false],
            [__DIR__ . '/specifications/nope.yml', false],
            [new \stdClass(), true],
            [[], false],
        ];
    }

    #[Test]
    #[DataProvider('specifications')]
    public function loadSpecification(mixed $filename, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(\InvalidArgumentException::class);
        }

        $specificationLoader = new OpenApiSpecificationLoader($filename);

        // the fixtures are 3.0.0, and a document that does not say counts as 3.0.0 too
        $this->assertSame('3.0.0', $specificationLoader->getOpenApiVersion());
    }

    /**
     * @return iterable<array<mixed>>
     */
    public static function responseSchemas(): iterable
    {
        return [
            'ucmethod-json-ok' => [__DIR__ . '/specifications/users.json', 'GET', '/users', 200, true],
            'lcmethod-json-ok' =>[__DIR__ . '/specifications/users.json', 'get', '/users', 200, true],
            'yaml-ok' => [__DIR__ . '/specifications/users.yaml', 'get', '/users', 200, true],
            'path-no-match' => [__DIR__ . '/specifications/users.yaml', 'get', '/foo', 200, false],
            'status-no-match' => [__DIR__ . '/specifications/users.json', 'get', '/users', 404, false],
            'status-no-schema' => [__DIR__ . '/specifications/users.yaml', 'get', '/users', 401, false],
        ];
    }

    #[Test]
    #[DataProvider('responseSchemas')]
    public function getResponseSchemaFor(string $specification, string $method, string $path, int $statusCode, bool $valid): void
    {
        $specificationLoader = new OpenApiSpecificationLoader($specification);

        $schema = $specificationLoader->getResponseSchemaFor($method, $path, $statusCode);

        if ($valid) {
            $this->assertNotNull($schema);
            // carries the components, so its references resolve
            $this->assertObjectHasProperty('components', $schema);
        } else {
            $this->assertNull($schema);
        }
    }
}
