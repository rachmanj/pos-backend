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
        Schema::create('customer_aging_snapshots', function (Blueprint $table) {
            $table->id();

            // Customer and snapshot information
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->date('snapshot_date');
            $table->enum('snapshot_type', ['daily', 'weekly', 'monthly', 'quarterly', 'manual'])->default('daily');

            // Aging buckets (Indonesian business standard)
            $table->decimal('current_amount', 15, 2)->default(0); // 0-30 days
            $table->decimal('days_31_60', 15, 2)->default(0);     // 31-60 days
            $table->decimal('days_61_90', 15, 2)->default(0);     // 61-90 days
            $table->decimal('days_91_120', 15, 2)->default(0);    // 91-120 days
            $table->decimal('days_over_120', 15, 2)->default(0);  // Over 120 days
            $table->decimal('total_outstanding', 15, 2)->default(0);

            // Additional aging analysis
            $table->decimal('overdue_amount', 15, 2)->default(0);
            $table->integer('overdue_invoices_count')->default(0);
            $table->integer('total_invoices_count')->default(0);
            $table->integer('days_oldest_invoice')->default(0);

            // Credit and payment information
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('available_credit', 15, 2)->default(0);
            $table->decimal('credit_utilization_percentage', 5, 2)->default(0);

            // Payment behavior metrics
            $table->decimal('average_days_to_pay', 5, 2)->default(0);
            $table->integer('payment_terms_days')->default(30);
            $table->decimal('payment_reliability_score', 5, 2)->default(100.00);
            $table->integer('late_payments_count')->default(0);

            // Risk assessment
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->enum('collection_status', ['current', 'follow_up', 'collection', 'legal', 'write_off'])->default('current');
            $table->text('risk_notes')->nullable();

            // Snapshot metadata
            $table->foreignId('generated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('generated_at');
            $table->json('calculation_metadata')->nullable();

            // Audit fields
            $table->timestamps();

            // Indexes for performance
            $table->index(['customer_id', 'snapshot_date'], 'cas_customer_date_idx');
            $table->index(['snapshot_date', 'snapshot_type'], 'cas_date_type_idx');
            $table->index(['risk_level', 'collection_status'], 'cas_risk_collection_idx');
            $table->index('total_outstanding', 'cas_total_outstanding_idx');
            $table->index('overdue_amount', 'cas_overdue_amount_idx');

            // Unique constraint to prevent duplicate snapshots
            $table->unique(['customer_id', 'snapshot_date', 'snapshot_type'], 'cas_unique_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_aging_snapshots');
    }
};
