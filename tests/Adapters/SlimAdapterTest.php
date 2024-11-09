<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests\Adapters;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Radebatz\OpenApi\Verifier\Adapters\Slim\OpenApiResponseVerifier;
use Slim\App;
use Slim\Factory\AppFactory;

class SlimAdapterTest extends TestCase
{
    use OpenApiResponseVerifier;

    protected function setUp(): void
    {
        if (!class_exists('\\Slim\\App') || version_compare(App::VERSION, '4.0.0', '<')) {
            $this->markTestSkipped('Slim not installed.');
        }
    }

    #[Test]
    public function passVerificationUsers(): void
    {
        $response = $this->runApp('GET', '/users', valid: true);

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function failVerificationUsers(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/required - The property name is required/');

        $this->runApp('GET', '/users', valid: false);
    }

    #[Test]
    public function passVerificationUser(): void
    {
        $response = $this->runApp('GET', '/user/1', valid: true);

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function failVerificationUser(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/required - The property email is required/');

        $this->runApp('GET', '/user/1', valid: false);
    }

    protected function runApp(string $requestMethod, string $requestUri, bool $valid)
    {
        $request = (new Psr17Factory())->createServerRequest($requestMethod, $requestUri);

        AppFactory::setContainer($this->container());
        $app = AppFactory::create();

        // register test route as we do not have an actual app...
        if ($valid) {
            $app->get('/users', function (RequestInterface $request, ResponseInterface $response) {
                $response->getBody()->write('{"data":[{"id":1,"name":"joe","email":"joe@cool.com"}]}');

                return $response;
            });
            $app->get('/user/{id}', function (RequestInterface $request, ResponseInterface $response) {
                $response->getBody()->write('{"data":{"id":1,"name":"joe","email":"joe@cool.com"}}');

                return $response;
            });
        } else {
            $app->get('/users', function (RequestInterface $request, ResponseInterface $response) {
                $response->getBody()->write('{"data":[{"id":1,"email":"joe@cool.com"}]}');

                return $response;
            });
            $app->get('/user/{id}', function (RequestInterface $request, ResponseInterface $response) {
                $response->getBody()->write('{"data":{"id":1,"name":"joe"}}');

                return $response;
            });
        }

        // register OpenApi verifier
        $this->registerOpenApiVerifier($app, __DIR__ . '/../specifications/users.yaml');

        return $app->handle($request);
    }

    protected function container(): ContainerInterface
    {
        return new class() implements ContainerInterface {
            protected $container = [];

            public function get(string $id)
            {
                return $this->container[$id] ?? null;
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->container);
            }

            public function set(string $id, $value)
            {
                $this->container[$id] = $value;
            }
        };
    }
}
