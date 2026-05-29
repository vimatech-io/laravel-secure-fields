<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Traits;

use VimaTech\SecureFields\Casts\SecureField;
use VimaTech\SecureFields\Casts\SecureJson;
use VimaTech\SecureFields\Contracts\Encryptor;

/**
 * Provides secure field management for Eloquent models.
 *
 * Features:
 * - Automatic hidden serialization of encrypted fields
 * - Masked output support
 * - Secure array/JSON export
 */
trait HasSecureFields
{
    use HasSearchableFields;

    /**
     * Stores original plaintext values before encryption.
     *
     * @var array<string, string>
     */
    protected array $secureFieldValues = [];

    public static function bootHasSecureFields(): void
    {
        // Prevent double boot of HasSearchableFields
    }

    public function initializeHasSecureFields(): void
    {
        // Auto-hide encrypted fields from serialization
        $this->hidden = array_merge($this->hidden, $this->getSecureFields());

        // Hide hash columns too
        foreach ($this->getSecureSearchableFields() as $field) {
            $this->hidden[] = $this->getSearchIndexColumn($field);
        }
    }

    /**
     * Set attribute override to capture plaintext before cast encryption.
     */
    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->getSecureFields()) && $value !== null) {
            if (is_array($value)) {
                $this->secureFieldValues[$key] = json_encode($value);
            } else {
                $this->secureFieldValues[$key] = (string) $value;
            }
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Get a masked version of a secure field.
     */
    public function masked(string $field, int $visibleEnd = 4, string $maskChar = '*'): ?string
    {
        $value = $this->getAttribute($field);

        if ($value === null) {
            return null;
        }

        $length = mb_strlen($value);

        if ($length <= $visibleEnd) {
            return str_repeat($maskChar, $length);
        }

        $maskedLength = $length - $visibleEnd;
        $visible = mb_substr($value, -$visibleEnd);

        return str_repeat($maskChar, $maskedLength).$visible;
    }

    /**
     * Get array of field names that are encrypted.
     *
     * @return array<string>
     */
    protected function getSecureFields(): array
    {
        $secureFields = [];

        foreach ($this->getCasts() as $field => $cast) {
            if (is_a($cast, SecureField::class, true)
                || is_a($cast, SecureJson::class, true)) {
                $secureFields[] = $field;
            }
        }

        return $secureFields;
    }

    /**
     * Get decrypted value without triggering audit log (internal use).
     */
    public function getSecureRawValue(string $field): ?string
    {
        $raw = $this->getAttributeFromArray($field);

        if ($raw === null) {
            return null;
        }

        return app(Encryptor::class)->decrypt($raw);
    }

    /**
     * Convert the model to array with secure fields excluded.
     *
     * @return array<string, mixed>
     */
    public function toSecureArray(): array
    {
        $array = $this->toArray();

        foreach ($this->getSecureFields() as $field) {
            unset($array[$field]);
        }

        return $array;
    }

    /**
     * Convert the model to array with masked secure fields.
     *
     * @return array<string, mixed>
     */
    public function toMaskedArray(): array
    {
        $array = $this->toArray();

        foreach ($this->getSecureFields() as $field) {
            if (isset($array[$field])) {
                $array[$field] = $this->masked($field);
            }
        }

        return $array;
    }
}
