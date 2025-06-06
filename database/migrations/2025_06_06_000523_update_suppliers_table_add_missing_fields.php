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
        Schema::table('suppliers', function (Blueprint $table) {
            // Add code column if it doesn't exist
            if (!Schema::hasColumn('suppliers', 'code')) {
                $table->string('code')->nullable()->after('name');
            }

            // Add city column if it doesn't exist
            if (!Schema::hasColumn('suppliers', 'city')) {
                $table->string('city')->nullable()->after('address');
            }

            // Add country column if it doesn't exist
            if (!Schema::hasColumn('suppliers', 'country')) {
                $table->string('country')->nullable()->after('city');
            }
        });

        // Update existing suppliers with auto-generated codes if they don't have one
        $suppliers = \App\Models\Supplier::where(function ($query) {
            $query->whereNull('code')->orWhere('code', '');
        })->get();

        foreach ($suppliers as $supplier) {
            $supplier->update(['code' => 'SUP' . str_pad($supplier->id, 4, '0', STR_PAD_LEFT)]);
        }

        // Add unique constraint to code column
        try {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->unique('code');
            });
        } catch (\Exception $e) {
            // Unique constraint might already exist, ignore the error
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'code')) {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            }
            if (Schema::hasColumn('suppliers', 'city')) {
                $table->dropColumn('city');
            }
            if (Schema::hasColumn('suppliers', 'country')) {
                $table->dropColumn('country');
            }
        });
    }
};
