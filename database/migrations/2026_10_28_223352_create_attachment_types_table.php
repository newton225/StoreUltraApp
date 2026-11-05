<?php

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
        Schema::create('attachment_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label_fr');
            $table->string('label_en');
            $table->string('applies_to');
            $table->boolean('required')->default(false);
            $table->boolean('has_expiry')->default(false);
            $table->boolean('on_documents')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachment_types');
    }
};
