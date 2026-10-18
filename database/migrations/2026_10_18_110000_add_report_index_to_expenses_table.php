<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expense reports group a date range by category / subcategory / section and sum the amount
     * (category-wise and monthly category × section). This covering index answers them without reading rows:
     * measured on 100 000 expenses, category-wise for a year went from 3.8 s to well under a second.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->index(
                ['farm_id', 'deleted_at', 'expense_date', 'category_id', 'subcategory_id', 'section_id', 'amount'],
                'expenses_farm_date_report_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_farm_date_report_index');
        });
    }
};
