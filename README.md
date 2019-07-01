# openapi-verifier
[![Build Status](https://travis-ci.org/DerManoMann/openapi-verifier.png)](https://travis-ci.org/DerManoMann/openapi-router)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Introduction
Allows to validate a controller response from your API project against a given [OpenAPI](https://www.openapis.org/)
specification. 

## Requirements
* [PHP 7.1 or higher](http://www.php.net/)

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
### Manual verification
The `VerifiesOpenApi` trait can be used directly and customized in 3 ways in order to provide the reqired OpenApi specifications:
* Overriding the method `getOpenApiSpecificationLoader()` as shown below
* Populating the `$openapiSpecificationLoader` property.
* Creating a property `$openapiSpecification` pointing to the specification file

```php
<?php

namespace Tests\Feature;

use Radebatz\OpenApi\Verifier\VerifiesOpenApi;
use Radebatz\OpenApi\Verifier\OpenApiSpecificationLoader;
use PHPUnit\Framework\TestCase;

class UsersTest extends TestCase
{
    use VerifiesOpenApi;
    
    /** @inheritdoc */
    protected function getOpenApiSpecificationLoader(): ?OpenApiSpecificationLoader
    {
        return new OpenApiSpecificationLoader(__DIR__ . '/specifications/users.yaml');
    }

    /** @test */
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

class UsersTest extends TestCase
{
    use OpenApiResponseVerifier;

    public function setUp(): void
    {
        parent::setUp();

        $this->registerOpenApiVerifier(/* $this->>createApplication() */ /* , [specification filename] */);
    }

    /** @test */
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

```php
<?php

namespace Tests\Functional;

use ...
use PHPUnit\Framework\TestCase;

class BaseTestCase extends TestCase
{
    public function runApp($requestMethod, $requestUri, $requestData = null)
    {
        ...
        
        $app = new App();
        
        // register OpenApi verifier
        $this->registerOpenApiVerifier($app, __DIR__ . '/../specifications/users.yaml');
        
        ...
    }
}
```
```php
<?php

namespace Tests\Functional;

class UsersTest extends BaseTestCase
{
    /** @test */
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
