<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier;

/**
 * @phpstan-type JsonSchemaValidationError array{
 *  property: string,
 *  pointer: string,
 *  message: string,
 *  constraint: string|array{name: string, params: array<mixed>},
 *  context: int
 * }
 */
class OpenApiSchemaMismatchException extends \Exception
{
    /** @var list<JsonSchemaValidationError> */
    protected array $errors = [];

    /**
     * @return list<JsonSchemaValidationError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param list<JsonSchemaValidationError> $errors
     */
    public function setErrors(array $errors): OpenApiSchemaMismatchException
    {
        $this->errors = $errors;

        return $this;
    }

    public function getErrorSummary(): ?string
    {
        if (!$this->errors) {
            return null;
        }

        // sanitize
        $errorTypeMap = [];
        foreach ($this->errors as $error) {
            $wildcarded = preg_replace('/\[[0-9]+\]/', '[*]', $error['property']);

            // ensures compatibility with both, JsonSchema 5.x and 6.x
            $constraintName = $error['constraint']['name']
                ?? $error['constraint'];

            $errorType = sprintf('%s|%s', $constraintName, $error['message']);
            if (!array_key_exists($errorType, $errorTypeMap)) {
                $errorTypeMap[$errorType] = [
                    'properties' => [
                        $wildcarded => 1,
                    ],
                    'constraint' => $constraintName,
                    'message' => $error['message'],
                ];
            } else {
                // track usage of the same wildcard-property
                if (!array_key_exists($wildcarded, $errorTypeMap[$errorType]['properties'])) {
                    $errorTypeMap[$errorType]['properties'][$wildcarded] = 1;
                } else {
                    $errorTypeMap[$errorType]['properties'][$wildcarded]++;
                }
            }
        }

        $summary = [];
        foreach ($errorTypeMap as $errorType) {
            $summary[] = sprintf('%s - %s', $errorType['constraint'], $errorType['message']);

            foreach ($errorType['properties'] as $propertyName => $propertyAmount) {
                // if the property errored multiple times, add the amount to the error-message
                if (1 === $propertyAmount) {
                    $summary[] = sprintf('  - %s', $propertyName);
                } else {
                    $summary[] = sprintf('  - %s (%s more)', $propertyName, $propertyAmount - 1);
                }
            }
        }

        return implode(PHP_EOL, $summary);
    }
}
