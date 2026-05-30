<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Traits;

use Illuminate\Database\Eloquent\Builder;
use VimaTech\SecureFields\Contracts\HashEngine;

/**
 * Adds searchable encrypted field support to Eloquent models.
 *
 * Define searchable fields in your model:
 *
 * protected array $secureSearchable = ['email', 'phone'];
 *
 * Note: this trait accesses $secureFieldValues which must be populated before
 * save. HasSecureFields does this automatically via setAttribute(). When using
 * this trait standalone you are responsible for populating $secureFieldValues.
 */
trait HasSearchableFields
{
    /**
     * Stores plaintext values captured before the encryption cast runs.
     *
     * @var array<string, string>
     */
    protected array $secureFieldValues = [];

    public static function bootHasSearchableFields(): void
    {
        static::saving(function ($model) {
            $model->updateSearchIndexes();
        });
    }

    /**
     * Query by encrypted field using the hash index.
     */
    public function scopeSecureWhere(Builder $query, string $field, string $value): Builder
    {
        $hashEngine = app(HashEngine::class);
        $column = $this->getSearchIndexColumn($field);

        return $query->where($column, $hashEngine->hash($value));
    }

    /**
     * Update hash indexes for searchable fields before save.
     */
    protected function updateSearchIndexes(): void
    {
        $searchableFields = $this->getSecureSearchableFields();
        $hashEngine = app(HashEngine::class);

        foreach ($searchableFields as $field) {
            if ($this->isDirty($field) || ! $this->exists) {
                $plaintext = $this->getOriginalPlaintext($field);

                if ($plaintext !== null) {
                    $column = $this->getSearchIndexColumn($field);
                    $this->attributes[$column] = $hashEngine->hash($plaintext);
                }
            }
        }
    }

    /**
     * Get the original plaintext value before encryption cast.
     */
    protected function getOriginalPlaintext(string $field): ?string
    {
        $value = $this->attributes[$field] ?? null;

        if ($value === null) {
            return null;
        }

        return $this->getOriginalSecureValue($field);
    }

    /**
     * Get the value the user originally assigned before encryption.
     */
    protected function getOriginalSecureValue(string $field): ?string
    {
        return $this->secureFieldValues[$field] ?? null;
    }

    /**
     * Get the list of searchable encrypted fields.
     *
     * @return array<string>
     */
    protected function getSecureSearchableFields(): array
    {
        return $this->secureSearchable ?? [];
    }

    /**
     * Get the hash index column name for a field.
     */
    public function getSearchIndexColumn(string $field): string
    {
        return $field.'_hash';
    }
}
