<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class CashSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_number',
        'warehouse_id',
        'opened_by',
        'closed_by',
        'status',
        'opened_at',
        'closed_at',
        'reconciled_at',
        'opening_cash',
        'opening_denominations',
        'opening_notes',
        'closing_cash',
        'closing_denominations',
        'closing_notes',
        'expected_cash',
        'total_sales',
        'total_cash_sales',
        'total_card_sales',
        'total_other_sales',
        'transaction_count',
        'cash_in',
        'cash_out',
        'cash_movements_notes',
        'variance',
        'is_balanced',
        'variance_notes',
        'session_summary',
        'manager_notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'opening_cash' => 'decimal:2',
        'closing_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'total_cash_sales' => 'decimal:2',
        'total_card_sales' => 'decimal:2',
        'total_other_sales' => 'decimal:2',
        'cash_in' => 'decimal:2',
        'cash_out' => 'decimal:2',
        'variance' => 'decimal:2',
        'is_balanced' => 'boolean',
        'transaction_count' => 'integer',
        'opening_denominations' => 'array',
        'closing_denominations' => 'array',
        'session_summary' => 'array',
    ];

    // Relationships
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    // Accessors
    public function getDurationAttribute(): ?string
    {
        if (!$this->opened_at) return null;

        $end = $this->closed_at ?? now();
        $duration = $this->opened_at->diff($end);

        return $duration->format('%h:%I:%S');
    }

    public function getIsOpenAttribute(): bool
    {
        return $this->status === 'open';
    }

    public function getIsClosedAttribute(): bool
    {
        return $this->status === 'closed';
    }

    public function getIsReconciledAttribute(): bool
    {
        return $this->status === 'reconciled';
    }

    public function getVariancePercentageAttribute(): float
    {
        if ($this->expected_cash == 0) return 0;
        return ($this->variance / $this->expected_cash) * 100;
    }

    public function getAverageTransactionValueAttribute(): float
    {
        return $this->transaction_count > 0 ?
            $this->total_sales / $this->transaction_count : 0;
    }

    // Business Logic Methods
    public function generateSessionNumber(): string
    {
        $warehouseCode = $this->warehouse?->code ?? 'WH';
        $date = now()->format('Ymd');
        $sequence = static::whereDate('opened_at', today())
            ->where('warehouse_id', $this->warehouse_id)
            ->count() + 1;

        return "CS{$warehouseCode}-{$date}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    public function openSession(float $openingCash, array $denominations = [], string $notes = ''): void
    {
        $this->update([
            'status' => 'open',
            'opened_at' => now(),
            'opening_cash' => $openingCash,
            'opening_denominations' => $denominations,
            'opening_notes' => $notes,
        ]);
    }

    public function closeSession(float $closingCash, array $denominations = [], string $notes = ''): void
    {
        $this->calculateSessionTotals();

        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closing_cash' => $closingCash,
            'closing_denominations' => $denominations,
            'closing_notes' => $notes,
            'variance' => $closingCash - $this->expected_cash,
            'is_balanced' => abs($closingCash - $this->expected_cash) < 0.01, // Allow 1 cent variance
        ]);
    }

    public function reconcileSession(string $managerNotes = ''): void
    {
        $this->update([
            'status' => 'reconciled',
            'reconciled_at' => now(),
            'manager_notes' => $managerNotes,
        ]);
    }

    public function calculateSessionTotals(): void
    {
        $sales = $this->sales()->where('status', '!=', 'cancelled')->get();

        $totalSales = $sales->sum('total_amount');
        $totalCashSales = 0;
        $totalCardSales = 0;
        $totalOtherSales = 0;
        $transactionCount = $sales->count();

        foreach ($sales as $sale) {
            foreach ($sale->payments as $payment) {
                if ($payment->status === 'completed') {
                    switch ($payment->paymentMethod->type) {
                        case 'cash':
                            $totalCashSales += $payment->amount;
                            break;
                        case 'card':
                            $totalCardSales += $payment->amount;
                            break;
                        default:
                            $totalOtherSales += $payment->amount;
                    }
                }
            }
        }

        $expectedCash = $this->opening_cash + $totalCashSales + $this->cash_in - $this->cash_out;

        $this->update([
            'total_sales' => $totalSales,
            'total_cash_sales' => $totalCashSales,
            'total_card_sales' => $totalCardSales,
            'total_other_sales' => $totalOtherSales,
            'transaction_count' => $transactionCount,
            'expected_cash' => $expectedCash,
        ]);
    }

    public function addCashMovement(float $amount, string $type, string $notes = ''): void
    {
        if ($type === 'in') {
            $this->increment('cash_in', $amount);
        } else {
            $this->increment('cash_out', $amount);
        }

        $movements = $this->cash_movements_notes ?
            $this->cash_movements_notes . "\n" . $notes : $notes;

        $this->update(['cash_movements_notes' => $movements]);
    }

    public function generateSessionSummary(): array
    {
        return [
            'session_info' => [
                'number' => $this->session_number,
                'warehouse' => $this->warehouse->name,
                'opened_by' => $this->openedBy->name,
                'closed_by' => $this->closedBy?->name,
                'duration' => $this->duration,
            ],
            'cash_summary' => [
                'opening_cash' => $this->opening_cash,
                'closing_cash' => $this->closing_cash,
                'expected_cash' => $this->expected_cash,
                'variance' => $this->variance,
                'is_balanced' => $this->is_balanced,
            ],
            'sales_summary' => [
                'total_sales' => $this->total_sales,
                'cash_sales' => $this->total_cash_sales,
                'card_sales' => $this->total_card_sales,
                'other_sales' => $this->total_other_sales,
                'transaction_count' => $this->transaction_count,
                'average_transaction' => $this->average_transaction_value,
            ],
            'cash_movements' => [
                'cash_in' => $this->cash_in,
                'cash_out' => $this->cash_out,
                'net_movement' => $this->cash_in - $this->cash_out,
            ],
        ];
    }

    public function canAddSale(): bool
    {
        return $this->status === 'open';
    }

    public function canClose(): bool
    {
        return $this->status === 'open';
    }

    public function canReconcile(): bool
    {
        return $this->status === 'closed';
    }

    // Scopes
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    public function scopeReconciled(Builder $query): Builder
    {
        return $query->where('status', 'reconciled');
    }

    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('opened_at', today());
    }

    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('opened_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('opened_at', now()->month)
            ->whereYear('opened_at', now()->year);
    }

    public function scopeWithVariance(Builder $query): Builder
    {
        return $query->where('variance', '!=', 0);
    }

    public function scopeUnbalanced(Builder $query): Builder
    {
        return $query->where('is_balanced', false);
    }

    // Static Methods
    public static function getActiveSession(int $warehouseId): ?CashSession
    {
        return static::where('warehouse_id', $warehouseId)
            ->where('status', 'open')
            ->first();
    }

    public static function hasActiveSession(int $warehouseId): bool
    {
        return static::getActiveSession($warehouseId) !== null;
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($cashSession) {
            if (empty($cashSession->session_number)) {
                $cashSession->session_number = $cashSession->generateSessionNumber();
            }
            if (!$cashSession->opened_at) {
                $cashSession->opened_at = now();
            }
        });

        static::updating(function ($cashSession) {
            if ($cashSession->isDirty('status') && $cashSession->status === 'closed') {
                $cashSession->session_summary = $cashSession->generateSessionSummary();
            }
        });
    }
}
