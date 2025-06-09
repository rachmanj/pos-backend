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
            // Business Information (only add new fields)
            $table->string('company_registration_number')->nullable()->after('tax_number');
            $table->enum('business_type', ['individual', 'company', 'government', 'ngo', 'other'])->default('individual')->after('type');
            $table->string('industry')->nullable()->after('business_type');
            $table->integer('employee_count')->nullable()->after('industry');
            $table->decimal('annual_revenue', 15, 2)->nullable()->after('employee_count');
            $table->string('website')->nullable()->after('annual_revenue');
            $table->json('social_media')->nullable()->after('website'); // Store social media links

            // CRM Fields
            $table->enum('lead_source', ['website', 'referral', 'advertisement', 'cold_call', 'trade_show', 'social_media', 'other'])->nullable()->after('social_media');
            $table->enum('customer_stage', ['lead', 'prospect', 'customer', 'vip', 'inactive'])->default('lead')->after('lead_source');
            $table->enum('priority', ['low', 'normal', 'high', 'vip'])->default('normal')->after('customer_stage');
            $table->integer('payment_terms_days')->default(30)->after('priority'); // Net payment terms
            $table->enum('payment_method_preference', ['cash', 'bank_transfer', 'credit_card', 'check', 'other'])->nullable()->after('payment_terms_days');

            // Relationship Management
            $table->foreignId('assigned_sales_rep')->nullable()->constrained('users')->onDelete('set null')->after('payment_method_preference');
            $table->foreignId('account_manager')->nullable()->constrained('users')->onDelete('set null')->after('assigned_sales_rep');
            $table->date('first_purchase_date')->nullable()->after('account_manager');
            $table->date('last_contact_date')->nullable()->after('first_purchase_date');
            $table->date('next_follow_up_date')->nullable()->after('last_contact_date');

            // Loyalty & Analytics (some already exist)
            $table->decimal('average_order_value', 15, 2)->default(0)->after('next_follow_up_date');
            $table->integer('loyalty_points_balance')->default(0)->after('average_order_value');
            $table->enum('loyalty_tier', ['bronze', 'silver', 'gold', 'platinum', 'diamond'])->default('bronze')->after('loyalty_points_balance');
            $table->decimal('discount_percentage', 5, 2)->default(0)->after('loyalty_tier'); // Customer-specific discount

            // Communication Preferences
            $table->boolean('email_marketing_consent')->default(true)->after('discount_percentage');
            $table->boolean('sms_marketing_consent')->default(true)->after('email_marketing_consent');
            $table->boolean('phone_marketing_consent')->default(true)->after('sms_marketing_consent');
            $table->json('communication_preferences')->nullable()->after('phone_marketing_consent'); // Preferred times, channels, etc.

            // Additional Fields
            $table->text('internal_notes')->nullable()->after('communication_preferences');
            $table->json('custom_fields')->nullable()->after('internal_notes'); // Flexible custom data
            $table->boolean('is_blacklisted')->default(false)->after('custom_fields');
            $table->text('blacklist_reason')->nullable()->after('is_blacklisted');
            $table->timestamp('last_activity_at')->nullable()->after('blacklist_reason');

            // Add indexes for performance
            $table->index(['customer_stage', 'priority']);
            $table->index(['assigned_sales_rep', 'customer_stage']);
            $table->index(['account_manager', 'customer_stage']);
            $table->index(['loyalty_tier', 'loyalty_points_balance']);
            $table->index(['next_follow_up_date']);
            $table->index(['is_blacklisted', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['customer_stage', 'priority']);
            $table->dropIndex(['assigned_sales_rep', 'customer_stage']);
            $table->dropIndex(['account_manager', 'customer_stage']);
            $table->dropIndex(['loyalty_tier', 'loyalty_points_balance']);
            $table->dropIndex(['next_follow_up_date']);
            $table->dropIndex(['is_blacklisted', 'status']);

            $table->dropColumn([
                'company_registration_number',
                'business_type',
                'industry',
                'employee_count',
                'annual_revenue',
                'website',
                'social_media',
                'lead_source',
                'customer_stage',
                'priority',
                'payment_terms_days',
                'payment_method_preference',
                'assigned_sales_rep',
                'account_manager',
                'first_purchase_date',
                'last_contact_date',
                'next_follow_up_date',
                'average_order_value',
                'loyalty_points_balance',
                'loyalty_tier',
                'discount_percentage',
                'email_marketing_consent',
                'sms_marketing_consent',
                'phone_marketing_consent',
                'communication_preferences',
                'internal_notes',
                'custom_fields',
                'is_blacklisted',
                'blacklist_reason',
                'last_activity_at'
            ]);
        });
    }
};
