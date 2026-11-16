<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Animal purchases from a party; the animals are created inline (spec Phase 6).
        Schema::create('animal_purchases', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('purchase_no', 30);
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->date('purchase_date');
            $table->decimal('transport_cost', 12, 2)->default(0);
            $table->decimal('commission', 12, 2)->default(0);
            $table->decimal('other_cost', 12, 2)->default(0);
            $table->decimal('total_animal_price', 12, 2);
            $table->decimal('total_amount', 12, 2);   // animal prices + costs
            $table->string('notes', 1000)->nullable();
            $table->userStamps();
            $table->timestamps();

            $table->unique(['farm_id', 'purchase_no']);
            $table->index(['farm_id', 'purchase_date']);
            $table->index(['farm_id', 'party_id', 'purchase_date']);
        });

        Schema::create('animal_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->unique()->constrained()->restrictOnDelete(); // an animal is bought once
            $table->decimal('price', 12, 2);
            $table->decimal('allocated_cost', 12, 2);  // share of transport + commission + other, by price
            $table->decimal('total_cost', 12, 2);      // price + allocated_cost
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_purchase_items');
        Schema::dropIfExists('animal_purchases');
    }
};
