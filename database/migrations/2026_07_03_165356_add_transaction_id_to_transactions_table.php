<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add transaction_id column if transactions table exists.
     */
    public function up(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        if (!Schema::hasColumn('transactions', 'transaction_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('transaction_id')->nullable()->after('reference_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'transaction_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('transaction_id');
            });
        }
    }
};
