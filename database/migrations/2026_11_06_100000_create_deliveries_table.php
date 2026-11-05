<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->date('delivery_date');
            $table->string('shift', 30);                                  // MilkShift
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('routes')->restrictOnDelete(); // customer's route snapshot
            $table->foreignId('subscription_id')->nullable()->constrained()->restrictOnDelete();  // null = one-off / B2B dispatch
            $table->string('stock_source', 30)->default('own_farm');      // MilkSource
            $table->decimal('planned_qty', 10, 2)->default(0);
            $table->decimal('delivered_qty', 10, 2)->default(0);
            $table->decimal('rate', 10, 2);                               // PricingService snapshot
            $table->decimal('amount', 12, 2)->default(0);                 // delivered_qty × rate
            $table->string('status', 30)->default('pending');             // DeliveryStatus
            $table->string('skip_reason', 255)->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->decimal('fat', 5, 2)->nullable();
            $table->decimal('snf', 5, 2)->nullable();
            $table->decimal('buyer_measured_qty', 10, 2)->nullable();
            $table->decimal('buyer_fat', 5, 2)->nullable();
            $table->decimal('buyer_snf', 5, 2)->nullable();
            $table->unsignedBigInteger('bill_id')->nullable()->index();  // FK to bills is added with the billing task
            $table->uuid('client_uuid')->nullable()->unique();
            $table->string('notes', 500)->nullable();
            $table->userStamps();
            $table->timestamps();

            $table->unique(['customer_id', 'product_id', 'delivery_date', 'shift']);
            $table->index(['farm_id', 'delivery_date', 'route_id', 'shift'], 'deliveries_farm_date_route_index');
            $table->index(['customer_id', 'delivery_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
