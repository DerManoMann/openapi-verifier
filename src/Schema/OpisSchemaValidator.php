<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Schema;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;

/**
 * Validation with `opis/json-schema`, which supports JSON Schema 2020-12, OpenAPI 3.1's dialect.
 *
 * OpenAPI 3.0 schemas are translated to 2020-12 first.
 */
final class OpisSchemaValidator implements SchemaValidator
{
    private readonly Validator $validator;

    private readonly ErrorFormatter $formatter;

    private readonly OpenApi30Translator $translator;

    public function __construct(int $maxErrors = 100)
    {
        $this->validator = (new Validator())
            ->setMaxErrors($maxErrors)
            ->setStopAtFirstError(false);
        $this->formatter = new ErrorFormatter();
        $this->translator = new OpenApi30Translator();
    }

    public function validate(mixed $data, \stdClass $schema, string $openApiVersion): array
    {
        if ($this->translator->needsTranslation($openApiVersion)) {
            $schema = $this->translator->translate($schema);
        }

        $error = $this->validator->validate($data, $schema)->error();

        return $error instanceof ValidationError ? $this->leaves($error) : [];
    }

    /**
     * Only the innermost errors say what is wrong; the ones above them say where it was nested.
     *
     * @return list<SchemaError>
     */
    private function leaves(ValidationError $error): array
    {
        if ([] === $error->subErrors()) {
            $path = $error->data()->fullPath();

            return [new SchemaError(
                [] === $path ? '' : '/' . implode('/', $path),
                $error->keyword(),
                $this->formatter->formatErrorMessage($error),
            )];
        }

        $leaves = [];
        foreach ($error->subErrors() as $subError) {
            array_push($leaves, ...$this->leaves($subError));
        }

        return $leaves;
    }
}
