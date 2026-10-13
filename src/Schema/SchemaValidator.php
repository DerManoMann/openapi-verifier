<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Schema;

/**
 * Validates data against a schema from an OpenAPI document.
 *
 * The schema is as the document writes it, so its dialect depends on the OpenAPI version: 3.1 and
 * later use JSON Schema 2020-12, while 3.0 has keywords of its own, such as `nullable`. A validator
 * handles the versions it supports; `OpenApi30Translator` turns 3.0 into 2020-12 for engines that
 * only know JSON Schema.
 *
 * The schema carries the specification's `components`, so `#/components/...` references resolve
 * within it.
 */
interface SchemaValidator
{
    /**
     * @param  string            $openApiVersion the `openapi` version of the document the schema is from
     * @return list<SchemaError> empty if the data is valid
     */
    public function validate(mixed $data, \stdClass $schema, string $openApiVersion): array;
}
