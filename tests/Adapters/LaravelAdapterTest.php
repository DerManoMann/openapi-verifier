<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests\Adapters;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Facade;
use Radebatz\OpenApi\Verifier\Adapters\Laravel\OpenApiResponseVerifier;

class LaravelAdapterTest extends TestCase
{
    use OpenApiResponseVerifier;

    /** @inheritdoc */
    public function setUp(): void
    {
        parent::setUp();

        $this->registerOpenApiVerifier(null, __DIR__ . '/../specifications/users.yaml');
    }

    /** @test */
    public function passVerification()
    {
        $this->createApplication()
            ->get('router')->get('/users', function () {
                return '{"data":[{"id":1,"name":"joe","email":"joe@cool.com"}]}';
            });

        $response = $this->get('/users');

        $response->assertStatus(200);
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
