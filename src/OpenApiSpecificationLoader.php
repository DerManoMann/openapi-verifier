<?php

namespace Radebatz\OpenApi\Verifier;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class OpenApiSpecificationLoader
{
    protected $filename = null;
    protected $specification = null;

    /**
     * @param $specification object|string The specification object or filename
     */
    public function __construct($specification)
    {
        $this->specification = $this->resolveSpecification($specification);
    }

    protected function resolveSpecification($specification)
    {
        $resolved = null;
        if (is_string($specification)) {
            if (false !== strpos($specification, '.yaml') || false !== strpos($specification, '.yml')) {
                try {
                    $resolved = (object) Yaml::parseFile($specification, Yaml::PARSE_OBJECT_FOR_MAP);
                } catch (ParseException $parseException) {
                    throw new \InvalidArgumentException(
                        sprintf('Could not load specification: %s', $specification),
                        0,
                        $parseException
                    );
                }
            } else {
                $resolved = @json_decode(@file_get_contents($specification));
            }

            if (!$resolved) {
                throw new \InvalidArgumentException(sprintf('Could not load specification: %s', $specification));
            }
        } elseif (is_object($specification)) {
            $resolved = $specification;
        } else {
            throw new \InvalidArgumentException('Invalid specification');
        }

        return $this->fixNullable($resolved);
    }

    /**
     * Get a schema url for the response matching the given parameter.
     */
    public function getResponseSchemaUrlFor(string $method, string $path, int $statusCode): ?string
    {
        $method = strtolower($method);

        $schema = $this->findPath(null, $path, $method, 'responses', $statusCode, 'content', 'application/json', 'schema');
        if (!$schema && '/' != $path[0]) {
            // try absolue
            $schema = $this->findPath(null, '/' . $path, $method, 'responses', $statusCode, 'content', 'application/json', 'schema');
        }

        if ($schema) {
            $schema->components = $this->specification->components;

            return 'data://application/json;base64,' . base64_encode(json_encode($schema));
        }

        return null;
    }

    protected function findPath($node, ...$path)
    {
        $node = $node ?: (object) $this->specification->paths;
        $next = array_shift($path);

        if (null !== $next && property_exists($node, $next)) {
            if ($path) {
                return $this->findPath((object) $node->{$next}, ...$path);
            }

            return (object) $node->{$next};
        }

        return null;
    }

    // https://github.com/justinrainbow/json-schema/issues/551
    protected function fixNullable(&$node)
    {
        if (is_object($node) && property_exists($node, 'nullable') && property_exists($node, 'type')) {
            $anyOf = [
                ['type' => $node->type, 'format' => property_exists($node, 'format') ? $node->format : ''],
                ['type' => null],
            ];
            unset($node->type);
            $node->anyOf = $anyOf;
        }

        if (is_iterable($node) || is_object($node)) {
            foreach ($node as $key => &$value) {
                $this->fixNullable($value);
            }
        }

        return $node;
    }
}
