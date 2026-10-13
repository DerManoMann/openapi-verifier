<?php

use Rector\CodeQuality\Rector\Attribute\SortAttributeNamedArgsRector;
use Rector\CodeQuality\Rector\FuncCall\SortCallLikeNamedArgsRector;
use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withSkipPath(__DIR__ . '/tests/Fixtures')
    ->withSkip([
        // reorders the named arguments of swagger-php attributes, which decides the order
        // of the generated specifications
        SortAttributeNamedArgsRector::class,
        SortCallLikeNamedArgsRector::class,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        phpunitCodeQuality: true,
    )
    ->withAttributesSets(phpunit: true)
    ->withPhpVersion(PhpVersion::PHP_82);
