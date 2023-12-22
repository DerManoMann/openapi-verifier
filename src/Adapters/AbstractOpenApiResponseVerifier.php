<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Adapters;

use OpenApi\Generator;

trait AbstractOpenApiResponseVerifier
{
    protected string $openapiSpecification;

    protected function prepareOpenApiSpecificationLoader(string $srcDir, ?string $specification = null)
    {
        $appRoot = null;

        if ($specification) {
            $this->openapiSpecification = $specification;
        }

        // try loader
        $specificationLoader = $this->getOpenApiSpecificationLoader();

        if (!$specificationLoader) {
            $appRoot = $appRoot ?: $this->getAppRoot();

            // try some default filenames
            foreach (['openapi.json', 'openapi.yaml'] as $specfile) {
                if (file_exists($specification = $appRoot . '/tests/' . $specfile)) {
                    $this->openapiSpecification = $specification;
                    break;
                }
            }

            // try loader again
            $specificationLoader = $this->getOpenApiSpecificationLoader();
        }

        if (!$specificationLoader) {
            $appRoot = $appRoot ?: $this->getAppRoot();

            $openApi = Generator::scan($appRoot . '/' . $srcDir);
            $this->openapiSpecification = json_decode($openApi->toJson());
        }
    }

    protected function getAppRoot()
    {
        $rc = new \ReflectionClass('\Composer\Autoload\ClassLoader');

        return dirname(dirname(dirname($rc->getFileName())));
    }
}
