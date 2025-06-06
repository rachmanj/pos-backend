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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique(); // Sale number (e.g., SAL001-20250606-001)
            $table->string('receipt_number')->unique(); // Receipt number for customer

            // Relationships
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade'); // Which warehouse
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null'); // Customer (optional)
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->onDelete('set null'); // Cash session
            $table->foreignId('served_by')->constrained('users')->onDelete('cascade'); // Sales person

            // Sale Status and Type
            $table->enum('status', [
                'draft',
                'completed',
                'partially_refunded',
                'fully_refunded',
                'cancelled',
                'on_hold'
            ])->default('completed');

            $table->enum('type', ['pos', 'online', 'phone', 'walk_in'])->default('pos');

            // Timing
            $table->timestamp('sale_date'); // When sale was made
            $table->timestamp('completed_at')->nullable(); // When sale was completed

            // Financial Information
            $table->decimal('subtotal', 15, 2)->default(0); // Subtotal before tax and discount
            $table->decimal('discount_amount', 15, 2)->default(0); // Total discount amount
            $table->decimal('discount_percentage', 5, 2)->default(0); // Discount percentage
            $table->decimal('tax_amount', 15, 2)->default(0); // Total tax amount
            $table->decimal('tax_percentage', 5, 2)->default(0); // Tax percentage
            $table->decimal('total_amount', 15, 2)->default(0); // Final total amount
            $table->decimal('paid_amount', 15, 2)->default(0); // Amount paid
            $table->decimal('change_amount', 15, 2)->default(0); // Change given
            $table->decimal('due_amount', 15, 2)->default(0); // Amount still due (for credit sales)

            // Item Counts
            $table->integer('total_items')->default(0); // Total number of different items
            $table->decimal('total_quantity', 10, 2)->default(0); // Total quantity sold

            // Discount and Loyalty
            $table->string('discount_type')->nullable(); // Type of discount applied
            $table->string('discount_code')->nullable(); // Discount code used
            $table->decimal('loyalty_points_earned', 10, 2)->default(0); // Loyalty points earned
            $table->decimal('loyalty_points_used', 10, 2)->default(0); // Loyalty points used

            // Customer Information (for walk-in customers)
            $table->string('customer_name')->nullable(); // Walk-in customer name
            $table->string('customer_phone')->nullable(); // Walk-in customer phone

            // Additional Information
            $table->text('notes')->nullable(); // Sale notes
            $table->text('internal_notes')->nullable(); // Internal notes (not on receipt)
            $table->json('metadata')->nullable(); // Additional metadata (JSON)

            // Refund Information
            $table->decimal('refunded_amount', 15, 2)->default(0); // Total refunded amount
            $table->timestamp('last_refund_at')->nullable(); // Last refund date

            // Delivery Information (for future delivery feature)
            $table->boolean('requires_delivery')->default(false); // Requires delivery
            $table->text('delivery_address')->nullable(); // Delivery address
            $table->timestamp('delivery_date')->nullable(); // Scheduled delivery date
            $table->enum('delivery_status', ['pending', 'in_transit', 'delivered', 'cancelled'])->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes for performance
            $table->index(['warehouse_id', 'sale_date']);
            $table->index(['customer_id', 'sale_date']);
            $table->index(['cash_session_id', 'status']);
            $table->index(['status', 'type']);
            $table->index(['sale_number', 'receipt_number']);
            $table->index(['served_by', 'sale_date']);
            $table->index('total_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
