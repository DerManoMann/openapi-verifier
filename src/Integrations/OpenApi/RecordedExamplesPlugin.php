<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Integrations\OpenApi;

use OpenApi\Builder\Hooks;
use OpenApi\Contracts\PluginInterface;
use OpenApi\Utils\Pipeline;

/**
 * Adds the responses the verifier recorded to a swagger-php build, as examples.
 *
 * Needs swagger-php 7.3 or later, in `hybrid` or `spec` mode. The one setting is the directory the
 * recordings are in:
 *
 * ```php
 * $builder->withPlugins([RecordedExamplesPlugin::class => ['directory' => 'tests/examples']]);
 * ```
 */
final class RecordedExamplesPlugin implements PluginInterface
{
    public function configure(Hooks $hooks, array $config): void
    {
        $directory = $config['directory'] ?? null;
        if (!is_string($directory) || '' === $directory) {
            throw new \InvalidArgumentException(sprintf('%s needs a "directory" setting, the directory the examples were recorded into', self::class));
        }

        $hooks->withAugmenters(fn (Pipeline $augmenters): Pipeline => $augmenters->add(new RecordedExamples($directory)));
    }
}
