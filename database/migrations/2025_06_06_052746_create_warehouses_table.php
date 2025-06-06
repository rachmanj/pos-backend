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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Warehouse code (e.g., WH001, WH002)
            $table->string('name'); // Warehouse name
            $table->text('description')->nullable(); // Warehouse description
            $table->enum('type', ['main', 'branch', 'storage', 'distribution'])->default('branch'); // Warehouse type
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active'); // Warehouse status

            // Location Information
            $table->text('address')->nullable(); // Full address
            $table->string('city')->nullable(); // City
            $table->string('state')->nullable(); // State/Province
            $table->string('postal_code')->nullable(); // Postal code
            $table->string('country')->default('Indonesia'); // Country
            $table->decimal('latitude', 10, 8)->nullable(); // GPS latitude
            $table->decimal('longitude', 11, 8)->nullable(); // GPS longitude

            // Contact Information
            $table->string('phone')->nullable(); // Phone number
            $table->string('email')->nullable(); // Email address
            $table->string('manager_name')->nullable(); // Manager name
            $table->string('manager_phone')->nullable(); // Manager phone

            // Capacity Information
            $table->decimal('total_area', 10, 2)->nullable(); // Total area in square meters
            $table->decimal('storage_area', 10, 2)->nullable(); // Storage area in square meters
            $table->integer('max_capacity')->nullable(); // Maximum capacity (units)
            $table->integer('current_utilization')->default(0); // Current utilization percentage

            // Operational Information
            $table->time('opening_time')->nullable(); // Opening time
            $table->time('closing_time')->nullable(); // Closing time
            $table->json('operating_days')->nullable(); // Operating days (JSON array)
            $table->boolean('is_default')->default(false); // Is default warehouse
            $table->integer('sort_order')->default(0); // Sort order for display

            $table->timestamps();

            // Indexes
            $table->index(['status', 'type']);
            $table->index('is_default');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
