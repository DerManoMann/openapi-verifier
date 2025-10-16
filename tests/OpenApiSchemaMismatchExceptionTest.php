<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Radebatz\OpenApi\Verifier\OpenApiSchemaMismatchException;

class OpenApiSchemaMismatchExceptionTest extends TestCase
{
    private OpenApiSchemaMismatchException $subject;

    protected function setUp(): void
    {
        $this->subject = new OpenApiSchemaMismatchException();
    }

    #[Test]
    public function getErrorsReturnsSetData(): void
    {
        $value = [[
            'property' => 'some-property',
            'pointer' => 'some-pointer',
            'message' => 'some-message',
            'constraint' => 'some-constraint',
            'context' => PHP_INT_MAX,
        ]];

        $this->subject->setErrors($value);

        static::assertSame(
            $value,
            $this->subject->getErrors(),
        );
    }

    #[Test]
    #[DataProvider('errorDataProvider')]
    public function getErrorSummaryReturnsExpectedResult(
        array $errors,
        null|string $expected_result,
    ): void {
        $this->subject->setErrors($errors);

        static::assertSame(
            $expected_result,
            $this->subject->getErrorSummary(),
        );
    }

    public static function errorDataProvider(): \Generator
    {
        // no errors, no message
        yield [
            [],
            null,
        ];

        // single error; JsonSchema 5.x
        yield [
            [
                [
                    'property' => 'some-property',
                    'pointer' => 'some-pointer',
                    'message' => 'some-message',
                    'constraint' => 'some-constraint',
                    'context' => PHP_INT_MAX,
                ],
            ],
            <<<ERROR
            some-constraint - some-message
              - some-property
            ERROR,
        ];

        // single error; JsonSchema 6
        yield [
            [
                [
                    'property' => 'some-property',
                    'pointer' => 'some-pointer',
                    'message' => 'some-message',
                    'constraint' => ['name' => 'some-constraint', 'params' => []],
                    'context' => PHP_INT_MAX,
                ],
            ],
            <<<ERROR
            some-constraint - some-message
              - some-property
            ERROR,
        ];

        // multiple errors
        yield [
            [
                [
                    'property' => 'some-property2',
                    'pointer' => 'some-pointer2',
                    'message' => 'some-message',
                    'constraint' => ['name' => 'some-constraint', 'params' => []],
                    'context' => PHP_INT_MAX,
                ],
                [
                    'property' => 'some-property',
                    'pointer' => 'some-pointer',
                    'message' => 'some-message',
                    'constraint' => ['name' => 'some-constraint', 'params' => []],
                    'context' => PHP_INT_MAX,
                ],
            ],
            <<<ERROR
            some-constraint - some-message
              - some-property2
              - some-property
            ERROR,
        ];

        // multiple errors having the same property
        yield [
            [
                [
                    'property' => 'some-property[2]',
                    'pointer' => 'some-pointer2',
                    'message' => 'some-message',
                    'constraint' => ['name' => 'some-constraint', 'params' => []],
                    'context' => abs(PHP_INT_MAX),
                ],
                [
                    'property' => 'some-property[2]',
                    'pointer' => 'some-pointer',
                    'message' => 'some-message',
                    'constraint' => ['name' => 'some-constraint', 'params' => []],
                    'context' => PHP_INT_MAX,
                ],
            ],
            <<<ERROR
            some-constraint - some-message
              - some-property[*] (1 more)
            ERROR,
        ];
    }
}
