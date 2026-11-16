<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Animal traders we buy animals from / sell animals to (spec Phase 6). Master data → soft deletes.
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('name', 150);
            $table->string('phone', 15)->nullable();
            $table->string('village', 100)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('party_type', 10);                       // PartyType
            $table->decimal('opening_balance', 12, 2)->default(0); // + = they owe us, − = we owe them
            $table->boolean('is_active')->default(true);
            $table->userStamps();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['farm_id', 'name']);
            $table->index(['farm_id', 'phone']);
            $table->index(['farm_id', 'party_type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
