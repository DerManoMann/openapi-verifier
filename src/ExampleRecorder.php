<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier;

/**
 * Writes each verified response body to a directory, as an example for the document.
 *
 * A body is written to `<directory>/<method>/<path template>/<status>.json`, so
 * `GET /users/{id}` answered with 200 is `get/users/{id}/200.json`. One example per operation and
 * status: the last one recorded wins, so the files stay stable from one run to the next.
 *
 * Before writing, the value at each JSON pointer in `$scrub` is replaced with `$placeholder`. A
 * `*` segment matches every key or index, so `/items/*\/email` covers each item of a list.
 */
final class ExampleRecorder
{
    /** The directory to record into; recording is off when it is not set. */
    public const ENV_EXAMPLES = 'OPENAPI_VERIFIER_EXAMPLES';

    /** JSON pointers to scrub, separated by commas. */
    public const ENV_EXAMPLES_SCRUB = 'OPENAPI_VERIFIER_EXAMPLES_SCRUB';

    /**
     * @param list<string> $scrub JSON pointers whose values are replaced before writing
     */
    public function __construct(
        private readonly string $directory,
        private readonly array $scrub = [],
        private readonly mixed $placeholder = '***',
    ) {
    }

    /**
     * A recorder for the directory in `OPENAPI_VERIFIER_EXAMPLES`, or `null` when it is not set.
     */
    public static function fromEnvironment(): ?self
    {
        $directory = getenv(self::ENV_EXAMPLES);
        if (false === $directory || '' === $directory) {
            return null;
        }

        $scrub = array_values(array_filter(array_map(trim(...), explode(',', (string) getenv(self::ENV_EXAMPLES_SCRUB)))));

        return new self($directory, $scrub);
    }

    /**
     * The file holding the example for the given operation and status code.
     */
    public static function filename(string $directory, string $method, string $pathTemplate, int $statusCode): string
    {
        $path = trim($pathTemplate, '/');

        return rtrim($directory, '/') . '/' . strtolower($method) . ('' === $path ? '' : '/' . $path) . '/' . $statusCode . '.json';
    }

    public function record(string $method, string $pathTemplate, int $statusCode, mixed $data): void
    {
        foreach ($this->scrub as $pointer) {
            $data = $this->scrubbed($data, array_slice(explode('/', $pointer), 1));
        }

        $filename = self::filename($this->directory, $method, $pathTemplate, $statusCode);
        if (!is_dir($dir = dirname($filename)) && !mkdir($dir, 0o777, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Cannot create the examples directory "%s"', $dir));
        }

        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
    }

    /**
     * @param list<string> $segments the pointer's segments, still escaped
     */
    private function scrubbed(mixed $data, array $segments): mixed
    {
        if ([] === $segments) {
            return $this->placeholder;
        }

        $segment = str_replace(['~1', '~0'], ['/', '~'], array_shift($segments));

        if ($data instanceof \stdClass) {
            foreach (get_object_vars($data) as $key => $value) {
                if ('*' === $segment || (string) $key === $segment) {
                    $data->{$key} = $this->scrubbed($value, $segments);
                }
            }
        } elseif (is_array($data)) {
            foreach ($data as $key => $value) {
                if ('*' === $segment || (string) $key === $segment) {
                    $data[$key] = $this->scrubbed($value, $segments);
                }
            }
        }

        return $data;
    }
}
