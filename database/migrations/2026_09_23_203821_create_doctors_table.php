<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('specialty');
            $table->timestamps();
            $table->softDeletes();
        });

        // Partial unique index for active doctor emails (SQLite compatible)
        // Only active (non-soft-deleted) records participate in email uniqueness
        DB::statement('
            CREATE UNIQUE INDEX doctors_email_unique_active
            ON doctors(email)
            WHERE deleted_at IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the partial unique index first
        DB::statement('DROP INDEX IF EXISTS doctors_email_unique_active');
        Schema::dropIfExists('doctors');
    }
};
