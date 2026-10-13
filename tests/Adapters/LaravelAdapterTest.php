<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests\Adapters;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use Radebatz\OpenApi\Verifier\Adapters\Laravel\OpenApiResponseVerifier;

final class LaravelAdapterTest extends TestCase
{
    use OpenApiResponseVerifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOpenApiVerifier(null, __DIR__ . '/../specifications/users.yaml');
    }

    #[Test]
    public function passVerificationUsers(): void
    {
        $this->app
            ->get('router')->get('/users', function (): string {
                return '{"data":[{"id":1,"name":"joe","email":"joe@cool.com"}]}';
            });

        $response = $this->get('/users');

        $response->assertStatus(200);
    }

    #[Test]
    public function failVerificationUsers(): void
    {
        $this->app
            ->get('router')->get('/users', function (): string {
                return '{"data":[{"id":1,"email":"joe@cool.com"}]}';
            });

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/required - The required properties \(name\) are missing/');
        $this->get('/users');
    }

    #[Test]
    public function passVerificationUser(): void
    {
        $this->app
            ->get('router')->get('/user/{id}', function (): string {
                return '{"data":{"id":1,"name":"joe","email":"joe@cool.com"}}';
            });

        $response = $this->get('/user/1');

        $response->assertStatus(200);
    }

    #[Test]
    public function failVerificationUser(): void
    {
        $this->app
            ->get('router')->get('/user/{id}', function (): string {
                return '{"data":{"id":1,"name":"joe"}}';
            });

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/required - The required properties \(email\) are missing/');

        $this->get('/user/1');
    }

    /**
     * Laravel's `setUp()` calls this and keeps the result in `$this->app`.
     */
    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__ . '/../../vendor/laravel/laravel/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $app['config']->set([
            'app.environment' => 'local',
            'app.debug' => true,
        ]);
        Facade::setFacadeApplication($app);

        return $app;
    }
}
