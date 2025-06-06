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
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_number')->unique(); // Session number (e.g., CS001-20250606-001)
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade'); // Which warehouse/location
            $table->foreignId('opened_by')->constrained('users')->onDelete('cascade'); // Who opened the session
            $table->foreignId('closed_by')->nullable()->constrained('users')->onDelete('set null'); // Who closed the session

            // Session Status
            $table->enum('status', ['open', 'closed', 'reconciled'])->default('open');

            // Session Timing
            $table->timestamp('opened_at'); // When session was opened
            $table->timestamp('closed_at')->nullable(); // When session was closed
            $table->timestamp('reconciled_at')->nullable(); // When session was reconciled

            // Opening Balance
            $table->decimal('opening_cash', 15, 2)->default(0); // Cash amount at session start
            $table->json('opening_denominations')->nullable(); // Denomination breakdown at start
            $table->text('opening_notes')->nullable(); // Notes about opening

            // Closing Balance
            $table->decimal('closing_cash', 15, 2)->default(0); // Cash amount at session end
            $table->json('closing_denominations')->nullable(); // Denomination breakdown at end
            $table->text('closing_notes')->nullable(); // Notes about closing

            // Calculated Totals (from transactions)
            $table->decimal('expected_cash', 15, 2)->default(0); // Expected cash from sales
            $table->decimal('total_sales', 15, 2)->default(0); // Total sales amount
            $table->decimal('total_cash_sales', 15, 2)->default(0); // Total cash sales
            $table->decimal('total_card_sales', 15, 2)->default(0); // Total card sales
            $table->decimal('total_other_sales', 15, 2)->default(0); // Total other payment methods
            $table->integer('transaction_count')->default(0); // Number of transactions

            // Cash Movements
            $table->decimal('cash_in', 15, 2)->default(0); // Cash added during session
            $table->decimal('cash_out', 15, 2)->default(0); // Cash removed during session
            $table->text('cash_movements_notes')->nullable(); // Notes about cash movements

            // Reconciliation
            $table->decimal('variance', 15, 2)->default(0); // Difference between expected and actual
            $table->boolean('is_balanced')->default(false); // Is session balanced
            $table->text('variance_notes')->nullable(); // Notes about variances

            // Additional Information
            $table->json('session_summary')->nullable(); // Detailed session summary (JSON)
            $table->text('manager_notes')->nullable(); // Manager notes for review

            // Timestamps
            $table->timestamps();

            // Indexes for performance
            $table->index(['warehouse_id', 'status']);
            $table->index(['opened_at', 'closed_at']);
            $table->index('session_number');
            $table->index(['opened_by', 'closed_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
