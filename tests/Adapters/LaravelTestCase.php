<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests\Adapters;

if (class_exists('\\Illuminate\\Foundation\\Testing\\TestCase')) {
    abstract class LaravelTestCase extends \Illuminate\Foundation\Testing\TestCase
    {
    }
} else {
    class LaravelTestCase extends \PHPUnit\Framework\TestCase
    {
    }
}
