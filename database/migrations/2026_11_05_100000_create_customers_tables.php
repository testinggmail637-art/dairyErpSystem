<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('code', 20);                         // auto C-0001 per farm
            $table->string('name', 150);
            $table->string('customer_type', 30);                // CustomerType
            $table->string('b2b_category', 30)->nullable();     // B2bCategory, only for b2b
            $table->string('phone', 15)->nullable();
            $table->string('alt_phone', 15)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('area', 100)->nullable();
            $table->foreignId('route_id')->nullable()->constrained('routes')->restrictOnDelete();
            $table->unsignedInteger('route_sequence')->nullable();
            $table->string('gstin', 15)->nullable();
            $table->decimal('opening_balance', 12, 2)->default(0); // + = customer owes us
            $table->decimal('credit_limit', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('notes', 1000)->nullable();
            $table->userStamps();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['farm_id', 'code']);
            $table->index(['farm_id', 'name']);
            $table->index(['farm_id', 'phone']);
            $table->index(['route_id', 'route_sequence']);
        });

        Schema::create('customer_rates', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('rate', 10, 2);
            $table->date('effective_from');
            $table->userStamps();
            $table->timestamps();

            // Also serves PricingService: latest effective_from <= date for a customer + product.
            $table->unique(['customer_id', 'product_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_rates');
        Schema::dropIfExists('customers');
    }
};
