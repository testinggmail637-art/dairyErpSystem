<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Batch revert (spec 7.2 step 5): when and by whom; the reason is in the audit log. */
    public function up(): void
    {
        Schema::table('expense_import_batches', function (Blueprint $table) {
            $table->timestamp('reverted_at')->nullable()->after('imported_at');
            $table->foreignId('reverted_by')->nullable()->after('reverted_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expense_import_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reverted_by');
            $table->dropColumn('reverted_at');
        });
    }
};
