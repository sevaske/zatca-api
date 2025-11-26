<?php

declare(strict_types=1);

namespace Sevaske\ZatcaApi\Responses;

class ValidationResponse extends ZatcaResponse
{
    /**
     * Retrieves the full validation results from the response.
     *
     * @return array The array of validation results.
     */
    public function validation(): array
    {
        return (array) $this->getOptionalAttribute('validationResults');
    }

    /**
     * Gets the overall validation status from the response.
     *
     * @return string|null The validation status, or null if not available.
     */
    public function validationStatus(): ?string
    {
        return $this->validation()['status'] ?? null;
    }

    /**
     * Retrieves informational messages from the validation results.
     *
     * @return array An array of info messages.
     */
    public function info(): array
    {
        return (array) $this->validation()['infoMessages'];
    }

    /**
     * Retrieves warning messages from the validation results.
     *
     * @return array An array of warning messages.
     */
    public function warnings(): array
    {
        return (array) $this->validation()['warningMessages'];
    }

    /**
     * Retrieves error messages from the validation results.
     *
     * @return array An array of error messages.
     */
    public function errors(): array
    {
        return (array) $this->validation()['errorMessages'];
    }

    /**
     * Checks if the response contains any error messages.
     *
     * @return bool True if there are errors, false otherwise.
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors());
    }

    /**
     * Checks if the response contains any warning messages.
     *
     * @return bool True if there are warnings, false otherwise.
     */
    public function hasWarnings(): bool
    {
        return ! empty($this->warnings());
    }
}
