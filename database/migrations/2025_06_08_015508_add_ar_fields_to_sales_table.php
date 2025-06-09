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
        Schema::table('sales', function (Blueprint $table) {
            // Credit sale information
            $table->enum('payment_type', ['cash', 'credit', 'partial_credit'])->default('cash')->after('type');
            $table->integer('payment_terms_days')->nullable()->after('payment_type');
            $table->date('due_date')->nullable()->after('payment_terms_days');
            $table->enum('credit_status', ['approved', 'pending', 'rejected'])->nullable()->after('due_date');

            // AR tracking
            $table->enum('payment_status', ['paid', 'partial', 'unpaid', 'overdue', 'written_off'])->default('paid')->after('credit_status');
            $table->decimal('outstanding_amount', 15, 2)->default(0)->after('payment_status');
            $table->decimal('allocated_payments', 15, 2)->default(0)->after('outstanding_amount');
            $table->integer('days_overdue')->default(0)->after('allocated_payments');

            // Credit approval workflow
            $table->foreignId('credit_approved_by')->nullable()->constrained('users')->onDelete('set null')->after('days_overdue');
            $table->timestamp('credit_approved_at')->nullable()->after('credit_approved_by');
            $table->text('credit_approval_notes')->nullable()->after('credit_approved_at');

            // Payment tracking
            $table->date('last_payment_date')->nullable()->after('credit_approval_notes');
            $table->decimal('last_payment_amount', 15, 2)->nullable()->after('last_payment_date');
            $table->date('first_overdue_date')->nullable()->after('last_payment_amount');

            // Collection and follow-up
            $table->enum('collection_status', ['current', 'follow_up', 'collection', 'legal', 'written_off'])->default('current')->after('first_overdue_date');
            $table->date('next_follow_up_date')->nullable()->after('collection_status');
            $table->foreignId('assigned_collector')->nullable()->constrained('users')->onDelete('set null')->after('next_follow_up_date');

            // Late fees and penalties
            $table->decimal('late_fee_amount', 15, 2)->default(0)->after('assigned_collector');
            $table->decimal('penalty_amount', 15, 2)->default(0)->after('late_fee_amount');
            $table->decimal('total_fees', 15, 2)->default(0)->after('penalty_amount');

            // Write-off information
            $table->decimal('written_off_amount', 15, 2)->default(0)->after('total_fees');
            $table->date('written_off_date')->nullable()->after('written_off_amount');
            $table->foreignId('written_off_by')->nullable()->constrained('users')->onDelete('set null')->after('written_off_date');
            $table->text('write_off_reason')->nullable()->after('written_off_by');

            // Indexes for AR performance
            $table->index(['payment_status', 'due_date'], 'sales_payment_due_idx');
            $table->index(['collection_status', 'next_follow_up_date'], 'sales_collection_followup_idx');
            $table->index(['customer_id', 'payment_status'], 'sales_customer_payment_idx');
            $table->index(['days_overdue', 'outstanding_amount'], 'sales_overdue_amount_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['payment_status', 'due_date']);
            $table->dropIndex(['collection_status', 'next_follow_up_date']);
            $table->dropIndex(['customer_id', 'payment_status']);
            $table->dropIndex(['days_overdue', 'outstanding_amount']);

            // Drop columns
            $table->dropColumn([
                'payment_type',
                'payment_terms_days',
                'due_date',
                'credit_status',
                'payment_status',
                'outstanding_amount',
                'allocated_payments',
                'days_overdue',
                'credit_approved_by',
                'credit_approved_at',
                'credit_approval_notes',
                'last_payment_date',
                'last_payment_amount',
                'first_overdue_date',
                'collection_status',
                'next_follow_up_date',
                'assigned_collector',
                'late_fee_amount',
                'penalty_amount',
                'total_fees',
                'written_off_amount',
                'written_off_date',
                'written_off_by',
                'write_off_reason'
            ]);
        });
    }
};
