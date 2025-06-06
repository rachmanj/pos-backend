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
        'referred_by',
        'referral_count',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'last_purchase_date' => 'date',
        'credit_limit' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'loyalty_points' => 'decimal:2',
        'total_orders' => 'integer',
        'referral_count' => 'integer',
        'preferences' => 'array',
    ];

    // Relationships
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

    // Accessors
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
        return $this->type === 'vip' || $this->total_spent >= 10000000; // 10 million IDR
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

    // Business Logic Methods
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
    }

    public function redeemLoyaltyPoints(float $points): bool
    {
        if ($this->loyalty_points >= $points) {
            $this->decrement('loyalty_points', $points);
            return true;
        }
        return false;
    }

    public function updatePurchaseStats(float $amount): void
    {
        $this->increment('total_spent', $amount);
        $this->increment('total_orders');
        $this->update(['last_purchase_date' => now()]);
    }

    public function canMakePurchase(float $amount): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        // For credit customers, check credit limit
        if ($this->type === 'wholesale' && $this->credit_limit > 0) {
            return ($this->getCurrentCreditUsed() + $amount) <= $this->credit_limit;
        }

        return true;
    }

    public function generateCustomerCode(): string
    {
        $prefix = match ($this->type) {
            'vip' => 'VIP',
            'wholesale' => 'WHL',
            'member' => 'MEM',
            default => 'REG'
        };

        $nextNumber = static::where('customer_code', 'like', $prefix . '%')
            ->count() + 1;

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeVip(Builder $query): Builder
    {
        return $query->where('type', 'vip')
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

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('customer_code', 'like', "%{$search}%");
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
        });
    }
}
