<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests\Adapters;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use Radebatz\OpenApi\Verifier\Adapters\Laravel\OpenApiResponseVerifier;

class LaravelAdapterTest extends LaravelTestCase
{
    use OpenApiResponseVerifier;

    /** @inheritdoc */
    public function setUp(): void
    {
        if (!class_exists('\\Illuminate\\Foundation\\Application')) {
            $this->markTestSkipped('Laravel not installed.');
        }

        parent::setUp();

        $this->registerOpenApiVerifier(null, __DIR__ . '/../specifications/users.yaml');
    }

    #[Test]
    public function passVerificationUsers(): void
    {
        $this->createApplication()
            ->get('router')->get('/users', function () {
                return '{"data":[{"id":1,"name":"joe","email":"joe@cool.com"}]}';
            });

        $response = $this->get('/users');

        $response->assertStatus(200);
    }

    #[Test]
    public function failVerificationUsers(): void
    {
        $this->createApplication()
            ->get('router')->get('/users', function () {
                return '{"data":[{"id":1,"email":"joe@cool.com"}]}';
            });

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/required - The property name is required/');
        $this->get('/users');
    }

    #[Test]
    public function passVerificationUser(): void
    {
        $this->createApplication()
            ->get('router')->get('/user/{id}', function () {
                return '{"data":{"id":1,"name":"joe","email":"joe@cool.com"}}';
            });

        $response = $this->get('/user/1');

        $response->assertStatus(200);
    }

    #[Test]
    public function failVerificationUser(): void
    {
        $this->createApplication()
            ->get('router')->get('/user/{id}', function () {
                return '{"data":{"id":1,"name":"joe"}}';
            });

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/required - The property email is required/');

        $this->get('/user/1');
    }

    /** @inheritdoc */
    public function createApplication()
    {
        if (!$this->app) {
            /** @var Application $app */
            $app = require __DIR__ . '/../../vendor/laravel/laravel/bootstrap/app.php';
            $app->make(Kernel::class)->bootstrap();
            $app['config']->set([
                'app.environment' => 'local',
                'app.debug' => true,
            ]);
            Facade::setFacadeApplication($app);

            $this->app = $app;
        }

        return $this->app;
    }
}
