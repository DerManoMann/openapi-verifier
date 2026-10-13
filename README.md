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
  Without a match it falls back to `application/json`, so a JSON body labelled `text/html` is still validated.

Bodies are validated with [opis/json-schema](https://github.com/opis/json-schema), which supports JSON Schema
2020-12, the dialect of OpenAPI 3.1. OpenAPI 3.0 documents are translated first: `nullable` and the boolean form of
`exclusiveMinimum` and `exclusiveMaximum` become their 2020-12 equivalents.

Another engine can be used by passing a `Schema\SchemaValidator` to the verifier. It gets each schema as the document
writes it, together with the document's OpenAPI version, so it can handle 3.0 its own way or use
`Schema\OpenApi30Translator`.

A mismatch carries one `Schema\SchemaError` per problem, with a JSON pointer into the body, the failing keyword and a
message. `getErrorSummary()` groups them:

```
required - The required properties (name) are missing
  - /data/* (2 more)
```

### What is verified
Only response bodies are verified. Requests, parameters and response headers are not checked.

A response is only verified if the specification has a schema for it. A path, method or status code without one is
skipped: `verify()` returns `false` and the adapters do nothing.

The body is validated against that schema as written:
* `required` properties and types are checked.
* Formats that opis knows, such as `date-time` and `email`, are checked.
* Properties the schema does not declare are allowed, unless it sets `additionalProperties: false`.
* A body that is not valid JSON is validated as `null`, so it fails. With a `Content-Type` that is not JSON it is
  skipped instead.

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

## Upgrading from 2.x
**Requirements**
* PHP 8.2 or higher.
* `opis/json-schema` replaces `justinrainbow/json-schema`.
* `symfony/psr-http-message-bridge` 7 is allowed alongside 6.

**Validation**
* A `$ref` must be a JSON pointer such as `#/components/schemas/User`. `justinrainbow` also accepted
  `#components/schemas/User`, which now fails as an unresolved reference.
* Messages are opis's: "The required properties (name) are missing" where it was "The property name is required".
* OpenAPI 3.1 documents are no longer rewritten. A `nullable` in one has no effect, as in the specification.

**API**
* `OpenApiSchemaMismatchException::getErrors()` returns `Schema\SchemaError` objects.
* `OpenApiSpecificationLoader::getResponseSchemaUrlFor()` and `findPath()` are removed. `getResponseSchemaFor()`
  returns the schema with its components attached.
* `VerifiesOpenApi::verifyOpenApi()` takes a `RequestInterface`.
* `VerifiesOpenApi::failSchemaMismatch()` returns `void` and joins its parts with newlines.
* `$openapiSpecification` is `object|string|null`, with a `null` default.

**Adapters**
* Nothing is put in a container any more, and `OPENAPI_VERFIER_CONTAINER_KEY` is gone from both middleware.
* `Adapters\Slim\OpenApiVerifierMiddleware` and `Adapters\PSR17Middleware` are removed. The
  [PSR-15 middleware](#psr-15-middleware) replaces the first.
* The Slim adapter's `registerOpenApiVerifier()` requires an `App`.
* The Laravel middleware is `final` and takes an `OpenApiVerifier` in its constructor.
* The Slim verifier can be registered before or after the routing middleware.

## License
The openapi-verifier project is released under the [MIT license](LICENSE).
