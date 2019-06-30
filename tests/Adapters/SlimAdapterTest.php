<?php declare(strict_types=1);

namespace Tests\Functional;

use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Http\Environment;
use Slim\Http\Request;
use Slim\Http\Response;

class SlimAdapterTest extends TestCase
{
    protected function runApp($requestMethod, $requestUri, $requestData = null)
    {
        $request = Request::createFromEnvironment(Environment::mock([
            'REQUEST_METHOD' => $requestMethod,
            'REQUEST_URI' => $requestUri
        ]));

        if ($requestData) {
            $request = $request->withParsedBody($requestData);
        }

        $app = new App();
        $response = $app->process($request, new Response());

        return $response;
    }
}
