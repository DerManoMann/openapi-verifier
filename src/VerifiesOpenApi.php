<?php

namespace Radebatz\OpenApi\Verifier;

use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;

trait VerifiesOpenApi
{
    /** @var OpenApiSpecificationLoader $openapiSpecificationLoader */
    protected $openapiSpecificationLoader = null;

    /*
     * @return bool `true` if the content has been validated, `false` if not.
     *
     * @throws OpenApiVerificationException
     */
    public function verifyResponse(string $method, string $path, int $statusCode, string $content): bool
    {
        if ($schemaUrl = $this->getOpenApiSpecificationLoader()->getResponseSchemaUrlFor($method, $path, $statusCode)) {
            $retriever = new UriRetriever();
            if ($schema = $retriever->retrieve($schemaUrl)) {
                $validator = new Validator();

                $validator->check(json_decode($content), $schema);

                if (!$validator->isValid()) {
                    throw (new OpenApiVerificationException(sprintf('Schema mismatch: %s[%s]:%s', $method, $statusCode, $path)))
                        ->setErrors($validator->getErrors());
                }

                return true;
            }
        }

        return false;
    }

    public function getOpenApiSpecificationLoader(): ?OpenApiSpecificationLoader
    {
        if (!$this->openapiSpecificationLoader) {
            if (property_exists($this, 'openapiSpecification') && $this->openapiSpecification) {
                $this->openapiSpecificationLoader = new OpenApiSpecificationLoader($this->openapiSpecification);
            }
        }

        return $this->openapiSpecificationLoader;
    }
}
