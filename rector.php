<?php

use Rector\CodeQuality\Rector\Attribute\SortAttributeNamedArgsRector;
use Rector\CodeQuality\Rector\FuncCall\SortCallLikeNamedArgsRector;
use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withRules([
        TypedPropertyFromStrictConstructorRector::class
    ])
    ->withPreparedSets(true, true)
    ->withSkip([
        // reorders the named arguments of swagger-php attributes, which decides the order
        // of the generated specifications
        SortAttributeNamedArgsRector::class,
        SortCallLikeNamedArgsRector::class,
    ])
    ->withPhpVersion(PhpVersion::PHP_81);
