<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per staff member and day (spec Phase 6).
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('staff_member_id')->constrained()->restrictOnDelete();
            $table->date('attendance_date');
            $table->string('status', 10);            // AttendanceStatus
            $table->string('note', 255)->nullable();
            $table->userStamps();
            $table->timestamps();

            $table->unique(['staff_member_id', 'attendance_date']);
            $table->index(['farm_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
