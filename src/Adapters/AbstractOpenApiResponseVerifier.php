<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters;

use Composer\Autoload\ClassLoader;
use OpenApi\Builder;
use OpenApi\Generator;

trait AbstractOpenApiResponseVerifier
{
    protected object|string|null $openapiSpecification = null;

    /**
     * Find the specification: the given one, then `tests/openapi.json` or `tests/openapi.yaml`,
     * then one generated from `$srcDir`.
     */
    protected function prepareOpenApiSpecificationLoader(string $srcDir, ?string $specification = null): void
    {
        if ($specification) {
            $this->openapiSpecification = $specification;
        }

        if ($this->getOpenApiSpecificationLoader()) {
            return;
        }

        $appRoot = $this->getAppRoot();

        foreach (['openapi.json', 'openapi.yaml'] as $specfile) {
            if (file_exists($filename = $appRoot . '/tests/' . $specfile)) {
                $this->openapiSpecification = $filename;

                return;
            }
        }

        $this->openapiSpecification = $this->generateOpenApiSpecification($appRoot . '/' . $srcDir);
    }

    /**
     * Generate the specification from `$sourceDir` with swagger-php, in its default mode.
     *
     * Override to configure the generation, for example `Mode::SPEC` for a project written in
     * `OpenApi\Spec` attributes on swagger-php 6.x.
     */
    protected function generateOpenApiSpecification(string $sourceDir): object
    {
        if (class_exists(Builder::class)) {
            $json = (new Builder())->addSource($sourceDir)->build()->toJson();
        } elseif (class_exists(Generator::class)) {
            // swagger-php before 6.4
            $json = (string) (new Generator())->generate([$sourceDir])?->toJson();
        } else {
            throw new \LogicException(sprintf(
                'No specification found: none given, and neither tests/openapi.json nor tests/openapi.yaml exists. Generating one from %s requires zircote/swagger-php.',
                $sourceDir
            ));
        }

        $specification = json_decode($json);
        if (!$specification instanceof \stdClass) {
            throw new \RuntimeException(sprintf('Could not generate a specification from %s', $sourceDir));
        }

        return $specification;
    }

    /**
     * The project root: three levels above Composer's `ClassLoader`, in `vendor/composer/`.
     */
    protected function getAppRoot(): string
    {
        $classLoader = (new \ReflectionClass(ClassLoader::class))->getFileName();
        if (false === $classLoader) {
            throw new \RuntimeException("Cannot locate the project root: Composer's ClassLoader has no file");
        }

        return dirname($classLoader, 3);
    }
}
