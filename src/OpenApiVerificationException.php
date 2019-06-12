<?php

namespace Radebatz\OpenApi\Verifier;

class OpenApiVerificationException extends \Exception
{
    protected $errors = [];

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setErrors(array $errors): OpenApiVerificationException
    {
        $this->errors = $errors;

        return $this;
    }
}
