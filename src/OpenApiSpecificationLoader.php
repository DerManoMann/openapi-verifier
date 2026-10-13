<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class OpenApiSpecificationLoader
{
    protected $filename;
    protected $specification;

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
            } elseif (file_exists($specification)) {
                $resolved = json_decode(file_get_contents($specification));
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
     * Get a schema url for the response matching the given parameters.
     *
     * `$path` may be a concrete path (`/user/1`) or a template from the specification (`/user/{id}`).
     */
    public function getResponseSchemaUrlFor(string $method, string $path, int $statusCode, ?string $contentType = null): ?string
    {
        if (!($schema = $this->getResponseSchemaFor($method, $path, $statusCode, $contentType)) instanceof \stdClass) {
            return null;
        }

        // copy, so the components do not end up inside the specification itself
        $schema = clone $schema;
        $schema->components = $this->property($this->specification, 'components') ?? new \stdClass();

        return 'data://application/json;base64,' . base64_encode((string) json_encode($schema));
    }

    /**
     * Get the schema of the response matching the given parameters, if there is one.
     */
    public function getResponseSchemaFor(string $method, string $path, int $statusCode, ?string $contentType = null): ?\stdClass
    {
        if (null === ($template = $this->resolvePath($path))) {
            return null;
        }

        $pathItem = $this->property($this->property($this->specification, 'paths'), $template);
        $operation = $this->property($pathItem, strtolower($method));
        $response = $this->resolveResponse($this->property($operation, 'responses'), $statusCode);
        $mediaType = $this->resolveMediaType($this->property($response, 'content'), $contentType);
        $schema = $this->property($mediaType, 'schema');

        return null !== $schema ? (object) $schema : null;
    }

    /**
     * Find the specification path matching the given path.
     *
     * A literal match wins; otherwise the template with the most literal characters does, so
     * `/users/me` is preferred over `/users/{id}`. Base paths of the `servers` are tried as well.
     */
    public function resolvePath(string $path): ?string
    {
        $paths = $this->property($this->specification, 'paths');
        if (null === $paths) {
            return null;
        }

        $path = '/' . ltrim((string) strtok($path, '?#'), '/');
        $templates = array_map(strval(...), array_keys((array) $paths));

        $candidates = [$path];
        foreach ($this->serverBasePaths() as $basePath) {
            if ($path === $basePath || str_starts_with($path, $basePath . '/')) {
                $candidates[] = substr($path, strlen($basePath)) ?: '/';
            }
        }

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $templates, true)) {
                return $candidate;
            }
        }

        $best = null;
        $bestLiteral = -1;
        foreach ($candidates as $candidate) {
            foreach ($templates as $template) {
                if (!str_contains($template, '{')) {
                    continue;
                }

                $literal = strlen((string) preg_replace('/\{[^}]*\}/', '', $template));
                if ($literal > $bestLiteral && preg_match($this->templateToRegex($template), $candidate)) {
                    $best = $template;
                    $bestLiteral = $literal;
                }
            }
        }

        return $best;
    }

    protected function templateToRegex(string $template): string
    {
        $parts = preg_split('/(\{[^}]*\})/', $template, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $regex = '';
        foreach ($parts as $part) {
            $regex .= str_starts_with($part, '{') ? '[^/]+' : preg_quote($part, '#');
        }

        return '#^' . $regex . '$#';
    }

    /**
     * @return list<string> the path part of each server url, variables replaced by their defaults
     */
    protected function serverBasePaths(): array
    {
        $basePaths = [];
        foreach ((array) $this->property($this->specification, 'servers') as $server) {
            $url = (string) $this->property($server, 'url');
            foreach ((array) $this->property($server, 'variables') as $name => $variable) {
                $url = str_replace('{' . $name . '}', (string) $this->property($variable, 'default'), $url);
            }

            $basePath = rtrim((string) (str_contains($url, '://') ? parse_url($url, PHP_URL_PATH) : $url), '/');
            if ('' !== $basePath) {
                $basePaths[] = '/' . ltrim($basePath, '/');
            }
        }

        return $basePaths;
    }

    /**
     * The exact status code wins, then its range (`2XX`), then `default`.
     */
    protected function resolveResponse(mixed $responses, int $statusCode): mixed
    {
        foreach ([(string) $statusCode, intdiv($statusCode, 100) . 'XX', 'default'] as $key) {
            if (null !== ($response = $this->property($responses, $key))) {
                return $response;
            }
        }

        return null;
    }

    /**
     * Pick the media type for the response's content type, falling back to `application/json`.
     *
     * A response whose content type matches a non-JSON media type of the specification has
     * nothing to verify, since only JSON bodies are validated.
     */
    protected function resolveMediaType(mixed $content, ?string $contentType): mixed
    {
        if (null === $content) {
            return null;
        }

        if (null !== $contentType && '' !== ($type = strtolower(trim(explode(';', $contentType)[0])))) {
            foreach ([$type, strtok($type, '/') . '/*', '*/*'] as $key) {
                if (null !== ($mediaType = $this->property($content, $key))) {
                    return $this->isJson($type) ? $mediaType : null;
                }
            }
        }

        return $this->property($content, 'application/json');
    }

    protected function isJson(string $type): bool
    {
        return 'application/json' === $type || str_ends_with($type, '+json');
    }

    protected function property(mixed $node, string $name): mixed
    {
        if (is_array($node)) {
            return $node[$name] ?? null;
        }

        if (is_object($node) && property_exists($node, $name)) {
            return $node->{$name};
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
            foreach ($node as &$value) {
                $this->fixNullable($value);
            }
        }

        return $node;
    }
}
