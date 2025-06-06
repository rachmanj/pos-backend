<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'is_active',
        'requires_reference',
        'has_processing_fee',
        'processing_fee_percentage',
        'processing_fee_fixed',
        'minimum_amount',
        'maximum_amount',
        'affects_cash_drawer',
        'requires_change',
        'gateway_provider',
        'gateway_config',
        'account_number',
        'icon',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_reference' => 'boolean',
        'has_processing_fee' => 'boolean',
        'processing_fee_percentage' => 'decimal:2',
        'processing_fee_fixed' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'maximum_amount' => 'decimal:2',
        'affects_cash_drawer' => 'boolean',
        'requires_change' => 'boolean',
        'gateway_config' => 'array',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function salePayments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    // Accessors
    public function getIsCashAttribute(): bool
    {
        return $this->type === 'cash';
    }

    public function getIsCardAttribute(): bool
    {
        return $this->type === 'card';
    }

    public function getIsDigitalAttribute(): bool
    {
        return in_array($this->type, ['digital_wallet', 'bank_transfer']);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name . ($this->account_number ? " ({$this->account_number})" : '');
    }

    // Business Logic Methods
    public function calculateProcessingFee(float $amount): float
    {
        if (!$this->has_processing_fee) {
            return 0;
        }

        $percentageFee = ($amount * $this->processing_fee_percentage) / 100;
        return $percentageFee + $this->processing_fee_fixed;
    }

    public function isValidAmount(float $amount): bool
    {
        if ($amount < $this->minimum_amount) {
            return false;
        }

        if ($this->maximum_amount && $amount > $this->maximum_amount) {
            return false;
        }

        return true;
    }

    public function canProcessPayment(float $amount): array
    {
        $errors = [];

        if (!$this->is_active) {
            $errors[] = 'Payment method is not active';
        }

        if (!$this->isValidAmount($amount)) {
            if ($amount < $this->minimum_amount) {
                $errors[] = "Minimum amount is " . number_format($this->minimum_amount, 0, ',', '.');
            }
            if ($this->maximum_amount && $amount > $this->maximum_amount) {
                $errors[] = "Maximum amount is " . number_format($this->maximum_amount, 0, ',', '.');
            }
        }

        return [
            'can_process' => empty($errors),
            'errors' => $errors
        ];
    }

    public function getGatewayConfig(string $key = null): mixed
    {
        if ($key) {
            return $this->gateway_config[$key] ?? null;
        }
        return $this->gateway_config;
    }

    public function setGatewayConfig(string $key, mixed $value): void
    {
        $config = $this->gateway_config ?? [];
        $config[$key] = $value;
        $this->gateway_config = $config;
        $this->save();
    }

    public function getTotalProcessedToday(): float
    {
        return $this->salePayments()
            ->whereDate('paid_at', today())
            ->where('status', 'completed')
            ->sum('amount');
    }

    public function getTotalProcessedThisMonth(): float
    {
        return $this->salePayments()
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->where('status', 'completed')
            ->sum('amount');
    }

    public function getTransactionCount(string $period = 'today'): int
    {
        $query = $this->salePayments()->where('status', 'completed');

        return match ($period) {
            'today' => $query->whereDate('paid_at', today())->count(),
            'week' => $query->whereBetween('paid_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'month' => $query->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->count(),
            'year' => $query->whereYear('paid_at', now()->year)->count(),
            default => $query->count()
        };
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeCash(Builder $query): Builder
    {
        return $query->where('type', 'cash');
    }

    public function scopeCard(Builder $query): Builder
    {
        return $query->where('type', 'card');
    }

    public function scopeDigital(Builder $query): Builder
    {
        return $query->whereIn('type', ['digital_wallet', 'bank_transfer']);
    }

    public function scopeForAmount(Builder $query, float $amount): Builder
    {
        return $query->where('minimum_amount', '<=', $amount)
            ->where(function ($q) use ($amount) {
                $q->whereNull('maximum_amount')
                    ->orWhere('maximum_amount', '>=', $amount);
            });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Static Methods
    public static function getDefaultCashMethod(): ?PaymentMethod
    {
        return static::where('type', 'cash')
            ->where('is_active', true)
            ->first();
    }

    public static function getAvailableForAmount(float $amount): Builder
    {
        return static::active()
            ->forAmount($amount)
            ->ordered();
    }

    // Constants for common payment types
    const TYPE_CASH = 'cash';
    const TYPE_CARD = 'card';
    const TYPE_BANK_TRANSFER = 'bank_transfer';
    const TYPE_DIGITAL_WALLET = 'digital_wallet';
    const TYPE_CREDIT = 'credit';
    const TYPE_VOUCHER = 'voucher';
    const TYPE_OTHER = 'other';

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($paymentMethod) {
            if ($paymentMethod->sort_order === null) {
                $maxOrder = static::max('sort_order') ?? 0;
                $paymentMethod->sort_order = $maxOrder + 1;
            }
        });
    }
}
