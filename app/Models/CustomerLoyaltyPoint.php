<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLoyaltyPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'sale_id',
        'user_id',
        'type',
        'points',
        'transaction_amount',
        'points_rate',
        'description',
        'expiry_date',
        'is_expired',
        'metadata',
    ];

    protected $casts = [
        'points' => 'integer',
        'transaction_amount' => 'decimal:2',
        'points_rate' => 'decimal:4',
        'expiry_date' => 'date',
        'is_expired' => 'boolean',
        'metadata' => 'array',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeEarned($query)
    {
        return $query->where('type', 'earned');
    }

    public function scopeRedeemed($query)
    {
        return $query->where('type', 'redeemed');
    }

    public function scopeExpired($query)
    {
        return $query->where('is_expired', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_expired', false)
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', now());
            });
    }

    public function scopeExpiring($query, int $days = 30)
    {
        return $query->where('is_expired', false)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays($days)]);
    }

    public function scopePositive($query)
    {
        return $query->where('points', '>', 0);
    }

    public function scopeNegative($query)
    {
        return $query->where('points', '<', 0);
    }

    public function scopeFromSales($query)
    {
        return $query->whereNotNull('sale_id');
    }

    public function scopeManualAdjustments($query)
    {
        return $query->whereNull('sale_id')
            ->whereIn('type', ['adjusted', 'bonus', 'penalty']);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Accessors
    public function getIsPositiveAttribute(): bool
    {
        return $this->points > 0;
    }

    public function getIsNegativeAttribute(): bool
    {
        return $this->points < 0;
    }

    public function getAbsolutePointsAttribute(): int
    {
        return abs($this->points);
    }

    public function getTypeDisplayNameAttribute(): string
    {
        return match ($this->type) {
            'earned' => 'Points Earned',
            'redeemed' => 'Points Redeemed',
            'expired' => 'Points Expired',
            'adjusted' => 'Manual Adjustment',
            'bonus' => 'Bonus Points',
            'penalty' => 'Penalty Points',
            default => ucfirst($this->type)
        };
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'earned' => 'green',
            'bonus' => 'blue',
            'redeemed' => 'orange',
            'expired' => 'gray',
            'adjusted' => 'yellow',
            'penalty' => 'red',
            default => 'gray'
        };
    }

    public function getFormattedPointsAttribute(): string
    {
        $sign = $this->points >= 0 ? '+' : '';
        return $sign . number_format($this->points);
    }

    public function getFormattedTransactionAmountAttribute(): ?string
    {
        if (!$this->transaction_amount) {
            return null;
        }

        return 'Rp ' . number_format($this->transaction_amount, 0, ',', '.');
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expiry_date || $this->is_expired) {
            return null;
        }

        return now()->diffInDays($this->expiry_date, false);
    }

    public function getIsExpiringAttribute(): bool
    {
        if (!$this->expiry_date || $this->is_expired) {
            return false;
        }

        return $this->expiry_date->diffInDays(now()) <= 30;
    }

    public function getExpiryStatusAttribute(): string
    {
        if ($this->is_expired) {
            return 'expired';
        }

        if (!$this->expiry_date) {
            return 'never';
        }

        $daysUntilExpiry = $this->days_until_expiry;

        if ($daysUntilExpiry < 0) {
            return 'expired';
        } elseif ($daysUntilExpiry <= 7) {
            return 'expiring_soon';
        } elseif ($daysUntilExpiry <= 30) {
            return 'expiring';
        } else {
            return 'active';
        }
    }

    // Business Logic Methods
    public function markAsExpired(): bool
    {
        return $this->update(['is_expired' => true]);
    }

    public function canExpire(): bool
    {
        return !$this->is_expired
            && $this->expiry_date
            && $this->expiry_date->isPast()
            && $this->points > 0;
    }

    public function calculateEarnedPoints(float $transactionAmount, float $pointsRate): int
    {
        return (int) floor($transactionAmount * $pointsRate);
    }

    public static function createEarnedPoints(
        int $customerId,
        int $saleId,
        int $userId,
        float $transactionAmount,
        float $pointsRate,
        ?string $expiryMonths = null
    ): self {
        $points = (int) floor($transactionAmount * $pointsRate);
        $expiryDate = $expiryMonths ? now()->addMonths($expiryMonths) : null;

        return static::create([
            'customer_id' => $customerId,
            'sale_id' => $saleId,
            'user_id' => $userId,
            'type' => 'earned',
            'points' => $points,
            'transaction_amount' => $transactionAmount,
            'points_rate' => $pointsRate,
            'description' => "Points earned from sale #$saleId",
            'expiry_date' => $expiryDate,
            'metadata' => [
                'sale_id' => $saleId,
                'calculation' => [
                    'amount' => $transactionAmount,
                    'rate' => $pointsRate,
                    'points' => $points
                ]
            ]
        ]);
    }

    public static function createRedeemedPoints(
        int $customerId,
        int $userId,
        int $pointsRedeemed,
        string $description,
        ?array $metadata = null
    ): self {
        return static::create([
            'customer_id' => $customerId,
            'user_id' => $userId,
            'type' => 'redeemed',
            'points' => -$pointsRedeemed, // Negative for redemption
            'description' => $description,
            'metadata' => $metadata
        ]);
    }

    public static function createAdjustment(
        int $customerId,
        int $userId,
        int $points,
        string $type, // 'adjusted', 'bonus', 'penalty'
        string $description,
        ?array $metadata = null
    ): self {
        return static::create([
            'customer_id' => $customerId,
            'user_id' => $userId,
            'type' => $type,
            'points' => $points,
            'description' => $description,
            'metadata' => $metadata
        ]);
    }

    public static function expireOldPoints(): int
    {
        $expiredCount = 0;

        // Find points that should be expired
        $pointsToExpire = static::where('is_expired', false)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now())
            ->where('points', '>', 0)
            ->get();

        foreach ($pointsToExpire as $point) {
            $point->markAsExpired();
            $expiredCount++;

            // Create an expiry record
            static::create([
                'customer_id' => $point->customer_id,
                'user_id' => 1, // System user
                'type' => 'expired',
                'points' => -$point->points,
                'description' => "Points expired from transaction on " . $point->created_at->format('Y-m-d'),
                'metadata' => [
                    'original_transaction_id' => $point->id,
                    'original_earned_date' => $point->created_at->toISOString(),
                    'expiry_date' => $point->expiry_date->toISOString()
                ]
            ]);
        }

        return $expiredCount;
    }

    public static function getCustomerBalance(int $customerId): int
    {
        return static::where('customer_id', $customerId)
            ->where('is_expired', false)
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', now());
            })
            ->sum('points');
    }

    public static function getCustomerEarnedTotal(int $customerId): int
    {
        return static::where('customer_id', $customerId)
            ->where('type', 'earned')
            ->sum('points');
    }

    public static function getCustomerRedeemedTotal(int $customerId): int
    {
        return abs(static::where('customer_id', $customerId)
            ->where('type', 'redeemed')
            ->sum('points'));
    }

    public static function getCustomerExpiringPoints(int $customerId, int $days = 30): int
    {
        return static::where('customer_id', $customerId)
            ->expiring($days)
            ->sum('points');
    }
}
