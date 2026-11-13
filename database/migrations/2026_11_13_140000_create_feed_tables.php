<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_items', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('name', 100);
            $table->string('unit', 10);                             // FeedUnit
            $table->decimal('reorder_level', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->userStamps();
            $table->timestamps();

            $table->unique(['farm_id', 'name']);
        });

        Schema::create('feed_transactions', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('feed_item_id')->constrained()->restrictOnDelete();
            $table->date('transaction_date');
            $table->string('type', 20);                             // FeedTransactionType
            $table->decimal('quantity', 10, 2);                     // always > 0; the type says in or out
            $table->decimal('rate', 10, 2)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_credit')->default(false);
            $table->foreignId('payment_mode_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->restrictOnDelete(); // consumption by one animal
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();    // purchase → its expense
            $table->string('notes', 500)->nullable();
            $table->userStamps();
            $table->timestamps();

            $table->index(['farm_id', 'transaction_date']);
            $table->index(['farm_id', 'feed_item_id', 'type', 'transaction_date'], 'feed_transactions_item_index'); // stock + 90-day cost
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_transactions');
        Schema::dropIfExists('feed_items');
    }
};
