<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Money given to a worker before salary; recovered through salary slips (spec Phase 6). Not an expense.
        Schema::create('staff_advances', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('staff_member_id')->constrained()->restrictOnDelete();
            $table->date('advance_date');
            $table->decimal('amount', 12, 2);
            $table->foreignId('payment_mode_id')->constrained()->restrictOnDelete();
            $table->string('note', 500)->nullable();
            $table->uuid('client_uuid')->nullable()->unique(); // idempotent form submit (spec 4.7)
            $table->userStamps();
            $table->timestamps();

            $table->index(['farm_id', 'advance_date']);
            $table->index(['staff_member_id', 'advance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_advances');
    }
};
