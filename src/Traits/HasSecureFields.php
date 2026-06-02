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
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasSecureFields
{
    use HasSearchableFields;

    /**
     * Per-class cache of secure field names, computed once from $casts.
     *
     * @var array<string, array<string>>
     */
    private static array $secureFieldsCache = [];

    public function initializeHasSecureFields(): void
    {
        $this->hidden = array_merge($this->hidden, $this->getSecureFields());

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

        if ($value === null || ! is_string($value)) {
            return null;
        }

        $length = mb_strlen($value);

        if ($visibleEnd === 0 || $length <= $visibleEnd) {
            return str_repeat($maskChar, $length);
        }

        return str_repeat($maskChar, $length - $visibleEnd).mb_substr($value, -$visibleEnd);
    }

    /**
     * Get array of field names that are encrypted.
     * Result is cached per class — casts are defined statically and never change at runtime.
     *
     * @return array<string>
     */
    protected function getSecureFields(): array
    {
        $class = static::class;

        if (! array_key_exists($class, self::$secureFieldsCache)) {
            self::$secureFieldsCache[$class] = array_keys(array_filter(
                $this->getCasts(),
                fn (string $cast) => is_a($cast, SecureField::class, true)
                    || is_a($cast, SecureJson::class, true)
            ));
        }

        return self::$secureFieldsCache[$class];
    }

    /**
     * Get decrypted value without triggering audit log.
     * Internal use only — do NOT expose this via public API or use in data exports.
     */
    protected function getSecureRawValue(string $field): ?string
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
        return (clone $this)->makeHidden($this->getSecureFields())->toArray();
    }

    /**
     * Convert the model to array with masked secure fields.
     *
     * @return array<string, mixed>
     */
    public function toMaskedArray(): array
    {
        $secureFields = $this->getSecureFields();
        $array = (clone $this)->makeVisible($secureFields)->toArray();

        $visibleEnd = (int) config('secure-fields.masking.visible_end', 4);
        $maskChar = (string) config('secure-fields.masking.character', '*');

        foreach ($secureFields as $field) {
            if (! isset($array[$field]) || ! is_string($array[$field])) {
                continue;
            }

            $value = $array[$field];
            $length = mb_strlen($value);

            if ($visibleEnd === 0 || $length <= $visibleEnd) {
                $array[$field] = str_repeat($maskChar, $length);
            } else {
                $array[$field] = str_repeat($maskChar, $length - $visibleEnd).mb_substr($value, -$visibleEnd);
            }
        }

        return $array;
    }
}
