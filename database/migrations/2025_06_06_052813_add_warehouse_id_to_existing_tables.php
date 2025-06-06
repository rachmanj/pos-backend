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
        // Add warehouse_id to stock_movements table
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('id')->constrained('warehouses')->onDelete('cascade');
            $table->foreignId('warehouse_zone_id')->nullable()->after('warehouse_id')->constrained('warehouse_zones')->onDelete('set null');
            $table->string('bin_location')->nullable()->after('warehouse_zone_id'); // Specific bin location

            // Add indexes
            $table->index(['warehouse_id', 'product_id']);
            $table->index(['warehouse_zone_id', 'product_id']);
        });

        // Add warehouse_id to purchase_receipts table
        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('purchase_order_id')->constrained('warehouses')->onDelete('cascade');

            // Add index
            $table->index(['warehouse_id', 'status']);
        });

        // Add warehouse_id to purchase_receipt_items table
        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            $table->foreignId('warehouse_zone_id')->nullable()->after('unit_id')->constrained('warehouse_zones')->onDelete('set null');
            $table->string('bin_location')->nullable()->after('warehouse_zone_id'); // Destination bin location

            // Add index
            $table->index(['warehouse_zone_id', 'product_id']);
        });

        // Modify product_stocks table to add warehouse context (this will be deprecated in favor of warehouse_stocks)
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('id')->constrained('warehouses')->onDelete('cascade');
            $table->boolean('is_legacy')->default(true)->after('warehouse_id'); // Mark as legacy record

            // Add index
            $table->index(['warehouse_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove warehouse_id from stock_movements table
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropForeign(['warehouse_zone_id']);
            $table->dropColumn(['warehouse_id', 'warehouse_zone_id', 'bin_location']);
        });

        // Remove warehouse_id from purchase_receipts table
        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });

        // Remove warehouse_zone_id from purchase_receipt_items table
        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_zone_id']);
            $table->dropColumn(['warehouse_zone_id', 'bin_location']);
        });

        // Remove warehouse_id from product_stocks table
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn(['warehouse_id', 'is_legacy']);
        });
    }
};
