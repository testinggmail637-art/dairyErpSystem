<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('shift', 30);                              // MilkShift
            $table->decimal('quantity', 10, 2);
            $table->string('stock_source', 30)->default('own_farm');  // MilkSource
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 30)->default('active');          // SubscriptionStatus
            $table->string('notes', 500)->nullable();
            $table->userStamps();
            $table->timestamps();

            // Delivery sheet generation: active subscriptions valid on a date.
            $table->index(['farm_id', 'status', 'start_date']);
            $table->index(['customer_id', 'product_id', 'shift']);
        });

        Schema::create('subscription_pauses', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('reason', 255)->nullable();
            $table->userStamps();
            $table->timestamps();

            $table->index(['subscription_id', 'from_date']);
            $table->index(['farm_id', 'from_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_pauses');
        Schema::dropIfExists('subscriptions');
    }
};
