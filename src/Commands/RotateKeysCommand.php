<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use VimaTech\SecureFields\Casts\SecureField;
use VimaTech\SecureFields\Casts\SecureJson;
use VimaTech\SecureFields\Contracts\Encryptor;
use VimaTech\SecureFields\Traits\HasSecureFields;

class RotateKeysCommand extends Command
{
    protected $signature = 'secure-fields:rotate
        {model : The fully qualified model class}
        {--old-key= : The previous encryption key (base64 encoded)}
        {--fields=* : Specific fields to rotate (defaults to all secure fields)}
        {--chunk=500 : Number of records to process per chunk}
        {--dry-run : Preview changes without persisting}
        {--force : Run without confirmation}';

    protected $description = 'Rotate encryption keys for secure model fields';

    private int $processed = 0;

    private int $failed = 0;

    public function handle(): int
    {
        /** @var string $modelClass */
        $modelClass = $this->argument('model');
        /** @var string|null $oldKey */
        $oldKey = $this->option('old-key');
        $chunkSize = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            $this->error("Model class [{$modelClass}] does not exist.");

            return self::FAILURE;
        }

        if (! in_array(HasSecureFields::class, class_uses_recursive($modelClass))) {
            $this->error("Model [{$modelClass}] does not use the HasSecureFields trait.");

            return self::FAILURE;
        }

        if (! is_string($oldKey) || $oldKey === '') {
            $this->error('You must provide the old encryption key using --old-key.');

            return self::FAILURE;
        }

        /** @var Model $instance */
        $instance = new $modelClass;
        $fields = $this->getFieldsToRotate($instance);

        if (empty($fields)) {
            $this->warn('No secure fields found to rotate.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('[DRY RUN] No changes will be persisted.');
        }

        $totalRecords = (int) $modelClass::count();
        $this->info("Rotating keys for {$totalRecords} records in [{$modelClass}]");
        $this->info('Fields: '.implode(', ', $fields));
        $this->newLine();

        if (! $dryRun && ! $this->option('force') && ! $this->confirm('Continue with key rotation?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($totalRecords);
        $progressBar->start();

        $encryptor = app(Encryptor::class);

        $modelClass::query()
            ->select(array_merge(['id'], $fields, $this->getHashColumns($instance, $fields)))
            ->chunkById($chunkSize, function ($records) use ($encryptor, $fields, $oldKey, $dryRun, $progressBar) {
                foreach ($records as $record) {
                    /** @var Model $record */
                    try {
                        $updates = [];

                        foreach ($fields as $field) {
                            /** @var string|null $encryptedValue */
                            $encryptedValue = $record->getAttributeFromArray($field);

                            if ($encryptedValue === null) {
                                continue;
                            }

                            $newEncrypted = $encryptor->rotate($encryptedValue, $oldKey);
                            $updates[$field] = $newEncrypted;
                        }

                        if (! empty($updates) && ! $dryRun) {
                            DB::table($record->getTable())
                                ->where($record->getKeyName(), $record->getKey())
                                ->update($updates);
                        }

                        $this->processed++;
                    } catch (\Throwable $e) {
                        $this->failed++;
                        $this->newLine();
                        $this->error("Failed to rotate record ID {$record->getKey()}: {$e->getMessage()}");
                    }

                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->newLine(2);

        $this->info('Key rotation complete.');
        $this->info("Processed: {$this->processed}");

        if ($this->failed > 0) {
            $this->warn("Failed: {$this->failed}");
        }

        if ($dryRun) {
            $this->info('[DRY RUN] No changes were persisted.');
        }

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string>
     */
    private function getFieldsToRotate(Model $instance): array
    {
        /** @var array<int, string> $specifiedFields */
        $specifiedFields = array_filter((array) $this->option('fields'));

        if (! empty($specifiedFields)) {
            return $specifiedFields;
        }

        $fields = [];
        foreach ($instance->getCasts() as $field => $cast) {
            if (is_string($cast)
                && (is_a($cast, SecureField::class, true)
                || is_a($cast, SecureJson::class, true))) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param  array<string>  $fields
     * @return array<string>
     */
    private function getHashColumns(Model $instance, array $fields): array
    {
        /** @var array<string> $searchable */
        $searchable = $instance->secureSearchable ?? [];
        $columns = [];

        foreach ($fields as $field) {
            if (in_array($field, $searchable)) {
                $columns[] = $field.'_hash';
            }
        }

        return $columns;
    }
}
