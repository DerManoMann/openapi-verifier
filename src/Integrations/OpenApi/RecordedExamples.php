<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Integrations\OpenApi;

use OpenApi\Augmenter\Group;
use OpenApi\Spec as OA;
use OpenApi\Specification;
use OpenApi\Undefined;
use OpenApi\Utils\PipeInterface;
use Radebatz\OpenApi\Verifier\ExampleRecorder;

/**
 * Sets the example of each JSON response the verifier recorded, where the attributes give none.
 *
 * The example is read from the file `ExampleRecorder` writes for the operation's method, path and
 * the response's status code. A response keyed by a range (`2XX`) or `default` takes no example,
 * since no one status code is its own. A media type with an `example` or `examples` of its own,
 * or whose schema has an `example`, keeps it.
 *
 * @implements PipeInterface<Specification>
 */
final class RecordedExamples implements PipeInterface
{
    public function __construct(private readonly string $directory)
    {
    }

    public function __invoke(mixed $payload): Specification
    {
        foreach ($payload->operations as $operation) {
            if (null === $operation->path || null === $operation->method) {
                continue;
            }

            foreach ($operation->responses ?? [] as $response) {
                if (!is_int($response->response) && !ctype_digit((string) $response->response)) {
                    continue;
                }

                $filename = ExampleRecorder::filename($this->directory, $operation->method, $operation->path, (int) $response->response);
                if (!is_file($filename) || false === ($json = file_get_contents($filename))) {
                    continue;
                }

                foreach ($response->content ?? [] as $mediaType) {
                    if ($this->isJson($mediaType) && !$this->hasExample($mediaType)) {
                        $mediaType->example = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
                    }
                }
            }
        }

        return $payload;
    }

    public function group(): \BackedEnum
    {
        return Group::Augment;
    }

    private function isJson(OA\MediaType $mediaType): bool
    {
        $type = strtolower((string) $mediaType->mediaType);

        return 'application/json' === $type || str_ends_with($type, '+json');
    }

    private function hasExample(OA\MediaType $mediaType): bool
    {
        return Undefined::UNDEFINED !== $mediaType->example
            || [] !== ($mediaType->examples ?? [])
            || ($mediaType->schema instanceof OA\Schema && Undefined::UNDEFINED !== $mediaType->schema->example);
    }
}
