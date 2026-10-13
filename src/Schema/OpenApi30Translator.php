<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Schema;

/**
 * Rewrites an OpenAPI 3.0 schema into JSON Schema 2020-12, for validators of that dialect.
 *
 * - `nullable: true` adds `null` to `type`, and to `enum` if there is one; without a `type`,
 *   the schema becomes `anyOf: [<schema>, {type: null}]`
 * - boolean `exclusiveMinimum`/`exclusiveMaximum` take the value of `minimum`/`maximum`
 *
 * Only boolean values are rewritten, so a property that happens to be named `nullable` is not.
 */
final class OpenApi30Translator
{
    /**
     * Whether schemas from a document of this OpenAPI version need translating.
     */
    public function needsTranslation(string $openApiVersion): bool
    {
        return str_starts_with($openApiVersion, '3.0');
    }

    /**
     * @return \stdClass a translated copy; the given schema is left as it is
     */
    public function translate(\stdClass $schema): \stdClass
    {
        // a deep copy, since the schema shares its components with the loaded specification
        $copy = json_decode((string) json_encode($schema));

        // translated on their own, so they stay at the root even when the schema gets wrapped
        $components = $copy->components ?? null;
        unset($copy->components);

        $translated = $this->translateNode($copy);
        if (null !== $components) {
            $translated->components = $this->translateNode($components);
        }

        return $translated;
    }

    private function translateNode(mixed $node): mixed
    {
        if (is_array($node)) {
            return array_map($this->translateNode(...), $node);
        }

        if (!is_object($node)) {
            return $node;
        }

        foreach (get_object_vars($node) as $name => $value) {
            $node->{$name} = $this->translateNode($value);
        }

        foreach (['exclusiveMinimum' => 'minimum', 'exclusiveMaximum' => 'maximum'] as $exclusive => $bound) {
            if (is_bool($node->{$exclusive} ?? null)) {
                if ($node->{$exclusive} && property_exists($node, $bound)) {
                    $node->{$exclusive} = $node->{$bound};
                    unset($node->{$bound});
                } else {
                    unset($node->{$exclusive});
                }
            }
        }

        if (is_bool($node->nullable ?? null)) {
            $nullable = $node->nullable;
            unset($node->nullable);

            if ($nullable) {
                if (property_exists($node, 'type')) {
                    $node->type = array_values(array_unique([...(array) $node->type, 'null']));
                    if (is_array($node->enum ?? null) && !in_array(null, $node->enum, true)) {
                        $node->enum[] = null;
                    }
                } else {
                    $node = (object) ['anyOf' => [$node, (object) ['type' => 'null']]];
                }
            }
        }

        return $node;
    }
}
