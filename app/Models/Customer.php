<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_code',
        'name',
        'email',
        'phone',
        'birth_date',
        'gender',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'type',
        'status',
        'credit_limit',
        'total_spent',
        'total_orders',
        'loyalty_points',
        'last_purchase_date',
        'tax_number',
        'company_name',
        'notes',
        'preferences',
        // Tax Configuration
        'tax_exempt',
        'tax_rate_override',
        'exemption_reason',
        'exemption_details',
        'referred_by',
        'referral_count',
        // New CRM fields
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
        'last_activity_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'last_purchase_date' => 'date',
        'first_purchase_date' => 'date',
        'last_contact_date' => 'date',
        'next_follow_up_date' => 'date',
        'last_activity_at' => 'datetime',
        'credit_limit' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'loyalty_points' => 'decimal:2',
        'tax_rate_override' => 'decimal:2',
        'average_order_value' => 'decimal:2',
        'annual_revenue' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'total_orders' => 'integer',
        'referral_count' => 'integer',
        'employee_count' => 'integer',
        'payment_terms_days' => 'integer',
        'loyalty_points_balance' => 'integer',
        'preferences' => 'array',
        'social_media' => 'array',
        'communication_preferences' => 'array',
        'custom_fields' => 'array',
        'email_marketing_consent' => 'boolean',
        'sms_marketing_consent' => 'boolean',
        'phone_marketing_consent' => 'boolean',
        'is_blacklisted' => 'boolean',
        'tax_exempt' => 'boolean',
    ];

    // Existing Relationships
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class)->orderBy('sale_date', 'desc');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Customer::class, 'referred_by');
    }

    // New CRM Relationships
    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function activeContacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class)->where('is_active', true);
    }

    public function primaryContact(): HasMany
    {
        return $this->hasMany(CustomerContact::class)->where('is_primary', true);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function activeAddresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->where('is_active', true);
    }

    public function billingAddresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->where('type', 'billing');
    }

    public function shippingAddresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->where('type', 'shipping');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->orderBy('created_at', 'desc');
    }

    public function publicNotes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->where('is_private', false)->orderBy('created_at', 'desc');
    }

    public function loyaltyPointTransactions(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyPoint::class)->orderBy('created_at', 'desc');
    }

    public function assignedSalesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_sales_rep');
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager');
    }

    // Enhanced Accessors
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country
        ]);

        return implode(', ', $parts);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }

    public function getIsVipAttribute(): bool
    {
        return $this->type === 'vip' || $this->loyalty_tier === 'platinum' || $this->loyalty_tier === 'diamond' || $this->total_spent >= 10000000;
    }

    public function getAvailableCreditAttribute(): float
    {
        return max(0, $this->credit_limit - $this->getCurrentCreditUsed());
    }

    public function getLastPurchaseDaysAgoAttribute(): ?int
    {
        return $this->last_purchase_date ?
            $this->last_purchase_date->diffInDays(now()) : null;
    }

    public function getCustomerStageColorAttribute(): string
    {
        return match ($this->customer_stage) {
            'lead' => 'blue',
            'prospect' => 'yellow',
            'customer' => 'green',
            'vip' => 'purple',
            'inactive' => 'gray',
            default => 'gray'
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'vip' => 'purple',
            'high' => 'red',
            'normal' => 'blue',
            'low' => 'gray',
            default => 'blue'
        };
    }

    public function getLoyaltyTierColorAttribute(): string
    {
        return match ($this->loyalty_tier) {
            'diamond' => 'purple',
            'platinum' => 'gray',
            'gold' => 'yellow',
            'silver' => 'blue',
            'bronze' => 'orange',
            default => 'gray'
        };
    }

    public function getDisplayNameAttribute(): string
    {
        $name = $this->name;

        if ($this->company_name && $this->business_type !== 'individual') {
            $name = $this->company_name . " ({$this->name})";
        }

        return $name;
    }

    public function getNextFollowUpStatusAttribute(): ?string
    {
        if (!$this->next_follow_up_date) {
            return null;
        }

        $daysUntil = now()->diffInDays($this->next_follow_up_date, false);

        if ($daysUntil < 0) {
            return 'overdue';
        } elseif ($daysUntil === 0) {
            return 'today';
        } elseif ($daysUntil <= 7) {
            return 'this_week';
        } else {
            return 'future';
        }
    }

    public function getLifetimeValueAttribute(): float
    {
        return $this->total_spent + $this->getCurrentCreditUsed();
    }

    // Enhanced Business Logic Methods
    public function getCurrentCreditUsed(): float
    {
        return $this->sales()
            ->where('status', '!=', 'cancelled')
            ->sum('due_amount');
    }

    public function getAverageOrderValue(): float
    {
        return $this->total_orders > 0 ?
            $this->total_spent / $this->total_orders : 0;
    }

    public function addLoyaltyPoints(float $points): void
    {
        $this->increment('loyalty_points', $points);
        $this->increment('loyalty_points_balance', $points);
        $this->updateLoyaltyTier();
    }

    public function redeemLoyaltyPoints(float $points): bool
    {
        if ($this->loyalty_points_balance >= $points) {
            $this->decrement('loyalty_points_balance', $points);
            return true;
        }
        return false;
    }

    public function updatePurchaseStats(float $amount): void
    {
        $this->increment('total_spent', $amount);
        $this->increment('total_orders');
        $this->update([
            'last_purchase_date' => now(),
            'last_activity_at' => now(),
            'average_order_value' => $this->getAverageOrderValue()
        ]);

        // Update customer stage if this is first purchase
        if ($this->customer_stage === 'lead' || $this->customer_stage === 'prospect') {
            $this->update(['customer_stage' => 'customer']);
        }

        $this->updateLoyaltyTier();
    }

    public function updateLoyaltyTier(): void
    {
        $tier = match (true) {
            $this->total_spent >= 100000000 => 'diamond', // 100M IDR
            $this->total_spent >= 50000000 => 'platinum',  // 50M IDR
            $this->total_spent >= 25000000 => 'gold',      // 25M IDR
            $this->total_spent >= 10000000 => 'silver',    // 10M IDR
            default => 'bronze'
        };

        if ($this->loyalty_tier !== $tier) {
            $this->update(['loyalty_tier' => $tier]);
        }
    }

    public function canMakePurchase(float $amount): bool
    {
        if ($this->status !== 'active' || $this->is_blacklisted) {
            return false;
        }

        // For credit customers, check credit limit
        if ($this->credit_limit > 0) {
            return ($this->getCurrentCreditUsed() + $amount) <= $this->credit_limit;
        }

        return true;
    }

    public function generateCustomerCode(): string
    {
        $prefix = match ($this->customer_stage) {
            'vip' => 'VIP',
            'customer' => match ($this->type) {
                'wholesale' => 'WHL',
                'member' => 'MEM',
                default => 'CUS'
            },
            'prospect' => 'PRO',
            'lead' => 'LED',
            default => 'CUS'
        };

        $nextNumber = static::where('customer_code', 'like', $prefix . '%')
            ->count() + 1;

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function updateLastActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    public function markAsBlacklisted(string $reason): void
    {
        $this->update([
            'is_blacklisted' => true,
            'blacklist_reason' => $reason,
            'status' => 'suspended'
        ]);
    }

    public function removeFromBlacklist(): void
    {
        $this->update([
            'is_blacklisted' => false,
            'blacklist_reason' => null,
            'status' => 'active'
        ]);
    }

    public function scheduleFollowUp(\DateTime $date, ?int $assignedTo = null): void
    {
        $this->update([
            'next_follow_up_date' => $date,
            'assigned_sales_rep' => $assignedTo ?: $this->assigned_sales_rep
        ]);
    }

    public function getPrimaryContactInfo(): ?array
    {
        $contact = $this->primaryContact()->first();

        if (!$contact) {
            return null;
        }

        return [
            'name' => $contact->name,
            'position' => $contact->position,
            'phone' => $contact->phone ?: $contact->mobile,
            'email' => $contact->email,
            'whatsapp' => $contact->whatsapp
        ];
    }

    public function getPrimaryAddress(string $type = 'billing'): ?CustomerAddress
    {
        return $this->addresses()
            ->where('type', $type)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->first();
    }

    public function getRecentNotes(int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return $this->publicNotes()->limit($limit)->get();
    }

    public function getLoyaltyPointsBalance(): int
    {
        return CustomerLoyaltyPoint::getCustomerBalance($this->id);
    }

    public function getExpiringPoints(int $days = 30): int
    {
        return CustomerLoyaltyPoint::getCustomerExpiringPoints($this->id, $days);
    }

    // Enhanced Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('is_blacklisted', false);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeByStage(Builder $query, string $stage): Builder
    {
        return $query->where('customer_stage', $stage);
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeByLoyaltyTier(Builder $query, string $tier): Builder
    {
        return $query->where('loyalty_tier', $tier);
    }

    public function scopeVip(Builder $query): Builder
    {
        return $query->where('customer_stage', 'vip')
            ->orWhere('loyalty_tier', 'platinum')
            ->orWhere('loyalty_tier', 'diamond')
            ->orWhere('total_spent', '>=', 10000000);
    }

    public function scopeWithCredit(Builder $query): Builder
    {
        return $query->where('credit_limit', '>', 0);
    }

    public function scopeRecentCustomers(Builder $query, int $days = 30): Builder
    {
        return $query->where('last_purchase_date', '>=', now()->subDays($days));
    }

    public function scopeInactiveCustomers(Builder $query, int $days = 90): Builder
    {
        return $query->where('last_purchase_date', '<', now()->subDays($days))
            ->orWhereNull('last_purchase_date');
    }

    public function scopeRequiringFollowUp(Builder $query): Builder
    {
        return $query->whereNotNull('next_follow_up_date')
            ->where('next_follow_up_date', '<=', now());
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_sales_rep', $userId)
            ->orWhere('account_manager', $userId);
    }

    public function scopeByLeadSource(Builder $query, string $source): Builder
    {
        return $query->where('lead_source', $source);
    }

    public function scopeByIndustry(Builder $query, string $industry): Builder
    {
        return $query->where('industry', 'like', "%{$industry}%");
    }

    public function scopeBlacklisted(Builder $query): Builder
    {
        return $query->where('is_blacklisted', true);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('customer_code', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('tax_number', 'like', "%{$search}%");
        });
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customer) {
            if (empty($customer->customer_code)) {
                $customer->customer_code = $customer->generateCustomerCode();
            }

            // Set default values for new CRM fields
            $customer->customer_stage = $customer->customer_stage ?: 'lead';
            $customer->priority = $customer->priority ?: 'normal';
            $customer->loyalty_tier = $customer->loyalty_tier ?: 'bronze';
            $customer->payment_terms_days = $customer->payment_terms_days ?: 30;
            $customer->email_marketing_consent = $customer->email_marketing_consent ?? true;
            $customer->sms_marketing_consent = $customer->sms_marketing_consent ?? true;
            $customer->phone_marketing_consent = $customer->phone_marketing_consent ?? true;
        });

        static::updating(function ($customer) {
            $customer->last_activity_at = now();
        });
    }
}
