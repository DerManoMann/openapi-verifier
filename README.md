# openapi-verifier
[![build](https://github.com/DerManoMann/openapi-verifier/actions/workflows/build.yml/badge.svg)](https://github.com/DerManoMann/openapi-verifier/actions/workflows/build.yml)
[![Coverage Status](https://coveralls.io/repos/github/DerManoMann/openapi-verifier/badge.svg)](https://coveralls.io/github/DerManoMann/openapi-verifier)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Introduction
Allows to validate a controller response from your API project against a given [OpenAPI](https://www.openapis.org/)
specification. 

## Requirements
* [PHP 8.2 or higher](http://www.php.net/)

## Installation
You can use **composer** or simply **download the release**.

**Composer**

The preferred method is via [composer](https://getcomposer.org). Follow the
[installation instructions](https://getcomposer.org/doc/00-intro.md) if you do not already have
composer installed.

Once composer is installed, execute the following command in your project root to install this library:

```sh
composer require radebatz/openapi-verifier
```
After that all required classes should be availabe in your project to add routing support.

## Usage
### The verifier
`OpenApiVerifier` checks a response against the specification. It needs no test case and no framework,
so anything that sees a request and its response can use it.

```php
use Radebatz\OpenApi\Verifier\OpenApiVerifier;

$verifier = OpenApiVerifier::fromSpecification(__DIR__ . '/specifications/users.yaml');

// throws `OpenApiSchemaMismatchException` if the body does not match;
// returns `false` if the specification has no schema for this response
$verifier->verify($request, $response);
```

How the response is looked up:
* **Path**: a literal match first, then the path templates, so `/user/1` finds `/user/{id}`. When several
  templates match, the one with the most literal characters wins. A base path from `servers` is stripped
  first, and the optional third argument of `verify()` skips the matching and uses the given template.
* **Status code**: the exact code, then its range (`2XX`), then `default`.
* **Media type**: the response's `Content-Type`, including `+json` types such as `application/problem+json`.
  Without a match it falls back to `application/json`. Only JSON bodies are validated.

### Guzzle and PSR-18 clients
Verifying on the client side needs no framework. It works against a running server, a mock
handler or anything else a client can talk to.

**Guzzle**: push the middleware onto the handler stack. A response that does not match makes the
request throw `OpenApiSchemaMismatchException`.

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Radebatz\OpenApi\Verifier\Adapters\Guzzle\OpenApiVerifierMiddleware;
use Radebatz\OpenApi\Verifier\OpenApiVerifier;

$stack = HandlerStack::create();
$stack->push(new OpenApiVerifierMiddleware(OpenApiVerifier::fromSpecification('openapi.yaml')), 'openapi-verifier');

$client = new Client(['handler' => $stack, 'base_uri' => 'http://localhost:8080']);
$client->get('/user/1'); // verified against `/user/{id}`
```

**PSR-18**: wrap any `Psr\Http\Client\ClientInterface`, Guzzle's included.

```php
use Radebatz\OpenApi\Verifier\Adapters\Psr18\OpenApiVerifyingClient;

$client = new OpenApiVerifyingClient($psr18Client, OpenApiVerifier::fromSpecification('openapi.yaml'));
$response = $client->sendRequest($request);
```

Either way the response body is left at its start, so it can be read as usual afterwards.

### Manual verification
The `VerifiesOpenApi` trait can be used directly and customized in 3 ways in order to provide the reqired OpenApi specifications:
* Overriding the method `getOpenApiSpecificationLoader()` as shown below
* Populating the `$openapiSpecificationLoader` property.
* Setting a property `$openapiSpecification` pointing to the specification file

```php
<?php

namespace Tests\Feature;

use Radebatz\OpenApi\Verifier\VerifiesOpenApi;
use Radebatz\OpenApi\Verifier\OpenApiSpecificationLoader;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UsersTest extends TestCase
{
    use VerifiesOpenApi;
    
    /** @inheritdoc */
    protected function getOpenApiSpecificationLoader(): ?OpenApiSpecificationLoader
    {
        return new OpenApiSpecificationLoader(__DIR__ . '/specifications/users.yaml');
    }

    #[Test]
    public function index()
    {
        // PSR client
        $client = $this->client();
        $response = $client->get('/users');

        $this->assertEquals(200, $response->getStatusCode());
        
        // will throw OpenApiSchemaMismatchException if verification fails
        $this->verifyOpenApiResponseBody('get', '/users', 200, (string) $response->getBody());
    }
}

```
### PSR-15 middleware
Any PSR-15 stack (Slim, Mezzio and others) can verify its responses with the middleware. A response that does not
match throws `OpenApiSchemaMismatchException`. An optional callback is called with the exception and the response
first, to report the mismatch in a way of your own.

```php
use Radebatz\OpenApi\Verifier\Adapters\Psr15\OpenApiVerifierMiddleware;
use Radebatz\OpenApi\Verifier\OpenApiVerifier;

$app->add(new OpenApiVerifierMiddleware(OpenApiVerifier::fromSpecification('openapi.yaml')));
```

### Laravel adapter
The adapter will try to resolve the specification dynamically in this order:
* filename passed into `registerOpenApiVerifier()`
* `/tests/openapi.json`
* `/tests/openapi.yaml`
* Generate specification from scratch by scanning the `app` folder

The code expects to be in the context of a Laravel `Test\TestCase`.

```php
<?php

namespace Tests\Feature;

use Radebatz\OpenApi\Verifier\Adapters\Laravel\OpenApiResponseVerifier;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UsersTest extends TestCase
{
    use OpenApiResponseVerifier;

    public function setUp(): void
    {
        parent::setUp();

        $this->registerOpenApiVerifier(/* $this->>createApplication() */ /* , [specification filename] */);
    }

    #[Test]
    public function index()
    {
        // will `fail` if schema found and validation fails
        $response = $this->get('/users');

        $response->assertOk();
    }
}

```

### Slim adapter
The adapter will try to resolve the specification dynamically in this order:
* filename passed into `registerOpenApiVerifier()`
* `/tests/openapi.json`
* `/tests/openapi.yaml`
* Generate specification from scratch by scanning the `src` folder

Simplest way is to register the verifier in the `Tests\Functional\BaseTestCase`.

The adapter adds the PSR-15 middleware below, and a mismatch fails the test with the error summary and the response body.

```php
<?php

namespace Tests\Functional;

use ...
use Radebatz\OpenApi\Verifier\Adapters\Slim\OpenApiResponseVerifier;
use PHPUnit\Framework\TestCase;

class BaseTestCase extends TestCase
{
    use OpenApiResponseVerifier;

    public function runApp($requestMethod, $requestUri, $requestData = null)
    {
        ...
        
        $app = new App();
        
        // register OpenApi verifier, before or after the routing middleware
        $this->registerOpenApiVerifier($app, __DIR__ . '/../specifications/users.yaml');
        $app->addRoutingMiddleware();
        
        // ...
    }
}
```
```php
<?php

namespace Tests\Functional;

use PHPUnit\Framework\Attributes\Test;

class UsersTest extends BaseTestCase
{
    #[Test]
    public function index()
    {
        // will `fail` if schema found and validation fails
        $response = $this->runApp('GET', '/users');

        $this->assertEquals(200, $response->getStatusCode());
    }
}
```

## License
The openapi-verifier project is released under the [MIT license](LICENSE).
