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
        return $this->messages('infoMessages', null);
    }

    /**
     * Retrieves warning messages from the validation results.
     *
     * @return array An array of warning messages.
     */
    public function warnings(): array
    {
        return $this->messages('warningMessages', 'warnings');
    }

    /**
     * Retrieves error messages from the validation results.
     *
     * @return array An array of error messages.
     */
    public function errors(): array
    {
        return $this->messages('errorMessages', 'errors');
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

    /**
     * Normalizes a message list from validationResults; ZATCA may omit the key,
     * return null, or return a single message object instead of a list.
     * Falls back to a top-level key used by the legacy response format.
     */
    protected function messages(string $key, ?string $legacyKey = null): array
    {
        $validation = $this->validation();

        if (array_key_exists($key, $validation)) {
            $messages = $validation[$key];
        } elseif ($legacyKey !== null) {
            $messages = $this->getOptionalAttribute($legacyKey);
        } else {
            $messages = null;
        }

        if (! is_array($messages)) {
            return [];
        }

        // a single message object (associative array) instead of a list
        if ($messages !== [] && array_keys($messages) !== range(0, count($messages) - 1)) {
            return [$messages];
        }

        return $messages;
    }
}
