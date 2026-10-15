<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Running number per farm, document type and financial year (spec Section 4.6).
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('type', 30);
            $table->string('financial_year', 7); // e.g. 2026-27
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['farm_id', 'type', 'financial_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
