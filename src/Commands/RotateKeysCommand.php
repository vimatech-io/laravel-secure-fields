<?php

declare(strict_types=1);

namespace VimaTech\SecureFields\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use VimaTech\SecureFields\Contracts\AuditLogger;
use VimaTech\SecureFields\Contracts\Encryptor;
use VimaTech\SecureFields\Exceptions\DecryptionException;
use VimaTech\SecureFields\Support\SecureFieldResolver;
use VimaTech\SecureFields\Traits\HasSecureFields;

class RotateKeysCommand extends Command
{
    protected $signature = 'secure-fields:rotate
        {model : The fully qualified model class}
        {--old-key= : The previous encryption key (base64 encoded)}
        {--fields=* : Specific fields to rotate (defaults to all secure fields)}
        {--chunk=500 : Number of records to process per chunk}
        {--dry-run : Preview changes without persisting}
        {--continue-on-error : Skip values that neither key can read instead of stopping}
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
        $continueOnError = (bool) $this->option('continue-on-error');

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
        $this->info('Values already readable with the current key are left untouched, so an interrupted run resumes by re-running this command.');
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
        $rotated = 0;
        $skipped = 0;
        $failed = 0;
        $aborted = false;

        $modelClass::query()
            ->select(array_merge([$primaryKey], $fields))
            ->chunkById($chunkSize, function ($records) use ($encryptor, $fields, $oldKey, $dryRun, $continueOnError, $progressBar, $table, $primaryKey, &$rotated, &$skipped, &$failed, &$aborted) {
                $chunkUpdates = [];
                $chunkSkipped = 0;

                foreach ($records as $record) {
                    /** @var Model $record */
                    try {
                        $updates = $this->rotateRecord($encryptor, $record, $fields, $oldKey);
                    } catch (DecryptionException $e) {
                        $failed++;
                        $progressBar->advance();

                        Log::error('secure-fields: a value could not be read with either key', [
                            'table' => $table,
                            'key' => $record->getKey(),
                            'error' => $e->getMessage(),
                        ]);

                        if ($continueOnError) {
                            continue;
                        }

                        $aborted = true;

                        break;
                    }

                    if ($updates === []) {
                        $chunkSkipped++;
                    } else {
                        /** @var int|string $key */
                        $key = $record->getKey();
                        $chunkUpdates[$key] = $updates;
                    }

                    $progressBar->advance();
                }

                if ($aborted) {
                    return false;
                }

                if ($chunkUpdates !== [] && ! $dryRun) {
                    DB::transaction(function () use ($chunkUpdates, $table, $primaryKey) {
                        foreach ($chunkUpdates as $key => $updates) {
                            DB::table($table)->where($primaryKey, $key)->update($updates);
                        }
                    });
                }

                $rotated += count($chunkUpdates);
                $skipped += $chunkSkipped;

                return true;
            }, $primaryKey);

        $progressBar->finish();
        $this->newLine(2);

        if ($aborted) {
            $this->error('Rotation stopped at a value that neither the new nor the old key can read.');
            $this->line('Nothing was written for the batch that failed. Repair the record and re-run — rotated values are skipped on the next pass.');
        } else {
            $this->info('Key rotation complete.');
        }

        $this->info("Rotated: {$rotated}");
        $this->info("Already using the current key: {$skipped}");

        if ($failed > 0) {
            $this->warn("Unreadable: {$failed} — the application log names the affected keys.");
        }

        if ($dryRun) {
            $this->info('[DRY RUN] No changes were persisted.');
        } elseif ($rotated > 0) {
            app(AuditLogger::class)->logRotation($modelClass, $rotated);
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Values the current key already reads are left alone, which is what makes an
     * interrupted rotation resumable.
     *
     * @param  array<string>  $fields
     * @return array<string, string>
     *
     * @throws DecryptionException
     */
    private function rotateRecord(Encryptor $encryptor, Model $record, array $fields, string $oldKey): array
    {
        $updates = [];

        foreach ($fields as $field) {
            /** @var string|null $payload */
            $payload = $record->getAttributes()[$field] ?? null;

            if ($payload === null || $this->readableWithCurrentKey($encryptor, $payload)) {
                continue;
            }

            $updates[$field] = $encryptor->rotate($payload, $oldKey);
        }

        return $updates;
    }

    private function readableWithCurrentKey(Encryptor $encryptor, string $payload): bool
    {
        try {
            $encryptor->decrypt($payload);

            return true;
        } catch (DecryptionException) {
            return false;
        }
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
        $validFields = SecureFieldResolver::resolve($instance);

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
}
