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
        Schema::create('customer_credit_limits', function (Blueprint $table) {
            $table->id();

            // Customer relationship
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');

            // Credit limit information
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->decimal('available_credit', 15, 2)->default(0);
            $table->decimal('overdue_amount', 15, 2)->default(0);

            // Payment terms
            $table->integer('payment_terms_days')->default(30); // Net 30, Net 15, etc.
            $table->enum('payment_terms_type', ['cash', 'net_15', 'net_30', 'net_60', 'net_90', 'custom'])->default('net_30');
            $table->decimal('early_payment_discount_percentage', 5, 2)->default(0);
            $table->integer('early_payment_discount_days')->default(0);

            // Credit status and risk
            $table->enum('credit_status', ['good', 'warning', 'blocked', 'suspended', 'defaulted'])->default('good');
            $table->integer('credit_score')->default(100); // 0-100 scale
            $table->decimal('payment_reliability_score', 5, 2)->default(100.00); // Percentage

            // Credit history tracking
            $table->date('last_review_date')->nullable();
            $table->date('next_review_date')->nullable();
            $table->integer('days_past_due')->default(0);
            $table->integer('payment_delay_count')->default(0);
            $table->integer('late_payment_count')->default(0);

            // Credit management
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();

            // Risk management
            $table->boolean('requires_approval')->default(false);
            $table->decimal('auto_approval_limit', 15, 2)->default(0);
            $table->text('credit_notes')->nullable();
            $table->text('risk_assessment')->nullable();

            // Audit fields
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('customer_id', 'ccl_customer_idx');
            $table->index(['credit_status', 'credit_score'], 'ccl_status_score_idx');
            $table->index(['payment_terms_type', 'payment_terms_days'], 'ccl_terms_idx');
            $table->index('next_review_date', 'ccl_review_date_idx');

            // Unique constraint - one credit limit per customer
            $table->unique('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_credit_limits');
    }
};
