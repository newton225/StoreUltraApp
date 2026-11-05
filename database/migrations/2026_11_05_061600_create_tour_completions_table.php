<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The guided tours a member has already seen, kept per member (not in the browser) so that a tour
     * does not start again on another device.
     */
    public function up(): void
    {
        Schema::create('tour_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tour', 40);
            $table->timestamp('completed_at');
            $table->unique(['user_id', 'tour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_completions');
    }
};
