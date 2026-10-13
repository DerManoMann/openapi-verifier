<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests\Adapters;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\Verifier\Adapters\AbstractOpenApiResponseVerifier;
use Radebatz\OpenApi\Verifier\VerifiesOpenApi;

class AbstractOpenApiResponseVerifierTest extends TestCase
{
    use AbstractOpenApiResponseVerifier;
    use VerifiesOpenApi;

    protected string $appRoot;

    protected ?string $tmpDir = null;

    protected function tearDown(): void
    {
        if ($this->tmpDir) {
            @unlink($this->tmpDir . '/tests/openapi.json');
            @rmdir($this->tmpDir . '/tests');
            @rmdir($this->tmpDir);
        }
    }

    protected function getAppRoot()
    {
        return $this->appRoot;
    }

    protected function assertVerifiesUsers(): void
    {
        $this->assertNotNull($this->getOpenApiSpecificationLoader());
        $this->assertTrue($this->verifyOpenApiResponseBody(
            'get',
            '/users',
            200,
            '{"data":[{"id":1,"name":"joe","email":"joe@cool.com"}]}'
        ));
    }

    #[Test]
    public function explicitSpecification()
    {
        $this->appRoot = '/does/not/exist';

        $this->prepareOpenApiSpecificationLoader('src', __DIR__ . '/../specifications/users.json');

        $this->assertVerifiesUsers();
    }

    #[Test]
    public function discoveredSpecification()
    {
        $this->tmpDir = sys_get_temp_dir() . '/openapi-verifier-' . uniqid();
        mkdir($this->tmpDir . '/tests', 0777, true);
        copy(__DIR__ . '/../specifications/users.json', $this->tmpDir . '/tests/openapi.json');
        $this->appRoot = $this->tmpDir;

        $this->prepareOpenApiSpecificationLoader('src');

        $this->assertVerifiesUsers();
    }

    #[Test]
    public function generatedSpecification()
    {
        // no tests/openapi.{json,yaml} under tests/, so this generates from tests/Fixtures
        $this->appRoot = dirname(__DIR__);

        $this->prepareOpenApiSpecificationLoader('Fixtures');

        $this->assertVerifiesUsers();
    }
}
