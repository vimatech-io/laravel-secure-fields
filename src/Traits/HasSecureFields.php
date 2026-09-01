<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Traits;

use Illuminate\Database\Eloquent\Model;
use VimaTech\SecureFields\Contracts\Encryptor;
use VimaTech\SecureFields\Exceptions\SecureFieldsException;
use VimaTech\SecureFields\Support\SecureFieldResolver;

/**
 * @mixin Model
 */
trait HasSecureFields
{
    use HasSearchableFields;

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

    public function masked(string $field, int $visibleEnd = 4, string $maskChar = '*'): ?string
    {
        $value = $this->getAttribute($field);

        if ($value === null || ! is_string($value)) {
            return null;
        }

        return $this->maskSecureValue($value, $visibleEnd, $maskChar);
    }

    private function maskSecureValue(string $value, int $visibleEnd, string $maskChar): string
    {
        if ($visibleEnd < 0) {
            throw new SecureFieldsException("Masking visible_end must be zero or greater, [{$visibleEnd}] given.");
        }

        $length = mb_strlen($value);

        if ($visibleEnd === 0 || $length <= $visibleEnd) {
            return str_repeat($maskChar, $length);
        }

        return str_repeat($maskChar, $length - $visibleEnd).mb_substr($value, -$visibleEnd);
    }

    /**
     * @return array<string>
     */
    protected function getSecureFields(): array
    {
        return SecureFieldResolver::resolve($this);
    }

    /**
     * Bypasses the audit log. Never expose through a public API or a data export.
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
     * @return array<string, mixed>
     */
    public function toSecureArray(): array
    {
        return (clone $this)->makeHidden($this->getSecureFields())->toArray();
    }

    /**
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

            $array[$field] = $this->maskSecureValue($array[$field], $visibleEnd, $maskChar);
        }

        return $array;
    }
}
