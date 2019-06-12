<?php

namespace Radebatz\OpenApi\Verifier;

use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;

trait VerifiesOpenApi
{
    /*
     * @return bool `true` if the content has been validated, `false` if not.
     *
     * @throws OpenApiVerificationException
     */
    public function verifyResponse(string $method, string $path, int $statusCode, string $content): bool
    {
        if ($schemaUrl = $this->getSpecificationLoader()->getResponseSchemaUrlFor($method, $path, $statusCode)) {
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

    abstract public function getSpecificationLoader(): OpenApiSpecificationLoader;
}
