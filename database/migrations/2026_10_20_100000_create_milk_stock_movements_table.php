<?php

use App\Models\MilkProductionRecord;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The milk ledger — source of truth for stock (spec Section 6, Phase 2). Only MilkStockService writes it.
     * Existing milk entries (Task 2.5) get their production movement here, so the stock starts out right.
     */
    public function up(): void
    {
        Schema::create('milk_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->date('movement_date');
            $table->string('shift', 10)->nullable();           // MilkShift
            $table->string('stock_source', 20);                // MilkSource
            $table->string('milk_type', 10);                   // MilkType
            $table->string('movement_type', 20);               // MilkMovementType
            $table->decimal('quantity_in', 10, 2)->default(0);
            $table->decimal('quantity_out', 10, 2)->default(0);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason')->nullable();
            $table->userStamps();
            $table->timestamps();

            // spec index, made covering with the quantities: balance and daily summary read only the index
            $table->index(['farm_id', 'stock_source', 'milk_type', 'movement_date', 'quantity_in', 'quantity_out'], 'milk_stock_balance_index');
            $table->index(['farm_id', 'movement_date', 'movement_type'], 'milk_stock_date_index'); // daily sheet, ledger
            $table->unique(['reference_type', 'reference_id'], 'milk_stock_reference_unique'); // one movement per source record
        });

        DB::statement(<<<'SQL'
            INSERT INTO milk_stock_movements
                (farm_id, movement_date, shift, stock_source, milk_type, movement_type, quantity_in, quantity_out,
                 reference_type, reference_id, created_by, updated_by, created_at, updated_at)
            SELECT r.farm_id, r.production_date, r.shift, 'own_farm', a.animal_type, 'production', r.quantity_litre, 0,
                   ?, r.id, r.created_by, r.updated_by, r.created_at, r.updated_at
            FROM milk_production_records r
            JOIN animals a ON a.id = r.animal_id
            WHERE r.quantity_litre > 0
            SQL, [MilkProductionRecord::class]);
    }

    public function down(): void
    {
        Schema::dropIfExists('milk_stock_movements');
    }
};
