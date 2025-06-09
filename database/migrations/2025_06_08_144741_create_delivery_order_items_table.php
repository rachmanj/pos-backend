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
        Schema::create('delivery_order_items', function (Blueprint $table) {
            $table->id();

            // Delivery and Order References
            $table->foreignId('delivery_order_id')->constrained('delivery_orders')->onDelete('cascade');
            $table->foreignId('sales_order_item_id')->constrained('sales_order_items')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');

            // Quantity Information
            $table->decimal('quantity_to_deliver', 10, 3);
            $table->decimal('quantity_delivered', 10, 3)->default(0);
            $table->decimal('quantity_damaged', 10, 3)->default(0);
            $table->decimal('quantity_returned', 10, 3)->default(0);

            // Pricing Information (for invoice generation)
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_total', 15, 2);

            // Item Status and Quality Control
            $table->enum('item_status', [
                'pending',
                'packed',
                'delivered',
                'damaged',
                'returned'
            ])->default('pending');

            // Warehouse Location Information
            $table->foreignId('warehouse_zone_id')->nullable()->constrained('warehouse_zones')->onDelete('set null');
            $table->string('pick_location', 100)->nullable();

            // Quality and Delivery Notes
            $table->text('delivery_notes')->nullable();
            $table->text('quality_notes')->nullable();
            $table->text('damage_reason')->nullable();

            $table->timestamps();

            // Indexes for Performance
            $table->index(['delivery_order_id', 'product_id']);
            $table->index(['sales_order_item_id', 'item_status']);
            $table->index(['product_id', 'item_status']);
            $table->index('item_status');

            // Unique constraint to prevent duplicate items in same delivery
            $table->unique(['delivery_order_id', 'sales_order_item_id'], 'do_items_do_soi_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_order_items');
    }
};
