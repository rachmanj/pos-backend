<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // payment_terms already exists as integer, so we'll keep it as is
            $table->decimal('credit_limit', 15, 2)->default(0)->after('status');
            $table->decimal('current_balance', 15, 2)->default(0)->after('credit_limit');
            $table->string('bank_name')->nullable()->after('current_balance');
            $table->string('bank_account_number')->nullable()->after('bank_name');
            $table->string('bank_account_name')->nullable()->after('bank_account_number');

            // Index for payment queries
            $table->index('current_balance', 'suppliers_balance_idx');
            $table->index('payment_terms', 'suppliers_terms_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex('suppliers_balance_idx');
            $table->dropIndex('suppliers_terms_idx');
            $table->dropColumn([
                'credit_limit',
                'current_balance',
                'bank_name',
                'bank_account_number',
                'bank_account_name'
            ]);
        });
    }
};
