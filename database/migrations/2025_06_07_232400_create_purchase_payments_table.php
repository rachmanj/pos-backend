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
        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->foreignId('purchase_order_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('payment_method_id')->constrained()->onDelete('restrict');
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('reference_number')->nullable();
            $table->enum('status', ['pending', 'completed', 'cancelled', 'failed'])->default('pending');
            $table->enum('payment_type', ['advance', 'partial', 'full', 'overpayment'])->default('full');
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['supplier_id', 'payment_date']);
            $table->index(['status', 'payment_date']);
            $table->index('payment_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
    }
};
