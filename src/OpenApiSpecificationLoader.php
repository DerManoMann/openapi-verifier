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
        if (is_object($specification)) {
            $this->specification = $specification;
        } elseif (is_string($specification)) {
            if (false !== strpos($specification, '.yaml') || false !== strpos($specification, '.yml')) {
                try {
                    $this->specification = (object) Yaml::parseFile($specification);
                } catch (ParseException $parseException) {
                    throw new \InvalidArgumentException(
                        sprintf('Could not load specification: %s', $specification),
                        0,
                        $parseException
                    );
                }
            } else {
                $this->specification = @json_decode(@file_get_contents($specification));
            }

            if (!$this->specification) {
                throw new \InvalidArgumentException(sprintf('Could not load specification: %s', $specification));
            }
        } else {
            throw new \InvalidArgumentException('Invalid specification');
        }
    }

    /**
     * Get a schema url for the request/response matching the given parameter.
     */
    public function getSchemaUrlFor(string $method, string $path, int $statusCode = null): ?string
    {
        $method = strtolower($method);

        if ($statusCode) {
            if ($schema = $this->findPath(null, $path, $method, 'responses', $statusCode, 'content', 'application/json', 'schema')) {
                $schema->components = $this->specification->components;

                return 'data://application/json;base64,' . base64_encode(json_encode($schema));
            }
        } else {
            // TODO
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
}
