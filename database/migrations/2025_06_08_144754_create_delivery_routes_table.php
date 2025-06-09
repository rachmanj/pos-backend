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
        Schema::create('delivery_routes', function (Blueprint $table) {
            $table->id();
            $table->string('route_name', 100);
            $table->string('route_code', 20)->unique()->index();

            // Route Date and Timing
            $table->date('route_date');
            $table->time('planned_start_time');
            $table->time('planned_end_time');
            $table->time('actual_start_time')->nullable();
            $table->time('actual_end_time')->nullable();

            // Driver and Vehicle Information
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->string('vehicle_id', 50);
            $table->string('vehicle_type', 50)->nullable();
            $table->decimal('vehicle_capacity', 10, 2)->nullable(); // in KG or cubic meters

            // Route Metrics
            $table->decimal('total_distance', 10, 2)->nullable(); // in KM
            $table->integer('estimated_duration')->nullable(); // in minutes
            $table->integer('actual_duration')->nullable(); // in minutes
            $table->integer('total_stops')->default(0);
            $table->integer('completed_stops')->default(0);

            // Route Status
            $table->enum('route_status', [
                'planned',
                'in_progress',
                'completed',
                'cancelled',
                'delayed'
            ])->default('planned');

            // GPS and Tracking
            $table->decimal('start_latitude', 10, 8)->nullable();
            $table->decimal('start_longitude', 11, 8)->nullable();
            $table->decimal('end_latitude', 10, 8)->nullable();
            $table->decimal('end_longitude', 11, 8)->nullable();

            // Route Optimization
            $table->text('optimization_notes')->nullable();
            $table->json('route_waypoints')->nullable(); // JSON array of GPS coordinates

            // Financial Information
            $table->decimal('fuel_cost', 10, 2)->nullable();
            $table->decimal('driver_cost', 10, 2)->nullable();
            $table->decimal('total_route_cost', 10, 2)->nullable();

            // Additional Information
            $table->text('route_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            $table->timestamps();

            // Indexes for Performance
            $table->index(['driver_id', 'route_date']);
            $table->index(['route_date', 'route_status']);
            $table->index(['vehicle_id', 'route_date']);
            $table->index('route_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_routes');
    }
};
