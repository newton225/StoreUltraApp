<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Searches ignore accents (« eleonore » finds « Éléonore »): PostgreSQL does it with its `unaccent` extension,
 * created here with the migration role. SQLite has nothing to enable (the function is registered in PHP).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        }
    }

    /**
     * Reverse the migrations. The extension stays: other searches may rely on it.
     */
    public function down(): void {}
};
