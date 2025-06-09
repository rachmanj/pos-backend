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
        Schema::create('customer_payment_schedules', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('sale_id')->nullable()->constrained()->onDelete('cascade');

            // Schedule identification
            $table->string('schedule_number')->unique();
            $table->string('schedule_name');
            $table->text('description')->nullable();

            // Schedule amounts
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2);
            $table->decimal('installment_amount', 15, 2);

            // Schedule configuration
            $table->enum('frequency', ['weekly', 'bi_weekly', 'monthly', 'quarterly', 'custom'])->default('monthly');
            $table->integer('frequency_days')->nullable(); // For custom frequency
            $table->integer('total_installments');
            $table->integer('completed_installments')->default(0);

            // Schedule dates
            $table->date('start_date');
            $table->date('end_date');
            $table->date('next_payment_date');
            $table->date('last_payment_date')->nullable();

            // Status and management
            $table->enum('status', ['active', 'completed', 'suspended', 'cancelled', 'defaulted'])->default('active');
            $table->boolean('auto_generate_reminders')->default(true);
            $table->integer('reminder_days_before')->default(3);

            // Late fees and penalties
            $table->decimal('late_fee_percentage', 5, 2)->default(0);
            $table->decimal('late_fee_amount', 15, 2)->default(0);
            $table->integer('grace_period_days')->default(0);
            $table->decimal('total_late_fees', 15, 2)->default(0);

            // Processing information
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();

            // Additional information
            $table->text('terms_and_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            // Audit fields
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['customer_id', 'status'], 'cps_customer_status_idx');
            $table->index(['next_payment_date', 'status'], 'cps_next_payment_idx');
            $table->index(['sale_id', 'status'], 'cps_sale_status_idx');
            $table->index('schedule_number', 'cps_schedule_number_idx');
            $table->index(['frequency', 'start_date'], 'cps_frequency_start_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_payment_schedules');
    }
};
