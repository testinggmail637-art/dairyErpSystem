<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Excel import of expenses (spec Section 6 + 7.2): one batch per upload, one row per spreadsheet row. */
    public function up(): void
    {
        Schema::create('expense_import_batches', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('original_filename');
            $table->string('stored_path');                       // private disk
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('warning_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->string('status', 30);                        // ImportBatchStatus
            $table->string('error_file_path')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->userStamps();
            $table->timestamps();

            $table->index(['farm_id', 'created_at']);
        });

        Schema::create('expense_import_rows', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('import_batch_id')->constrained('expense_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');               // spreadsheet row (header = 1)
            $table->json('payload');                             // the row as read, by column key
            $table->json('errors')->nullable();                  // column key => [messages]
            $table->json('warnings')->nullable();
            $table->boolean('is_valid');
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['import_batch_id', 'is_valid', 'row_number']);
        });

        // expenses.import_batch_id was a plain column until this table existed (DECISIONS #87).
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('import_batch_id')->references('id')->on('expense_import_batches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['import_batch_id']);
        });
        Schema::dropIfExists('expense_import_rows');
        Schema::dropIfExists('expense_import_batches');
    }
};
