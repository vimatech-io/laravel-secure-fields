<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use VimaTech\SecureFields\Casts\SecureField;
use VimaTech\SecureFields\Casts\SecureJson;
use VimaTech\SecureFields\Contracts\AuditLogger;
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

    public function handle(): int
    {
        /** @var string $modelClass */
        $modelClass = $this->argument('model');
        /** @var string|null $oldKey */
        $oldKey = $this->option('old-key')
            ?? $this->secret('Enter the old encryption key (base64):');
        $chunkSize = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            $this->error("Model class [{$modelClass}] does not exist.");

            return self::FAILURE;
        }

        if (! is_subclass_of($modelClass, Model::class)) {
            $this->error("Model class [{$modelClass}] is not an Eloquent model.");

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

        if ($fields === null) {
            return self::FAILURE;
        }

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
        $table = $instance->getTable();
        $primaryKey = $instance->getKeyName();
        $processed = 0;
        $failed = 0;

        $modelClass::query()
            ->select(array_merge([$primaryKey], $fields))
            ->chunkById($chunkSize, function ($records) use ($encryptor, $fields, $oldKey, $dryRun, $progressBar, $table, $primaryKey, &$processed, &$failed) {
                $chunkUpdates = [];

                foreach ($records as $record) {
                    /** @var Model $record */
                    try {
                        $updates = [];

                        foreach ($fields as $field) {
                            /** @var string|null $encryptedValue */
                            $encryptedValue = $record->getAttributes()[$field] ?? null;

                            if ($encryptedValue === null) {
                                continue;
                            }

                            $updates[$field] = $encryptor->rotate($encryptedValue, $oldKey);
                        }

                        if (! empty($updates)) {
                            /** @var int|string $key */
                            $key = $record->getKey();
                            $chunkUpdates[$key] = $updates;
                        }

                        $processed++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::error('secure-fields: rotation failed for a record', [
                            'table' => $table,
                            'error' => $e->getMessage(),
                        ]);
                        $this->newLine();
                        $this->warn('A record failed to rotate — see application logs for details.');
                    }

                    $progressBar->advance();
                }

                if (! empty($chunkUpdates) && ! $dryRun) {
                    DB::transaction(function () use ($chunkUpdates, $table, $primaryKey) {
                        foreach ($chunkUpdates as $key => $updates) {
                            DB::table($table)->where($primaryKey, $key)->update($updates);
                        }
                    });
                }
            }, $primaryKey);

        $progressBar->finish();
        $this->newLine(2);

        $this->info('Key rotation complete.');
        $this->info("Processed: {$processed}");

        if ($failed > 0) {
            $this->warn("Failed: {$failed}");
        }

        if ($dryRun) {
            $this->info('[DRY RUN] No changes were persisted.');
        } else {
            app(AuditLogger::class)->logRotation($modelClass, $processed);
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Returns the list of fields to rotate, or null if validation fails.
     *
     * @return array<string>|null
     */
    private function getFieldsToRotate(Model $instance): ?array
    {
        /** @var array<int, string> $specifiedFields */
        $specifiedFields = array_values(array_filter((array) $this->option('fields')));
        $validFields = $this->getValidSecureFields($instance);

        if (! empty($specifiedFields)) {
            $invalid = array_diff($specifiedFields, $validFields);

            if (! empty($invalid)) {
                $this->error('Fields are not SecureField or SecureJson casts: '.implode(', ', $invalid));

                return null;
            }

            return $specifiedFields;
        }

        return $validFields;
    }

    /**
     * @return array<string>
     */
    private function getValidSecureFields(Model $instance): array
    {
        return array_keys(array_filter(
            $instance->getCasts(),
            fn (mixed $cast) => is_string($cast)
                && (is_a($cast, SecureField::class, true) || is_a($cast, SecureJson::class, true))
        ));
    }
}
