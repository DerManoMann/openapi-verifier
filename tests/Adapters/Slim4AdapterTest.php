<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests\Adapters;

use DI\ContainerBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Radebatz\OpenApi\Verifier\Adapters\Slim\OpenApiResponseVerifier;
use Slim\App;
use Slim\Factory\AppFactory;

class Slim4AdapterTest extends TestCase
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
        $request = (new Psr17Factory())->createServerRequest($requestMethod, $requestUri);

        if ($requestData) {
            $request = $request->withParsedBody($requestData);
        }

        AppFactory::setContainer((new ContainerBuilder())->build());
        $app = AppFactory::create();

        // register test route as we do not have an actual app...
        $app->get('/users', function (RequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write('{"data":[{"id":1,"name":"joe","email":"joe@cool.com"}]}');

            return $response;
        });

        // register OpenApi verifier
        $this->registerOpenApiVerifier($app, __DIR__ . '/../specifications/users.yaml');

        return $app->handle($request);
    }
}
