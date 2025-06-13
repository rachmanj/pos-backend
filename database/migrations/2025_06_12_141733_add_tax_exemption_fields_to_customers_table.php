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
        Schema::table('customers', function (Blueprint $table) {
            // Tax Configuration Fields
            $table->boolean('tax_exempt')->default(false)->after('tax_number');
            $table->decimal('tax_rate_override', 5, 2)->nullable()->after('tax_exempt');
            $table->enum('exemption_reason', [
                'government',
                'nonprofit',
                'export',
                'resale',
                'medical',
                'education',
                'other'
            ])->nullable()->after('tax_rate_override');
            $table->text('exemption_details')->nullable()->after('exemption_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'tax_exempt',
                'tax_rate_override',
                'exemption_reason',
                'exemption_details'
            ]);
        });
    }
};
