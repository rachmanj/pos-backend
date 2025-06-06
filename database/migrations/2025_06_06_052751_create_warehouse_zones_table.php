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
        Schema::create('warehouse_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->string('code'); // Zone code (e.g., A1, B2, C3)
            $table->string('name'); // Zone name
            $table->text('description')->nullable(); // Zone description
            $table->enum('type', ['receiving', 'storage', 'picking', 'shipping', 'quarantine', 'returns'])->default('storage');
            $table->enum('status', ['active', 'inactive', 'maintenance', 'full'])->default('active');

            // Location within warehouse
            $table->string('aisle')->nullable(); // Aisle identifier
            $table->string('row')->nullable(); // Row identifier
            $table->string('level')->nullable(); // Level/floor identifier
            $table->string('position')->nullable(); // Position identifier

            // Capacity Information
            $table->decimal('area', 8, 2)->nullable(); // Zone area in square meters
            $table->integer('max_capacity')->nullable(); // Maximum capacity (units)
            $table->integer('current_stock')->default(0); // Current stock count
            $table->decimal('utilization_percentage', 5, 2)->default(0); // Current utilization percentage

            // Environmental conditions
            $table->boolean('temperature_controlled')->default(false); // Temperature controlled zone
            $table->decimal('min_temperature', 5, 2)->nullable(); // Minimum temperature
            $table->decimal('max_temperature', 5, 2)->nullable(); // Maximum temperature
            $table->boolean('humidity_controlled')->default(false); // Humidity controlled zone
            $table->decimal('min_humidity', 5, 2)->nullable(); // Minimum humidity percentage
            $table->decimal('max_humidity', 5, 2)->nullable(); // Maximum humidity percentage

            // Access control
            $table->boolean('restricted_access')->default(false); // Requires special access
            $table->json('allowed_product_categories')->nullable(); // Allowed product categories (JSON array)
            $table->json('restrictions')->nullable(); // Zone restrictions (JSON object)

            $table->integer('sort_order')->default(0); // Sort order for display
            $table->timestamps();

            // Indexes
            $table->unique(['warehouse_id', 'code']); // Unique zone code per warehouse
            $table->index(['warehouse_id', 'type']);
            $table->index(['warehouse_id', 'status']);
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_zones');
    }
};
