<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests\Integrations\OpenApi;

use OpenApi\Builder;
use OpenApi\Builder\Mode;
use OpenApi\Contracts\PluginInterface;
use OpenApi\Spec as OA;
use OpenApi\Specification;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\Verifier\ExampleRecorder;
use Radebatz\OpenApi\Verifier\Integrations\OpenApi\RecordedExamplesPlugin;

final class RecordedExamplesPluginTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        if (!interface_exists(PluginInterface::class)) {
            $this->markTestSkipped('swagger-php 7.3 or later is needed for plugins');
        }

        $this->directory = sys_get_temp_dir() . '/openapi-verifier-examples-' . bin2hex(random_bytes(4));
        $recorder = new ExampleRecorder($this->directory);
        $recorder->record('get', '/users/{id}', 200, (object) ['id' => 7, 'name' => 'Recorded']);
        $recorder->record('get', '/users', 200, [(object) ['id' => 7]]);
    }

    protected function tearDown(): void
    {
        if (isset($this->directory) && is_dir($this->directory)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }

            rmdir($this->directory);
        }
    }

    #[Test]
    public function aRecordedBodyBecomesTheExample(): void
    {
        $document = $this->build()->toArray();

        $this->assertSame(['id' => 7, 'name' => 'Recorded'], $document['paths']['/users/{id}']['get']['responses'][200]['content']['application/json']['example']);
        $this->assertSame('Written', $document['paths']['/users']['get']['responses'][200]['content']['application/json']['example'], 'an example of its own is kept');
        $this->assertArrayNotHasKey('example', $document['paths']['/users/{id}']['get']['responses'][404]['content']['application/json'], 'nothing was recorded for 404');
    }

    #[Test]
    public function theDirectoryIsRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Builder())->setMode(Mode::SPEC)->withPlugins([RecordedExamplesPlugin::class]);
    }

    private function build(): Builder\Result
    {
        $json = static fn (?OA\Schema $schema = null, mixed $example = null): OA\MediaType => null === $example
            ? new OA\MediaType\Json(schema: $schema ?? new OA\Schema(type: 'object'))
            : new OA\MediaType\Json(schema: $schema ?? new OA\Schema(type: 'array'), example: $example);

        return (new Builder())
            ->setMode(Mode::SPEC)
            ->withPlugins([RecordedExamplesPlugin::class => ['directory' => $this->directory]])
            ->withSpecification(fn (Specification $specification): Specification => $specification->add(
                new OA\Info(title: 'Users', version: '1.0.0'),
                new OA\Operation\Get(path: '/users/{id}', operationId: 'user', parameters: [new OA\Parameter\Path(name: 'id', schema: new OA\Schema(type: 'integer'))], responses: [
                    new OA\Response(response: 200, description: 'A user', content: [$json()]),
                    new OA\Response(response: 404, description: 'Not found', content: [$json()]),
                ]),
                new OA\Operation\Get(path: '/users', operationId: 'users', responses: [
                    new OA\Response(response: 200, description: 'Users', content: [$json(example: 'Written')]),
                ]),
            ))
            ->build();
    }
}
