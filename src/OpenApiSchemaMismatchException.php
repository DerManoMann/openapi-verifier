<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier;

class OpenApiSchemaMismatchException extends \Exception
{
    protected $errors = [];

    public function getErrors(): array
    {
        return $this->errors;
    }

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

            $errorType = $error['constraint'] . '|' . $error['message'];
            if (!array_key_exists($errorType, $errorTypeMap)) {
                $errorTypeMap[$errorType] = [
                    'properties' => [
                        $wildcarded => [$error['property']],
                    ],
                    'constraint' => $error['constraint'],
                    'message' => $error['message'],
                ];
            } else {
                if (!in_array($wildcarded, $errorTypeMap[$errorType]['properties'])) {
                    $errorTypeMap[$errorType]['properties'][$wildcarded] = [$error['property']];
                } else {
                    $errorTypeMap[$errorType]['properties'][] = $wildcarded;
                }
            }
        }

        $summary = [];
        foreach ($errorTypeMap as $es) {
            $summary[] = sprintf('%s - %s', $es['constraint'], $es['message']);
            foreach ($es['properties'] as $ps => $pl) {
                if (1 == count($pl)) {
                    $summary[] = sprintf('  - %s', $ps);
                } else {
                    $summary[] = sprintf('  - %s (%s more)', count($pl) - 1);
                }
            }
        }

        return implode(PHP_EOL, $summary);
    }
}
