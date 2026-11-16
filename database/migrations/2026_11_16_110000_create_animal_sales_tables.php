<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Animal sales to a party (spec Phase 6). Cancelled sales stay on record.
        Schema::create('animal_sales', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('sale_no', 30);
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->date('sale_date');
            $table->decimal('total_price', 12, 2);
            $table->decimal('selling_costs', 12, 2)->default(0);  // commission / transport we pay
            $table->decimal('net_amount', 12, 2);                 // total_price − selling_costs
            $table->string('notes', 1000)->nullable();
            $table->string('status', 10)->default('completed');  // AnimalSaleStatus
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('cancel_reason', 500)->nullable();
            $table->userStamps();
            $table->timestamps();

            $table->unique(['farm_id', 'sale_no']);
            $table->index(['farm_id', 'sale_date']);
            $table->index(['farm_id', 'party_id', 'sale_date']);
        });

        Schema::create('animal_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete(); // not unique: a cancelled sale can be sold again
            $table->decimal('price', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_sale_items');
        Schema::dropIfExists('animal_sales');
    }
};
