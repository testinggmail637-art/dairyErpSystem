<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milk_collections', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->date('collection_date');
            $table->string('shift', 10);                          // MilkShift
            $table->foreignId('farmer_id')->constrained()->restrictOnDelete();
            $table->string('milk_type', 20);                      // MilkType
            $table->decimal('quantity_litre', 10, 2);
            $table->decimal('fat', 5, 2)->nullable();
            $table->decimal('snf', 5, 2)->nullable();
            $table->decimal('clr', 5, 2)->nullable();
            $table->foreignId('rate_chart_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('rate_per_litre', 10, 2);              // snapshot
            $table->decimal('amount', 12, 2);                      // snapshot = qty × rate (0 when rejected)
            $table->string('quality_status', 20);                 // QualityStatus
            $table->string('rejection_reason', 255)->nullable();
            $table->unsignedBigInteger('farmer_payment_id')->nullable(); // set when in a payment statement (locks it); FK with farmer_payments (4.6)
            $table->uuid('client_uuid')->nullable()->unique();    // idempotent entry (spec 4.7)
            $table->string('notes', 500)->nullable();
            $table->userStamps();
            $table->timestamps();

            $table->unique(['farmer_id', 'collection_date', 'shift', 'milk_type'], 'milk_collections_one_per_shift');
            $table->index(['farm_id', 'collection_date', 'shift']);
            $table->index(['farm_id', 'farmer_id', 'collection_date']);
            $table->index('farmer_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milk_collections');
    }
};
