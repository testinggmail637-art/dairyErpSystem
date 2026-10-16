<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expenses (spec Section 6, Phase 1). Saved directly — there are NO approval columns.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->date('expense_date');
            $table->decimal('amount', 12, 2);
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('expense_categories')->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('animal_id')->nullable();          // FK added in Phase 2 (animals table)
            $table->foreignId('vendor_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('paid_to_text', 150)->nullable();
            $table->boolean('is_credit')->default(false);                 // true = not paid yet, adds to vendor payable
            $table->foreignId('payment_mode_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->string('unit', 30)->nullable();
            $table->string('receipt_path')->nullable();                    // private disk
            $table->text('note')->nullable();
            $table->nullableMorphs('source');                             // auto-created by treatment, feed purchase, salary run
            $table->unsignedBigInteger('import_batch_id')->nullable();    // FK added with expense_import_batches (Task 1.8)
            $table->userStamps();
            $table->timestamps();
            $table->softDeletes();

            // Every filter of the expense list / reports leads with farm_id and ends with the date.
            // The first one also covers totals (COUNT/SUM of amount by paid/credit for a date range) and the default
            // newest-first page without reading rows; deleted_at is in it because every query has "deleted_at IS NULL".
            $table->index(['farm_id', 'deleted_at', 'expense_date', 'is_credit', 'amount'], 'expenses_farm_date_totals_index');
            // Newest-first paging: InnoDB appends the primary key, so this serves ORDER BY expense_date DESC, id DESC.
            $table->index(['farm_id', 'deleted_at', 'expense_date'], 'expenses_farm_date_order_index');
            // Filter + totals without reading rows (amount/is_credit at the end make them covering).
            $table->index(['farm_id', 'category_id', 'deleted_at', 'expense_date', 'is_credit', 'amount'], 'expenses_farm_category_index');
            $table->index(['farm_id', 'section_id', 'deleted_at', 'expense_date', 'is_credit', 'amount'], 'expenses_farm_section_index');
            $table->index(['farm_id', 'vendor_id', 'deleted_at', 'expense_date', 'is_credit', 'amount'], 'expenses_farm_vendor_index');
            $table->index(['farm_id', 'is_credit', 'expense_date']);
            $table->index(['farm_id', 'created_by', 'expense_date']);
            $table->index('import_batch_id');
            $table->index('animal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
