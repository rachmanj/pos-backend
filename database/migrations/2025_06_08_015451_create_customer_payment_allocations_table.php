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
        Schema::create('customer_payment_allocations', function (Blueprint $table) {
            $table->id();

            // Payment allocation relationships
            $table->foreignId('customer_payment_receive_id')->constrained()->onDelete('cascade');
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');

            // Allocation details
            $table->decimal('allocated_amount', 15, 2);
            $table->date('allocation_date');
            $table->enum('allocation_type', ['automatic', 'manual', 'partial', 'overpayment', 'advance'])->default('manual');

            // Status and workflow
            $table->enum('status', ['pending', 'applied', 'reversed', 'cancelled'])->default('pending');
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reversed_at')->nullable();

            // Processing information
            $table->foreignId('allocated_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();

            // Additional information
            $table->text('notes')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->json('metadata')->nullable();

            // Audit fields
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['customer_payment_receive_id', 'sale_id'], 'cpa_payment_sale_idx');
            $table->index(['customer_id', 'allocation_date'], 'cpa_customer_date_idx');
            $table->index(['status', 'allocation_type'], 'cpa_status_type_idx');
            $table->index('allocation_date', 'cpa_allocation_date_idx');

            // Unique constraint to prevent duplicate allocations
            $table->unique(['customer_payment_receive_id', 'sale_id'], 'cpa_payment_sale_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_payment_allocations');
    }
};
