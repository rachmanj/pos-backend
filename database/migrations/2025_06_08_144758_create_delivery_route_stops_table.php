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
        Schema::create('delivery_route_stops', function (Blueprint $table) {
            $table->id();

            // Route and Delivery References
            $table->foreignId('delivery_route_id')->constrained('delivery_routes')->onDelete('cascade');
            $table->foreignId('delivery_order_id')->constrained('delivery_orders')->onDelete('cascade');

            // Stop Sequencing
            $table->integer('stop_sequence');
            $table->integer('priority')->default(1); // 1=high, 2=medium, 3=low

            // Timing Information
            $table->time('estimated_arrival');
            $table->time('actual_arrival')->nullable();
            $table->integer('estimated_duration')->default(30); // in minutes
            $table->integer('actual_duration')->nullable(); // in minutes
            $table->time('departure_time')->nullable();

            // Stop Status
            $table->enum('stop_status', [
                'pending',
                'en_route',
                'arrived',
                'completed',
                'failed',
                'skipped'
            ])->default('pending');

            // Location Information
            $table->text('stop_address');
            $table->string('contact_person', 100)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Delivery Information
            $table->integer('total_packages')->default(1);
            $table->decimal('total_weight', 10, 2)->nullable(); // in KG
            $table->decimal('total_volume', 10, 2)->nullable(); // in cubic meters

            // Customer and Delivery Details
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->text('delivery_instructions')->nullable();
            $table->text('access_notes')->nullable();

            // Completion Information
            $table->text('delivery_notes')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('recipient_name', 100)->nullable();
            $table->text('delivery_signature')->nullable(); // For future signature capture
            $table->string('delivery_photo_path')->nullable(); // For proof of delivery

            // Driver Tracking
            $table->decimal('distance_from_previous', 10, 2)->nullable(); // in KM
            $table->integer('travel_time_from_previous')->nullable(); // in minutes

            $table->timestamps();

            // Indexes for Performance
            $table->index(['delivery_route_id', 'stop_sequence']);
            $table->index(['delivery_order_id', 'stop_status']);
            $table->index(['customer_id', 'stop_status']);
            $table->index(['stop_status', 'estimated_arrival']);
            $table->index('stop_sequence');

            // Unique constraint to prevent duplicate stops for same delivery
            $table->unique(['delivery_route_id', 'delivery_order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_route_stops');
    }
};
