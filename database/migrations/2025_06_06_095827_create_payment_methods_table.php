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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Payment method code (e.g., CASH, CARD, TRANSFER)
            $table->string('name'); // Payment method name
            $table->text('description')->nullable(); // Description

            // Payment Method Type
            $table->enum('type', [
                'cash',
                'card',
                'bank_transfer',
                'digital_wallet',
                'credit',
                'voucher',
                'other'
            ])->default('cash');

            // Configuration
            $table->boolean('is_active')->default(true); // Is method active
            $table->boolean('requires_reference')->default(false); // Requires reference number
            $table->boolean('has_processing_fee')->default(false); // Has processing fee
            $table->decimal('processing_fee_percentage', 5, 2)->default(0); // Processing fee %
            $table->decimal('processing_fee_fixed', 10, 2)->default(0); // Fixed processing fee
            $table->decimal('minimum_amount', 10, 2)->default(0); // Minimum transaction amount
            $table->decimal('maximum_amount', 15, 2)->nullable(); // Maximum transaction amount

            // Cash Handling
            $table->boolean('affects_cash_drawer')->default(false); // Affects cash drawer balance
            $table->boolean('requires_change')->default(false); // Can give change (cash)

            // Integration Settings
            $table->string('gateway_provider')->nullable(); // Payment gateway provider
            $table->json('gateway_config')->nullable(); // Gateway configuration (JSON)
            $table->string('account_number')->nullable(); // Bank account or wallet number

            // Display Settings
            $table->string('icon')->nullable(); // Icon for UI
            $table->string('color')->nullable(); // Color for UI
            $table->integer('sort_order')->default(0); // Display order

            // Timestamps
            $table->timestamps();

            // Indexes for performance
            $table->index(['is_active', 'type']);
            $table->index('sort_order');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
