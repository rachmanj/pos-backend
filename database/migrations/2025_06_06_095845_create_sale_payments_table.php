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
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('sale_id')->constrained('sales')->onDelete('cascade'); // Parent sale
            $table->foreignId('payment_method_id')->constrained('payment_methods')->onDelete('cascade'); // Payment method used
            $table->foreignId('processed_by')->constrained('users')->onDelete('cascade'); // Who processed this payment

            // Payment Information
            $table->decimal('amount', 15, 2); // Payment amount
            $table->decimal('received_amount', 15, 2)->default(0); // Amount actually received (for cash)
            $table->decimal('change_amount', 15, 2)->default(0); // Change given (for cash)
            $table->decimal('processing_fee', 15, 2)->default(0); // Processing fee charged

            // Payment Status
            $table->enum('status', [
                'pending',
                'completed',
                'failed',
                'cancelled',
                'refunded',
                'partially_refunded'
            ])->default('completed');

            // Reference Information
            $table->string('reference_number')->nullable(); // Card transaction ref, bank transfer ref, etc.
            $table->string('approval_code')->nullable(); // Card approval code
            $table->string('gateway_transaction_id')->nullable(); // Payment gateway transaction ID

            // Card/Digital Payment Information
            $table->string('card_last_four')->nullable(); // Last 4 digits of card
            $table->string('card_type')->nullable(); // Visa, Mastercard, etc.
            $table->string('card_holder_name')->nullable(); // Cardholder name

            // Bank Transfer Information
            $table->string('bank_name')->nullable(); // Bank name for transfers
            $table->string('account_number')->nullable(); // Account number (masked)
            $table->string('transfer_receipt')->nullable(); // Transfer receipt number

            // Digital Wallet Information
            $table->string('wallet_provider')->nullable(); // GoPay, OVO, Dana, etc.
            $table->string('wallet_account')->nullable(); // Wallet account (masked)

            // Cash Information
            $table->json('denominations_received')->nullable(); // Cash denominations received
            $table->json('denominations_given')->nullable(); // Change denominations given

            // Voucher/Credit Information
            $table->string('voucher_code')->nullable(); // Voucher code used
            $table->decimal('voucher_value', 15, 2)->default(0); // Voucher face value
            $table->string('credit_account')->nullable(); // Credit account reference

            // Timing
            $table->timestamp('paid_at'); // When payment was made
            $table->timestamp('verified_at')->nullable(); // When payment was verified
            $table->timestamp('settled_at')->nullable(); // When payment was settled

            // Refund Information
            $table->decimal('refunded_amount', 15, 2)->default(0); // Amount refunded
            $table->timestamp('refunded_at')->nullable(); // When refund was processed
            $table->string('refund_reference')->nullable(); // Refund reference number
            $table->text('refund_reason')->nullable(); // Reason for refund

            // Additional Information
            $table->text('notes')->nullable(); // Payment notes
            $table->json('gateway_response')->nullable(); // Raw gateway response (JSON)
            $table->json('metadata')->nullable(); // Additional metadata (JSON)

            // Audit Trail
            $table->string('terminal_id')->nullable(); // POS terminal ID
            $table->string('receipt_number')->nullable(); // Payment receipt number
            $table->boolean('requires_signature')->default(false); // Requires customer signature
            $table->string('signature_path')->nullable(); // Path to signature image

            // Timestamps
            $table->timestamps();

            // Indexes for performance
            $table->index(['sale_id', 'payment_method_id']);
            $table->index(['status', 'paid_at']);
            $table->index('reference_number');
            $table->index('gateway_transaction_id');
            $table->index(['processed_by', 'paid_at']);
            $table->index(['voucher_code', 'amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
