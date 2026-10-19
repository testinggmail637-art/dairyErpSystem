<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** expenses.animal_id was a plain column until the animals table existed (spec Section 6: "FK added in Phase 2"). */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('animal_id')->references('id')->on('animals')->restrictOnDelete();
            // the animal profile's Expenses tab: one animal's expenses by date, summed without reading rows
            $table->index(['animal_id', 'deleted_at', 'expense_date', 'amount'], 'expenses_animal_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['animal_id']);
            $table->dropIndex('expenses_animal_date_index');
        });
    }
};
