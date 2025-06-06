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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('sale_id')->constrained('sales')->onDelete('cascade'); // Parent sale
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade'); // Product sold
            $table->foreignId('warehouse_zone_id')->nullable()->constrained('warehouse_zones')->onDelete('set null'); // Zone where item was picked
            $table->foreignId('unit_id')->constrained('units')->onDelete('cascade'); // Unit of measure

            // Item Information
            $table->string('product_name'); // Product name at time of sale
            $table->string('product_sku'); // Product SKU at time of sale
            $table->string('product_barcode')->nullable(); // Product barcode at time of sale

            // Quantity and Pricing
            $table->decimal('quantity', 10, 4); // Quantity sold
            $table->decimal('unit_price', 15, 4); // Price per unit (including any item-specific discount)
            $table->decimal('original_price', 15, 4); // Original price before discount
            $table->decimal('cost_price', 15, 4)->default(0); // Cost price for profit calculation

            // Line Totals
            $table->decimal('line_discount_amount', 15, 2)->default(0); // Discount amount for this line
            $table->decimal('line_discount_percentage', 5, 2)->default(0); // Discount percentage for this line
            $table->decimal('line_tax_amount', 15, 2)->default(0); // Tax amount for this line
            $table->decimal('line_tax_percentage', 5, 2)->default(0); // Tax percentage for this line
            $table->decimal('line_total', 15, 2); // Total for this line (quantity × unit_price)
            $table->decimal('line_subtotal', 15, 2); // Subtotal before tax for this line

            // Profit Calculation
            $table->decimal('total_cost', 15, 2)->default(0); // Total cost (quantity × cost_price)
            $table->decimal('gross_profit', 15, 2)->default(0); // Gross profit for this line

            // Discount Information
            $table->string('discount_type')->nullable(); // Type of discount applied to this item
            $table->string('discount_reason')->nullable(); // Reason for discount

            // Stock Information at Time of Sale
            $table->decimal('available_stock', 10, 4)->default(0); // Available stock when sold
            $table->string('lot_number')->nullable(); // Lot/batch number if applicable
            $table->date('expiry_date')->nullable(); // Expiry date if applicable

            // Return/Refund Information
            $table->decimal('returned_quantity', 10, 4)->default(0); // Quantity returned
            $table->decimal('refunded_amount', 15, 2)->default(0); // Amount refunded for this item
            $table->boolean('is_returnable')->default(true); // Can this item be returned

            // Additional Information
            $table->text('notes')->nullable(); // Notes about this item
            $table->json('metadata')->nullable(); // Additional metadata (JSON)

            // Promotional Information
            $table->string('promotion_code')->nullable(); // Promotion code applied
            $table->string('promotion_name')->nullable(); // Promotion name

            // Serial Number Tracking (for serialized items)
            $table->string('serial_number')->nullable(); // Serial number if applicable
            $table->boolean('requires_serial')->default(false); // Does this item require serial tracking

            // Timestamps
            $table->timestamps();

            // Indexes for performance
            $table->index(['sale_id', 'product_id']);
            $table->index(['product_id', 'created_at']);
            $table->index(['warehouse_zone_id', 'product_id']);
            $table->index('serial_number');
            $table->index('lot_number');
            $table->index(['returned_quantity', 'refunded_amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
