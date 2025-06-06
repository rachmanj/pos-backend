<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class SalePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'payment_method_id',
        'processed_by',
        'amount',
        'received_amount',
        'change_amount',
        'processing_fee',
        'status',
        'reference_number',
        'approval_code',
        'gateway_transaction_id',
        'card_last_four',
        'card_type',
        'card_holder_name',
        'bank_name',
        'account_number',
        'transfer_receipt',
        'wallet_provider',
        'wallet_account',
        'denominations_received',
        'denominations_given',
        'voucher_code',
        'voucher_value',
        'credit_account',
        'paid_at',
        'verified_at',
        'settled_at',
        'refunded_amount',
        'refunded_at',
        'refund_reference',
        'refund_reason',
        'notes',
        'gateway_response',
        'metadata',
        'terminal_id',
        'receipt_number',
        'requires_signature',
        'signature_path',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'processing_fee' => 'decimal:2',
        'voucher_value' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'verified_at' => 'datetime',
        'settled_at' => 'datetime',
        'refunded_at' => 'datetime',
        'requires_signature' => 'boolean',
        'denominations_received' => 'array',
        'denominations_given' => 'array',
        'gateway_response' => 'array',
        'metadata' => 'array',
    ];

    // Relationships
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Accessors
    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getIsFailedAttribute(): bool
    {
        return $this->status === 'failed';
    }

    public function getIsRefundedAttribute(): bool
    {
        return in_array($this->status, ['refunded', 'partially_refunded']);
    }

    public function getIsCashAttribute(): bool
    {
        return $this->paymentMethod->type === 'cash';
    }

    public function getIsCardAttribute(): bool
    {
        return $this->paymentMethod->type === 'card';
    }

    public function getIsDigitalAttribute(): bool
    {
        return in_array($this->paymentMethod->type, ['digital_wallet', 'bank_transfer']);
    }

    public function getNetAmountAttribute(): float
    {
        return $this->amount - $this->processing_fee;
    }

    public function getRemainingRefundAmountAttribute(): float
    {
        return max(0, $this->amount - $this->refunded_amount);
    }

    public function getCanRefundAttribute(): bool
    {
        return $this->is_completed && $this->refunded_amount < $this->amount;
    }

    public function getDisplayReferenceAttribute(): string
    {
        if ($this->reference_number) {
            return $this->reference_number;
        }

        if ($this->gateway_transaction_id) {
            return substr($this->gateway_transaction_id, -8);
        }

        if ($this->approval_code) {
            return $this->approval_code;
        }

        return '-';
    }

    public function getMaskedCardNumberAttribute(): ?string
    {
        return $this->card_last_four ? "**** **** **** {$this->card_last_four}" : null;
    }

    public function getMaskedAccountAttribute(): ?string
    {
        if (!$this->account_number) return null;

        $length = strlen($this->account_number);
        if ($length <= 4) return $this->account_number;

        return str_repeat('*', $length - 4) . substr($this->account_number, -4);
    }

    // Business Logic Methods
    public function processPayment(): bool
    {
        // Validate payment method can process this amount
        $validation = $this->paymentMethod->canProcessPayment($this->amount);
        if (!$validation['can_process']) {
            return false;
        }

        // Update payment status
        $this->update([
            'status' => 'completed',
            'paid_at' => now(),
            'processing_fee' => $this->paymentMethod->calculateProcessingFee($this->amount),
        ]);

        // If cash payment, handle change calculation
        if ($this->is_cash && $this->received_amount > $this->amount) {
            $this->update(['change_amount' => $this->received_amount - $this->amount]);
        }

        return true;
    }

    public function verifyPayment(): void
    {
        $this->update([
            'status' => 'completed',
            'verified_at' => now(),
        ]);
    }

    public function settlePayment(): void
    {
        $this->update([
            'settled_at' => now(),
        ]);
    }

    public function processRefund(float $amount, string $reason = ''): bool
    {
        if ($amount > $this->remaining_refund_amount) {
            return false;
        }

        $this->increment('refunded_amount', $amount);

        $newStatus = $this->refunded_amount >= $this->amount ? 'refunded' : 'partially_refunded';

        $this->update([
            'status' => $newStatus,
            'refunded_at' => now(),
            'refund_reason' => $reason,
            'refund_reference' => $this->generateRefundReference(),
        ]);

        return true;
    }

    public function cancelPayment(string $reason = ''): void
    {
        $this->update([
            'status' => 'cancelled',
            'notes' => $this->notes . "\nCancelled: {$reason}",
        ]);
    }

    public function failPayment(string $reason = ''): void
    {
        $this->update([
            'status' => 'failed',
            'notes' => $this->notes . "\nFailed: {$reason}",
        ]);
    }

    protected function generateRefundReference(): string
    {
        return 'REF' . now()->format('YmdHis') . rand(1000, 9999);
    }

    public function generateReceiptNumber(): string
    {
        $prefix = match ($this->paymentMethod->type) {
            'cash' => 'CSH',
            'card' => 'CRD',
            'bank_transfer' => 'TRF',
            'digital_wallet' => 'DGT',
            default => 'PAY'
        };

        $date = now()->format('ymd');
        $sequence = static::whereDate('paid_at', today())
            ->where('payment_method_id', $this->payment_method_id)
            ->count() + 1;

        return "{$prefix}{$date}" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function addGatewayResponse(array $response): void
    {
        $this->update(['gateway_response' => $response]);
    }

    public function getGatewayResponse(string $key = null): mixed
    {
        if ($key) {
            return $this->gateway_response[$key] ?? null;
        }
        return $this->gateway_response;
    }

    public function calculateDenominations(float $amount): array
    {
        $denominations = [100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100, 50];
        $result = [];

        foreach ($denominations as $denom) {
            if ($amount >= $denom) {
                $count = floor($amount / $denom);
                $result[$denom] = $count;
                $amount -= $count * $denom;
            }
        }

        return $result;
    }

    public function setReceivedDenominations(array $denominations): void
    {
        $total = 0;
        foreach ($denominations as $denom => $count) {
            $total += $denom * $count;
        }

        $this->update([
            'received_amount' => $total,
            'denominations_received' => $denominations,
            'change_amount' => max(0, $total - $this->amount),
        ]);

        // Calculate change denominations
        if ($this->change_amount > 0) {
            $changeDenoms = $this->calculateDenominations($this->change_amount);
            $this->update(['denominations_given' => $changeDenoms]);
        }
    }

    // Scopes
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeRefunded(Builder $query): Builder
    {
        return $query->whereIn('status', ['refunded', 'partially_refunded']);
    }

    public function scopeByMethod(Builder $query, string $methodType): Builder
    {
        return $query->whereHas('paymentMethod', function ($q) use ($methodType) {
            $q->where('type', $methodType);
        });
    }

    public function scopeCash(Builder $query): Builder
    {
        return $query->byMethod('cash');
    }

    public function scopeCard(Builder $query): Builder
    {
        return $query->byMethod('card');
    }

    public function scopeDigital(Builder $query): Builder
    {
        return $query->whereHas('paymentMethod', function ($q) {
            $q->whereIn('type', ['digital_wallet', 'bank_transfer']);
        });
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('paid_at', today());
    }

    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('paid_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year);
    }

    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('paid_at', [$startDate, $endDate]);
    }

    public function scopeWithReference(Builder $query, string $reference): Builder
    {
        return $query->where(function ($q) use ($reference) {
            $q->where('reference_number', 'like', "%{$reference}%")
                ->orWhere('gateway_transaction_id', 'like', "%{$reference}%")
                ->orWhere('approval_code', 'like', "%{$reference}%");
        });
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (!$payment->paid_at) {
                $payment->paid_at = now();
            }
            if (!$payment->receipt_number) {
                $payment->receipt_number = $payment->generateReceiptNumber();
            }
            if (!$payment->processed_by) {
                $payment->processed_by = auth()?->id();
            }
        });
    }
}
