<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\Verifier\OpenApiSchemaMismatchException;
use Radebatz\OpenApi\Verifier\OpenApiSpecificationLoader;
use Radebatz\OpenApi\Verifier\VerifiesOpenApi;

class VerifiesOpenApiTest extends TestCase
{
    use VerifiesOpenApi;

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
    public function verifyBody($method, $path, $statusCode, $content, $isValid, $isVerified)
    {
        if (!$isValid) {
            $this->expectException(OpenApiSchemaMismatchException::class);

            $verified = $this->verifyOpenApiResponseBody($method, $path, $statusCode, $content);
        } else {
            try {
                $verified = $this->verifyOpenApiResponseBody($method, $path, $statusCode, $content);
            } catch (OpenApiSchemaMismatchException $oave) {
                $this->fail(sprintf('%s:%s%s', $oave->getMessage(), PHP_EOL, $oave->getErrorSummary()));
            }
        }

        $this->assertEquals($isVerified, $verified);
    }

    /** @test */
    public function verifyRequestResponse()
    {
        $psr17Factory = new Psr17Factory();
        $creator = new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

        $request = $creator->fromArrays(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users']);
        $response = $psr17Factory
            ->createResponse(200)
            ->withBody(
                $psr17Factory
                ->createStream('{"data":[{"id":1,"name":"joe","email":"joe@cool.com"}]}')
            );

        $verified = $this->verifyOpenApi($request, $response);

        $this->assertTrue($verified);
    }

    /** {@inheritdoc} */
    protected function getOpenApiSpecificationLoader(): ?OpenApiSpecificationLoader
    {
        return new OpenApiSpecificationLoader(__DIR__ . '/specifications/users.yaml');
    }
}
