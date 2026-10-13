<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters;

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

        $openApi = (new Generator())->generate([$appRoot . '/' . $srcDir]);
        $this->openapiSpecification = json_decode($openApi->toJson());
    }

    /**
     * The project root: three levels above Composer's `ClassLoader`, in `vendor/composer/`.
     */
    protected function getAppRoot(): string
    {
        $classLoader = (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName();
        if (false === $classLoader) {
            throw new \RuntimeException('Cannot locate the project root: Composer\'s ClassLoader has no file');
        }

        return dirname($classLoader, 3);
    }
}
