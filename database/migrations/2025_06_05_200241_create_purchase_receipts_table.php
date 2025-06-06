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
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('purchase_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('received_by')->constrained('users')->onDelete('cascade');

            $table->date('receipt_date');
            $table->enum('status', [
                'draft',
                'partial',
                'complete',
                'quality_check_pending',
                'quality_check_failed',
                'approved'
            ])->default('draft');

            $table->text('notes')->nullable();
            $table->text('quality_check_notes')->nullable();
            $table->boolean('stock_updated')->default(false);

            $table->timestamps();

            $table->index(['purchase_order_id', 'status']);
            $table->index(['receipt_date', 'status']);
            $table->index('receipt_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_receipts');
    }
};
