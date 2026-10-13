<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Schema;

/**
 * One way the data failed its schema.
 */
final class SchemaError
{
    /**
     * @param string $pointer JSON pointer to the failing part of the data, `''` for the whole of it
     * @param string $keyword the schema keyword that failed, for example `required` or `type`
     */
    public function __construct(
        public readonly string $pointer,
        public readonly string $keyword,
        public readonly string $message,
    ) {
    }
}
