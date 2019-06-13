<?php

namespace Radebatz\OpenApi\Verifier\Tests;

use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\Verifier\OpenApiSpecificationLoader;

class OpenApiSpecificationLoaderTest extends TestCase
{
    public function specifications()
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

    /**
     * @test
     * @dataProvider specifications
     */
    public function loadSpecification($filename, $valid)
    {
        if (!$valid) {
            $this->expectException(\InvalidArgumentException::class);
        }

        $specificationLoader = new OpenApiSpecificationLoader($filename);

        $this->assertIsObject($specificationLoader);
    }

    public function schemaUrls()
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

    /**
     * @test
     * @dataProvider schemaUrls
     */
    public function getSchemaUrlFor($specification, $method, $path, $statusCode, $valid)
    {
        $specificationLoader = new OpenApiSpecificationLoader($specification);

        $schemaUrl = $specificationLoader->getResponseSchemaUrlFor($method, $path, $statusCode);

        $assert = $valid ? 'assertNotNull' : 'assertNull';
        $this->{$assert}($schemaUrl);
    }
}
