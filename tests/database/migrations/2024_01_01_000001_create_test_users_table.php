<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->text('email')->nullable();
            $table->string('email_hash', 64)->nullable();
            $table->text('phone')->nullable();
            $table->string('phone_hash', 64)->nullable();
            $table->text('ssn')->nullable();
            $table->text('notes')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_users');
    }
};
