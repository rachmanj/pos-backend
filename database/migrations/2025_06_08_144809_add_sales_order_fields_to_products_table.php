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
        Schema::table('products', function (Blueprint $table) {
            // Sales Order Management Fields
            $table->boolean('is_orderable')->default(true);
            $table->decimal('minimum_order_quantity', 10, 3)->default(1);
            $table->decimal('maximum_order_quantity', 10, 3)->nullable();
            $table->foreignId('order_unit_id')->nullable()->constrained('units')->onDelete('set null');

            // Lead Time and Availability
            $table->integer('lead_time_days')->default(0);
            $table->boolean('requires_special_handling')->default(false);
            $table->text('handling_instructions')->nullable();
            $table->boolean('fragile')->default(false);
            $table->boolean('hazardous')->default(false);

            // Pricing for Sales Orders
            $table->decimal('wholesale_price', 15, 2)->nullable();
            $table->decimal('bulk_discount_threshold', 10, 3)->nullable();
            $table->decimal('bulk_discount_percentage', 5, 2)->nullable();
            $table->decimal('volume_discount_tier1_qty', 10, 3)->nullable();
            $table->decimal('volume_discount_tier1_price', 15, 2)->nullable();
            $table->decimal('volume_discount_tier2_qty', 10, 3)->nullable();
            $table->decimal('volume_discount_tier2_price', 15, 2)->nullable();
            $table->decimal('volume_discount_tier3_qty', 10, 3)->nullable();
            $table->decimal('volume_discount_tier3_price', 15, 2)->nullable();

            // Product Dimensions and Weight (for delivery planning)
            $table->decimal('length_cm', 8, 2)->nullable();
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->decimal('volume_cubic_cm', 12, 2)->nullable();

            // Sales Order Analytics
            $table->integer('total_orders_count')->default(0);
            $table->decimal('total_ordered_quantity', 15, 3)->default(0);
            $table->decimal('total_sales_value', 18, 2)->default(0);
            $table->date('last_ordered_date')->nullable();
            $table->date('first_ordered_date')->nullable();

            // Delivery and Packaging Information
            $table->boolean('requires_refrigeration')->default(false);
            $table->decimal('min_temperature', 5, 2)->nullable(); // Celsius
            $table->decimal('max_temperature', 5, 2)->nullable(); // Celsius
            $table->string('packaging_type', 50)->nullable(); // box, pallet, bag, etc.
            $table->integer('units_per_package')->default(1);
            $table->boolean('stackable')->default(true);

            // Customer Restrictions and Preferences
            $table->boolean('age_restricted')->default(false);
            $table->integer('minimum_age_requirement')->nullable();
            $table->boolean('prescription_required')->default(false);
            $table->boolean('corporate_customers_only')->default(false);

            // Seasonal and Availability Information
            $table->boolean('seasonal_product')->default(false);
            $table->date('season_start_date')->nullable();
            $table->date('season_end_date')->nullable();
            $table->boolean('pre_order_allowed')->default(false);
            $table->date('available_from_date')->nullable();
            $table->date('discontinued_date')->nullable();

            // Indexes for Performance
            $table->index(['is_orderable', 'discontinued_date']);
            $table->index(['category_id', 'is_orderable']);
            $table->index(['minimum_order_quantity', 'is_orderable']);
            $table->index('lead_time_days');
            $table->index('last_ordered_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['order_unit_id']);

            // Drop indexes
            $table->dropIndex(['is_orderable', 'discontinued_date']);
            $table->dropIndex(['category_id', 'is_orderable']);
            $table->dropIndex(['minimum_order_quantity', 'is_orderable']);
            $table->dropIndex(['lead_time_days']);
            $table->dropIndex(['last_ordered_date']);

            // Drop columns
            $table->dropColumn([
                'is_orderable',
                'minimum_order_quantity',
                'maximum_order_quantity',
                'order_unit_id',
                'lead_time_days',
                'requires_special_handling',
                'handling_instructions',
                'fragile',
                'hazardous',
                'wholesale_price',
                'bulk_discount_threshold',
                'bulk_discount_percentage',
                'volume_discount_tier1_qty',
                'volume_discount_tier1_price',
                'volume_discount_tier2_qty',
                'volume_discount_tier2_price',
                'volume_discount_tier3_qty',
                'volume_discount_tier3_price',
                'length_cm',
                'width_cm',
                'height_cm',
                'weight_kg',
                'volume_cubic_cm',
                'total_orders_count',
                'total_ordered_quantity',
                'total_sales_value',
                'last_ordered_date',
                'first_ordered_date',
                'requires_refrigeration',
                'min_temperature',
                'max_temperature',
                'packaging_type',
                'units_per_package',
                'stackable',
                'age_restricted',
                'minimum_age_requirement',
                'prescription_required',
                'corporate_customers_only',
                'seasonal_product',
                'season_start_date',
                'season_end_date',
                'pre_order_allowed',
                'available_from_date',
                'discontinued_date'
            ]);
        });
    }
};
