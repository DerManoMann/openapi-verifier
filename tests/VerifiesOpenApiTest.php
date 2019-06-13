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
            'invalid-json' => ['get', '/users', 200, 'xxx', false, false],
            'invalid-path' => ['get', '/xxxxx', 200, 'xxx', true, false],
            'no-schema-invalid-json' => ['get', '/users', 401, 'xxx', true, false],
            'verified-bad' => ['get', '/users', 200, '{"data":[{}]}', false, true],
            'verified-ok' => ['get', '/users', 200, '{"data":[{"id":1,"name":"joe","email":"joe@cool.com"}]}', true, true],
            'verified-nullable-ok' => ['get', '/users', 200, '{"data":[{"id":1,"name":"joe","email":"joe@cool.com", "dob": null}]}', true, true],
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

            $verified = $this->verifyResponse($method, $path, $statusCode, $content);
        } else {
            try {
                $verified = $this->verifyResponse($method, $path, $statusCode, $content);
            } catch (OpenApiVerificationException $oave) {
                $this->fail(sprintf('%s:%s%s', $oave->getMessage(), PHP_EOL, $oave->getErrorSummary()));
            }
        }

        $this->assertEquals($isVerified, $verified);
    }
}
