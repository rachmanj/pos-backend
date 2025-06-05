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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('symbol', 10);
            $table->unsignedBigInteger('base_unit_id')->nullable();
            $table->decimal('conversion_factor', 10, 6)->default(1.000000);
            $table->timestamps();

            $table->foreign('base_unit_id')->references('id')->on('units')->onDelete('set null');
            $table->index('base_unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
