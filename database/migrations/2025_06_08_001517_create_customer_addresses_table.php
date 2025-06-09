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
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['billing', 'shipping', 'office', 'warehouse', 'other'])->default('billing');
            $table->string('label')->nullable(); // e.g., "Main Office", "Jakarta Branch"
            $table->text('address_line_1');
            $table->text('address_line_2')->nullable();
            $table->string('city');
            $table->string('state_province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('Indonesia');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('delivery_notes')->nullable();
            $table->json('business_hours')->nullable(); // Store operating hours
            $table->timestamps();

            $table->index(['customer_id', 'type']);
            $table->index(['customer_id', 'is_primary']);
            $table->index(['customer_id', 'is_active']);
            $table->index(['city', 'state_province']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
