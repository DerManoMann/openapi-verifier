<?php

namespace Radebatz\OpenApi\Verifier\Tests;

use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\Verifier\OpenApiSpecificationLoader;
use Radebatz\OpenApi\Verifier\OpenApiVerificationException;
use Radebatz\OpenApi\Verifier\VerifiesOpenApi;

class VerifiesOpenApiTest extends TestCase
{
    use VerifiesOpenApi;

    /** @inheritdoc */
    protected function getOpenApiSpecificationLoader(): ?OpenApiSpecificationLoader
    {
        return new OpenApiSpecificationLoader(__DIR__ . '/specifications/users.yaml');
    }

    public function responses()
    {
        return [
            ['get', '/users', 200, 'xxx', false, false],
            ['get', '/xxxxx', 200, 'xxx', true, false],
            ['get', '/users', 401, 'xxx', true, false],
            ['get', '/users', 200, '{"data":[{}]}', false, true],
            ['get', '/users', 200, '{"data":[{"id":1,"name":"joe","email":"joe@cool.com"}]}', true, true],
        ];
    }

    /**
     * @test
     * @dataProvider responses
     */
    public function verify($method, $path, $statusCode, $content, $isValid, $isVerified)
    {
        if (!$isValid) {
            $this->expectException(OpenApiVerificationException::class);
        }

        $verified = $this->verifyResponse($method, $path, $statusCode, $content);

        $this->assertEquals($isVerified, $verified);
    }
}
