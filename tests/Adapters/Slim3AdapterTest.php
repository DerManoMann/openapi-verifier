<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests\Adapters;

use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\Verifier\Adapters\Slim\OpenApiResponseVerifier;
use Slim\App;
use Slim\Http\Environment;
use Slim\Http\Request;
use Slim\Http\Response;

class Slim3AdapterTest extends TestCase
{
    use OpenApiResponseVerifier;

    protected function setUp(): void
    {
        if (!class_exists('\\Slim\\App') || version_compare(App::VERSION, '4.0.0', '<')) {
            $this->markTestSkipped('not installed.');
        }
    }

    /** @test */
    public function passVerification()
    {
        $response = $this->runApp('GET', '/users');

        $this->assertEquals(200, $response->getStatusCode());
    }

    protected function runApp($requestMethod, $requestUri, $requestData = null)
    {
        $request = Request::createFromEnvironment(Environment::mock([
            'REQUEST_METHOD' => $requestMethod,
            'REQUEST_URI' => $requestUri,
        ]));

        if ($requestData) {
            $request = $request->withParsedBody($requestData);
        }

        $app = new App();

        // register test route as we do not have an actual app...
        $app->get('/users', function () {
            return '{"data":[{"id":1,"name":"joe","email":"joe@cool.com"}]}';
        });

        // register OpenApi verifier
        $this->registerOpenApiVerifier($app, __DIR__ . '/../specifications/users.yaml');

        return $app->process($request, new Response());
    }
}
