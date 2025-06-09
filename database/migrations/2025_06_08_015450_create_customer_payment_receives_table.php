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
        Schema::create('customer_payment_receives', function (Blueprint $table) {
            $table->id();

            // Payment identification
            $table->string('payment_number')->unique();
            $table->string('reference_number')->nullable();

            // Customer and relationship
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained()->onDelete('set null');

            // Payment details
            $table->date('payment_date');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('allocated_amount', 15, 2)->default(0);
            $table->decimal('unallocated_amount', 15, 2)->default(0);

            // Payment method
            $table->foreignId('payment_method_id')->constrained()->onDelete('restrict');
            $table->string('payment_reference')->nullable(); // Bank ref, check number, etc.
            $table->text('payment_details')->nullable(); // JSON for additional payment info

            // Status and workflow
            $table->enum('status', ['pending', 'verified', 'allocated', 'completed', 'cancelled'])->default('pending');
            $table->enum('allocation_status', ['unallocated', 'partially_allocated', 'fully_allocated'])->default('unallocated');

            // Processing information
            $table->foreignId('received_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            // Additional information
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('metadata')->nullable();

            // Bank reconciliation
            $table->boolean('is_reconciled')->default(false);
            $table->date('reconciled_date')->nullable();
            $table->string('bank_statement_reference')->nullable();

            // Audit fields
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['customer_id', 'payment_date'], 'cpr_customer_date_idx');
            $table->index(['status', 'allocation_status'], 'cpr_status_allocation_idx');
            $table->index(['payment_date', 'warehouse_id'], 'cpr_date_warehouse_idx');
            $table->index('payment_number', 'cpr_payment_number_idx');
            $table->index('reference_number', 'cpr_reference_number_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_payment_receives');
    }
};
