<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_number',
        'receipt_number',
        'warehouse_id',
        'customer_id',
        'cash_session_id',
        'served_by',
        'status',
        'type',
        'sale_date',
        'completed_at',
        'subtotal',
        'discount_amount',
        'discount_percentage',
        'tax_amount',
        'tax_percentage',
        'total_amount',
        'paid_amount',
        'change_amount',
        'due_amount',
        'total_items',
        'total_quantity',
        'discount_type',
        'discount_code',
        'loyalty_points_earned',
        'loyalty_points_used',
        'customer_name',
        'customer_phone',
        'notes',
        'internal_notes',
        'metadata',
        'refunded_amount',
        'last_refund_at',
        'requires_delivery',
        'delivery_address',
        'delivery_date',
        'delivery_status',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'completed_at' => 'datetime',
        'delivery_date' => 'datetime',
        'last_refund_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'total_quantity' => 'decimal:2',
        'loyalty_points_earned' => 'decimal:2',
        'loyalty_points_used' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'total_items' => 'integer',
        'requires_delivery' => 'boolean',
        'metadata' => 'array',
    ];

    // Relationships
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function servedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    // Accessors
    public function getCustomerDisplayNameAttribute(): string
    {
        return $this->customer?->name ?? $this->customer_name ?? 'Walk-in Customer';
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsRefundedAttribute(): bool
    {
        return in_array($this->status, ['partially_refunded', 'fully_refunded']);
    }

    public function getIsCancelledAttribute(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getCanRefundAttribute(): bool
    {
        return $this->status === 'completed' && $this->refunded_amount < $this->total_amount;
    }

    public function getRemainingRefundAmountAttribute(): float
    {
        return $this->total_amount - $this->refunded_amount;
    }

    public function getGrossProfitAttribute(): float
    {
        return $this->items->sum('gross_profit');
    }

    public function getProfitMarginAttribute(): float
    {
        return $this->total_amount > 0 ?
            ($this->gross_profit / $this->total_amount) * 100 : 0;
    }

    public function getIsFullyPaidAttribute(): bool
    {
        return $this->due_amount <= 0;
    }

    public function getHasPendingPaymentAttribute(): bool
    {
        return $this->due_amount > 0;
    }

    // Business Logic Methods
    public function generateSaleNumber(): string
    {
        $warehouseCode = $this->warehouse?->code ?? 'WH';
        $date = now()->format('Ymd');
        $sequence = static::whereDate('sale_date', today())
            ->where('warehouse_id', $this->warehouse_id)
            ->count() + 1;

        return "SAL{$warehouseCode}-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function generateReceiptNumber(): string
    {
        $warehouseCode = $this->warehouse?->code ?? 'WH';
        $date = now()->format('ymd');
        $sequence = static::whereDate('sale_date', today())
            ->where('warehouse_id', $this->warehouse_id)
            ->count() + 1;

        return "RCP{$warehouseCode}{$date}" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function calculateTotals(): void
    {
        $items = $this->items()->with('product')->get();

        $subtotal = $items->sum('line_subtotal');
        $totalTax = $items->sum('line_tax_amount');
        $totalDiscount = $items->sum('line_discount_amount');
        $totalQuantity = $items->sum('quantity');
        $totalItems = $items->count();

        // Apply sale-level discount
        if ($this->discount_percentage > 0) {
            $this->discount_amount = ($subtotal * $this->discount_percentage) / 100;
        }

        // Calculate final totals
        $finalSubtotal = $subtotal - $totalDiscount - $this->discount_amount;
        $finalTotal = $finalSubtotal + $totalTax;

        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $totalTax,
            'total_amount' => $finalTotal,
            'total_quantity' => $totalQuantity,
            'total_items' => $totalItems,
            'due_amount' => max(0, $finalTotal - $this->paid_amount),
        ]);
    }

    public function addItem(Product $product, float $quantity, float $unitPrice = null, array $options = []): SaleItem
    {
        $unitPrice = $unitPrice ?? $product->selling_price;
        $lineTotal = $quantity * $unitPrice;
        $costPrice = $product->cost_price;
        $totalCost = $quantity * $costPrice;
        $grossProfit = $lineTotal - $totalCost;

        // Get current stock from warehouse
        $warehouseStock = WarehouseStock::where('warehouse_id', $this->warehouse_id)
            ->where('product_id', $product->id)
            ->first();

        $availableStock = $warehouseStock?->available_quantity ?? 0;

        $saleItem = $this->items()->create([
            'product_id' => $product->id,
            'warehouse_zone_id' => $options['warehouse_zone_id'] ?? null,
            'unit_id' => $product->unit_id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'product_barcode' => $product->barcode,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'original_price' => $product->selling_price,
            'cost_price' => $costPrice,
            'line_total' => $lineTotal,
            'line_subtotal' => $lineTotal,
            'total_cost' => $totalCost,
            'gross_profit' => $grossProfit,
            'available_stock' => $availableStock,
            'lot_number' => $options['lot_number'] ?? null,
            'expiry_date' => $options['expiry_date'] ?? null,
            'serial_number' => $options['serial_number'] ?? null,
            'notes' => $options['notes'] ?? null,
        ]);

        $this->calculateTotals();
        return $saleItem;
    }

    public function addPayment(PaymentMethod $paymentMethod, float $amount, array $paymentData = []): SalePayment
    {
        $payment = $this->payments()->create([
            'payment_method_id' => $paymentMethod->id,
            'processed_by' => auth()->id(),
            'amount' => $amount,
            'received_amount' => $paymentData['received_amount'] ?? $amount,
            'change_amount' => $paymentData['change_amount'] ?? 0,
            'processing_fee' => $paymentMethod->calculateProcessingFee($amount),
            'paid_at' => now(),
            'reference_number' => $paymentData['reference_number'] ?? null,
            'approval_code' => $paymentData['approval_code'] ?? null,
            'card_last_four' => $paymentData['card_last_four'] ?? null,
            'card_type' => $paymentData['card_type'] ?? null,
            'notes' => $paymentData['notes'] ?? null,
        ]);

        // Update paid amount and due amount
        $this->increment('paid_amount', $amount);
        $this->update(['due_amount' => max(0, $this->total_amount - $this->paid_amount)]);

        return $payment;
    }

    public function processRefund(float $amount, string $reason = ''): bool
    {
        if ($amount > $this->remaining_refund_amount) {
            return false;
        }

        $this->increment('refunded_amount', $amount);
        $this->update([
            'last_refund_at' => now(),
            'status' => $this->refunded_amount >= $this->total_amount ? 'fully_refunded' : 'partially_refunded'
        ]);

        // Create refund payments (negative amounts)
        foreach ($this->payments as $payment) {
            if ($amount <= 0) break;

            $refundableAmount = min($amount, $payment->amount);
            if ($refundableAmount > 0) {
                $payment->increment('refunded_amount', $refundableAmount);
                $payment->update([
                    'refunded_at' => now(),
                    'refund_reason' => $reason,
                ]);
                $amount -= $refundableAmount;
            }
        }

        return true;
    }

    public function completeSale(): void
    {
        if ($this->status !== 'draft') {
            return;
        }

        // Process stock deductions
        foreach ($this->items as $item) {
            $this->deductStock($item);
        }

        // Update customer stats if customer exists
        if ($this->customer) {
            $this->customer->updatePurchaseStats($this->total_amount);

            // Award loyalty points (1 point per 1000 IDR)
            $loyaltyPoints = floor($this->total_amount / 1000);
            if ($loyaltyPoints > 0) {
                $this->customer->addLoyaltyPoints($loyaltyPoints);
                $this->update(['loyalty_points_earned' => $loyaltyPoints]);
            }
        }

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    protected function deductStock(SaleItem $item): void
    {
        // Deduct from warehouse stock
        $warehouseStock = WarehouseStock::where('warehouse_id', $this->warehouse_id)
            ->where('product_id', $item->product_id)
            ->first();

        if ($warehouseStock) {
            $warehouseStock->decrement('quantity', $item->quantity);
            $warehouseStock->decrement('available_quantity', $item->quantity);
        }

        // Create stock movement
        StockMovement::create([
            'product_id' => $item->product_id,
            'warehouse_id' => $this->warehouse_id,
            'movement_type' => 'out',
            'quantity' => -$item->quantity,
            'unit_cost' => $item->cost_price,
            'reference_type' => 'sale',
            'reference_id' => $this->id,
            'notes' => "Sale: {$this->sale_number}",
            'user_id' => $this->served_by,
        ]);
    }

    // Scopes
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('sale_date', today());
    }

    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('sale_date', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('sale_date', now()->month)
            ->whereYear('sale_date', now()->year);
    }

    public function scopeWithRefunds(Builder $query): Builder
    {
        return $query->where('refunded_amount', '>', 0);
    }

    public function scopePendingPayment(Builder $query): Builder
    {
        return $query->where('due_amount', '>', 0);
    }

    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('sale_date', [$startDate, $endDate]);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('sale_number', 'like', "%{$search}%")
                ->orWhere('receipt_number', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_phone', 'like', "%{$search}%")
                ->orWhereHas('customer', function ($customer) use ($search) {
                    $customer->where('name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%");
                });
        });
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            if (empty($sale->sale_number)) {
                $sale->sale_number = $sale->generateSaleNumber();
            }
            if (empty($sale->receipt_number)) {
                $sale->receipt_number = $sale->generateReceiptNumber();
            }
            if (!$sale->sale_date) {
                $sale->sale_date = now();
            }
            if (!$sale->served_by) {
                $sale->served_by = auth()->id();
            }
        });
    }
}
