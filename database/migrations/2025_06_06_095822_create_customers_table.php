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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code')->unique(); // Customer code (e.g., CUST001, CUST002)
            $table->string('name'); // Customer name
            $table->string('email')->nullable()->unique(); // Email address
            $table->string('phone')->nullable(); // Phone number
            $table->date('birth_date')->nullable(); // Birth date for promotions
            $table->enum('gender', ['male', 'female', 'other'])->nullable(); // Gender

            // Address Information
            $table->text('address')->nullable(); // Full address
            $table->string('city')->nullable(); // City
            $table->string('state')->nullable(); // State/Province
            $table->string('postal_code')->nullable(); // Postal code
            $table->string('country')->default('Indonesia'); // Country

            // Customer Type and Status
            $table->enum('type', ['regular', 'vip', 'wholesale', 'member'])->default('regular');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');

            // Loyalty and Credit Information
            $table->decimal('credit_limit', 15, 2)->default(0); // Credit limit for wholesale customers
            $table->decimal('total_spent', 15, 2)->default(0); // Total amount spent (for analytics)
            $table->integer('total_orders')->default(0); // Total number of orders
            $table->decimal('loyalty_points', 10, 2)->default(0); // Loyalty points
            $table->date('last_purchase_date')->nullable(); // Last purchase date

            // Additional Information
            $table->string('tax_number')->nullable(); // Tax ID for business customers
            $table->string('company_name')->nullable(); // Company name for business customers
            $table->text('notes')->nullable(); // Additional notes
            $table->json('preferences')->nullable(); // Customer preferences (JSON)

            // Referral System
            $table->foreignId('referred_by')->nullable()->constrained('customers')->onDelete('set null');
            $table->integer('referral_count')->default(0); // Number of successful referrals

            // Timestamps
            $table->timestamps();

            // Indexes for performance
            $table->index(['status', 'type']);
            $table->index(['total_spent', 'last_purchase_date']);
            $table->index('customer_code');
            $table->index(['phone', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
