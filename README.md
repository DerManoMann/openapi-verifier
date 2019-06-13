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

**Note:** Examples are based on Laravel 5.

### Example of manually creating the scpecification loader:

The `VerifiesOpenApi` trait can be customized in 3 ways in order to provide the reqired OpenApi specifications:
* Overriding the method `getOpenApiSpecificationLoader()` as shown below
* Populating the `$openapiSpecificationLoader` property.
* Creating a property `$openapiSpecification` pointing to the specification file

```php
<?php

namespace Tests\Feature;

use Radebatz\OpenApi\Verifier\VerifiesOpenApi;
use Radebatz\OpenApi\Verifier\OpenApiSpecificationLoader;
use Tests\TestCase;

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
        $response = $this->get('/users');

        $response->assertOk();
        
        // will throw OpenApiVerificationException if verification fails
        $this->verifyResponse('get', '/users', 200, $response->content());
    }
}

```
### Example using the Laravel adapter:
The adapter will try to resolve the specification dynamically in this order:
* filename passed into `registerOpenApiVerifier()`
* `/tests/openapi.json`
* `/tests/openapi.yaml`
* Generate specification from scratch by scanning the `app` folder

```php
<?php

namespace Tests\Feature;

use Radebatz\OpenApi\Verifier\Adapters\LaravelOpenApiResponseVerifier;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use LaravelOpenApiResponseVerifier;

    public function setUp(): void
    {
        parent::setUp();

        $this->registerOpenApiVerifier(/* specification filename */);
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

## License

The openapi-router project is released under the [MIT license](LICENSE).
