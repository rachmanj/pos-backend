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
        Schema::table('customers', function (Blueprint $table) {
            // AR balance tracking
            $table->decimal('current_ar_balance', 15, 2)->default(0)->after('total_spent');
            $table->decimal('overdue_balance', 15, 2)->default(0)->after('current_ar_balance');
            $table->decimal('total_credit_used', 15, 2)->default(0)->after('overdue_balance');

            // Payment behavior tracking
            $table->date('last_payment_date')->nullable()->after('last_purchase_date');
            $table->decimal('last_payment_amount', 15, 2)->nullable()->after('last_payment_date');
            $table->decimal('average_payment_days', 5, 2)->default(0)->after('last_payment_amount');
            $table->integer('total_late_payments')->default(0)->after('average_payment_days');

            // Credit status and risk
            $table->enum('ar_status', ['current', 'overdue', 'collection', 'suspended', 'written_off'])->default('current')->after('status');
            $table->integer('days_past_due')->default(0)->after('ar_status');
            $table->date('first_overdue_date')->nullable()->after('days_past_due');

            // Collection and follow-up
            $table->date('last_statement_date')->nullable()->after('first_overdue_date');
            $table->date('next_statement_date')->nullable()->after('last_statement_date');
            $table->integer('statement_frequency_days')->default(30)->after('next_statement_date');

            // Risk assessment
            $table->enum('credit_risk_level', ['low', 'medium', 'high', 'critical'])->default('low')->after('statement_frequency_days');
            $table->decimal('payment_reliability_score', 5, 2)->default(100.00)->after('credit_risk_level');
            $table->integer('credit_score')->default(100)->after('payment_reliability_score');

            // Write-off tracking
            $table->decimal('total_written_off', 15, 2)->default(0)->after('credit_score');
            $table->date('last_write_off_date')->nullable()->after('total_written_off');

            // Indexes for AR performance
            $table->index(['ar_status', 'days_past_due']);
            $table->index(['credit_risk_level', 'current_ar_balance']);
            $table->index(['overdue_balance', 'first_overdue_date']);
            $table->index('next_statement_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['ar_status', 'days_past_due']);
            $table->dropIndex(['credit_risk_level', 'current_ar_balance']);
            $table->dropIndex(['overdue_balance', 'first_overdue_date']);
            $table->dropIndex('next_statement_date');

            // Drop columns
            $table->dropColumn([
                'current_ar_balance',
                'overdue_balance',
                'total_credit_used',
                'last_payment_date',
                'last_payment_amount',
                'average_payment_days',
                'total_late_payments',
                'ar_status',
                'days_past_due',
                'first_overdue_date',
                'last_statement_date',
                'next_statement_date',
                'statement_frequency_days',
                'credit_risk_level',
                'payment_reliability_score',
                'credit_score',
                'total_written_off',
                'last_write_off_date'
            ]);
        });
    }
};
