<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests;

use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\Verifier\ExampleRecorder;
use Radebatz\OpenApi\Verifier\OpenApiSchemaMismatchException;
use Radebatz\OpenApi\Verifier\OpenApiSpecificationLoader;
use Radebatz\OpenApi\Verifier\OpenApiVerifier;

final class ExampleRecorderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/openapi-verifier-examples-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }

            rmdir($this->directory);
        }

        putenv(ExampleRecorder::ENV_EXAMPLES);
        putenv(ExampleRecorder::ENV_EXAMPLES_SCRUB);
    }

    #[Test]
    public function aBodyIsWrittenUnderItsMethodPathAndStatus(): void
    {
        (new ExampleRecorder($this->directory))->record('GET', '/users/{id}', 200, (object) ['id' => 1]);

        $this->assertSame("{\n    \"id\": 1\n}\n", file_get_contents($this->directory . '/get/users/{id}/200.json'));
        $this->assertSame($this->directory . '/post/201.json', ExampleRecorder::filename($this->directory, 'POST', '/', 201));
    }

    #[Test]
    public function theScrubbedValuesAreReplaced(): void
    {
        $data = json_decode('{"token":"secret","items":[{"email":"a@example.com","id":1},{"email":"b@example.com","id":2}]}');

        (new ExampleRecorder($this->directory, ['/token', '/items/*/email']))->record('get', '/users', 200, $data);

        $this->assertSame(
            ['token' => '***', 'items' => [['email' => '***', 'id' => 1], ['email' => '***', 'id' => 2]]],
            json_decode((string) file_get_contents($this->directory . '/get/users/200.json'), true),
        );
    }

    #[Test]
    public function theEnvironmentSwitchesRecordingOn(): void
    {
        $this->assertNull(ExampleRecorder::fromEnvironment());

        putenv(ExampleRecorder::ENV_EXAMPLES . '=' . $this->directory);
        putenv(ExampleRecorder::ENV_EXAMPLES_SCRUB . '=/token');
        $recorder = ExampleRecorder::fromEnvironment();
        $this->assertInstanceOf(ExampleRecorder::class, $recorder);
        $recorder->record('get', '/users', 200, (object) ['token' => 'secret']);

        $this->assertSame(['token' => '***'], json_decode((string) file_get_contents($this->directory . '/get/users/200.json'), true));
    }

    #[Test]
    public function theVerifierRecordsWhatItVerified(): void
    {
        $loader = new OpenApiSpecificationLoader((object) json_decode((string) json_encode([
            'openapi' => '3.1.0',
            'paths' => ['/users/{id}' => ['get' => ['responses' => ['200' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['id']]]]]]]]],
        ])));
        $verifier = new OpenApiVerifier($loader, exampleRecorder: new ExampleRecorder($this->directory));

        $this->assertTrue($verifier->verify(new Request('GET', '/users/7'), new Response(200, ['Content-Type' => 'application/json'], '{"id":7}')));
        $this->assertSame(['id' => 7], json_decode((string) file_get_contents($this->directory . '/get/users/{id}/200.json'), true));

        try {
            $verifier->verify(new Request('GET', '/users/8'), new Response(200, ['Content-Type' => 'application/json'], '{"name":"x"}'));
            $this->fail('expected a mismatch');
        } catch (OpenApiSchemaMismatchException) {
            $this->assertSame(['id' => 7], json_decode((string) file_get_contents($this->directory . '/get/users/{id}/200.json'), true), 'a mismatch is not recorded');
        }
    }
}
