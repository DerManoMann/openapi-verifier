<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier;

use Radebatz\OpenApi\Verifier\Schema\SchemaError;

class OpenApiSchemaMismatchException extends \Exception
{
    /** @var list<SchemaError> */
    protected array $errors = [];

    /**
     * @return list<SchemaError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param list<SchemaError> $errors
     */
    public function setErrors(array $errors): OpenApiSchemaMismatchException
    {
        $this->errors = $errors;

        return $this;
    }

    /**
     * The errors grouped by keyword and message, one line per failing pointer.
     *
     * Array indices in a pointer are replaced by `*`, so the same error on every item of a list
     * is one line with a count.
     */
    public function getErrorSummary(): ?string
    {
        if (!$this->errors) {
            return null;
        }

        $groups = [];
        foreach ($this->errors as $error) {
            $pointer = (string) preg_replace('#/\d+(?=/|$)#', '/*', $error->pointer) ?: '/';
            $key = $error->keyword . '|' . $error->message;
            $groups[$key]['keyword'] = $error->keyword;
            $groups[$key]['message'] = $error->message;
            $groups[$key]['pointers'][$pointer] = ($groups[$key]['pointers'][$pointer] ?? 0) + 1;
        }

        $summary = [];
        foreach ($groups as $group) {
            $summary[] = sprintf('%s - %s', $group['keyword'], $group['message']);
            foreach ($group['pointers'] as $pointer => $count) {
                $summary[] = 1 === $count ? sprintf('  - %s', $pointer) : sprintf('  - %s (%s more)', $pointer, $count - 1);
            }
        }

        return implode(PHP_EOL, $summary);
    }
}
