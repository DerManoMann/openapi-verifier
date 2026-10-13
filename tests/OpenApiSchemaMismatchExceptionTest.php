<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\Verifier\OpenApiSchemaMismatchException;
use Radebatz\OpenApi\Verifier\Schema\SchemaError;

final class OpenApiSchemaMismatchExceptionTest extends TestCase
{
    #[Test]
    public function getErrorsReturnsSetData(): void
    {
        $errors = [new SchemaError('/data/0/name', 'type', 'some-message')];

        $this->assertSame($errors, (new OpenApiSchemaMismatchException())->setErrors($errors)->getErrors());
    }

    /**
     * @return iterable<array<mixed>>
     */
    public static function errors(): iterable
    {
        yield 'no errors' => [[], null];

        yield 'single error' => [
            [new SchemaError('/data/name', 'type', 'some-message')],
            <<<ERROR
            type - some-message
              - /data/name
            ERROR,
        ];

        yield 'whole document' => [
            [new SchemaError('', 'type', 'some-message')],
            <<<ERROR
            type - some-message
              - /
            ERROR,
        ];

        yield 'same keyword and message, two pointers' => [
            [new SchemaError('/data/b', 'type', 'some-message'), new SchemaError('/data/a', 'type', 'some-message')],
            <<<ERROR
            type - some-message
              - /data/b
              - /data/a
            ERROR,
        ];

        yield 'list items share a line' => [
            [
                new SchemaError('/data/0/name', 'required', 'some-message'),
                new SchemaError('/data/1/name', 'required', 'some-message'),
                new SchemaError('/data/12/name', 'required', 'some-message'),
            ],
            <<<ERROR
            required - some-message
              - /data/*/name (2 more)
            ERROR,
        ];

        yield 'different messages are separate groups' => [
            [new SchemaError('/a', 'type', 'one'), new SchemaError('/b', 'type', 'two')],
            <<<ERROR
            type - one
              - /a
            type - two
              - /b
            ERROR,
        ];
    }

    /**
     * @param list<SchemaError> $errors
     */
    #[Test]
    #[DataProvider('errors')]
    public function getErrorSummary(array $errors, ?string $expected): void
    {
        $this->assertSame($expected, (new OpenApiSchemaMismatchException())->setErrors($errors)->getErrorSummary());
    }
}
