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

## Basic usage

Example use for a Laravel feature test:

```php
<?php

namespace Tests\Feature;

use Radebatz\OpenApi\Verifier\VerifiesOpenApi;
use Radebatz\OpenApi\Verifier\OpenApiSpecificationLoader;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use VerifiesOpenApi;
    
    protected $specificationLoader;

    protected function getSpecificationLoader(): OpenApiSpecificationLoader
    {
        if (!$this->specificationLoader) {
            $this->specificationLoader = new OpenApiSpecificationLoader(__DIR__ . '/../../openapi.yaml');
        }

        return $this->specificationLoader;
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

## License

The openapi-router project is released under the [MIT license](LICENSE).
