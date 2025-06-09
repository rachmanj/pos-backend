<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SalesOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_number',
        'customer_id',
        'warehouse_id',
        'order_date',
        'requested_delivery_date',
        'confirmed_delivery_date',
        'subtotal_amount',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'order_status',
        'payment_terms_days',
        'credit_approved_by',
        'credit_approval_date',
        'sales_rep_id',
        'notes',
        'special_instructions',
        'created_by',
        'approved_by',
        'cancelled_by',
        'cancellation_reason'
    ];

    protected $casts = [
        'order_date' => 'date',
        'requested_delivery_date' => 'date',
        'confirmed_delivery_date' => 'date',
        'credit_approval_date' => 'datetime',
        'subtotal_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payment_terms_days' => 'integer'
    ];

    protected $appends = [
        'status_label',
        'is_overdue',
        'days_until_delivery',
        'total_items',
        'total_quantity',
        'completion_percentage',
        'can_be_cancelled',
        'can_be_approved',
        'can_be_modified'
    ];

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_APPROVED = 'approved';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Boot method to handle automatic field population
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->sales_order_number) {
                $model->sales_order_number = $model->generateOrderNumber();
            }
            if (!$model->created_by) {
                $model->created_by = Auth::id();
            }
            if (!$model->order_date) {
                $model->order_date = now()->toDateString();
            }
        });

        static::updating(function ($model) {
            // Auto-update totals when items change
            if ($model->isDirty(['subtotal_amount', 'tax_amount', 'discount_amount'])) {
                $model->calculateTotals();
            }
        });
    }

    /**
     * Relationships
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function creditApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credit_approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    /**
     * Accessor for status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->order_status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Unknown'
        };
    }

    /**
     * Accessor for overdue status
     */
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->requested_delivery_date) {
            return false;
        }

        return Carbon::parse($this->requested_delivery_date)->isPast() &&
            !in_array($this->order_status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    /**
     * Accessor for days until delivery
     */
    public function getDaysUntilDeliveryAttribute(): ?int
    {
        if (!$this->requested_delivery_date) {
            return null;
        }

        return Carbon::now()->diffInDays(Carbon::parse($this->requested_delivery_date), false);
    }

    /**
     * Accessor for total items count
     */
    public function getTotalItemsAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Accessor for total quantity
     */
    public function getTotalQuantityAttribute(): float
    {
        return $this->items()->sum('quantity_ordered');
    }

    /**
     * Accessor for completion percentage
     */
    public function getCompletionPercentageAttribute(): float
    {
        $totalQuantity = $this->items()->sum('quantity_ordered');
        if ($totalQuantity == 0) {
            return 0;
        }

        $deliveredQuantity = $this->items()->sum('quantity_delivered');
        return round(($deliveredQuantity / $totalQuantity) * 100, 2);
    }

    /**
     * Business Logic: Check if order can be cancelled
     */
    public function getCanBeCancelledAttribute(): bool
    {
        return in_array($this->order_status, [
            self::STATUS_DRAFT,
            self::STATUS_CONFIRMED,
            self::STATUS_APPROVED
        ]) && $this->completion_percentage < 50;
    }

    /**
     * Business Logic: Check if order can be approved
     */
    public function getCanBeApprovedAttribute(): bool
    {
        return $this->order_status === self::STATUS_CONFIRMED;
    }

    /**
     * Business Logic: Check if order can be modified
     */
    public function getCanBeModifiedAttribute(): bool
    {
        return in_array($this->order_status, [
            self::STATUS_DRAFT,
            self::STATUS_CONFIRMED
        ]);
    }

    /**
     * Business Logic: Generate unique order number
     */
    private function generateOrderNumber(): string
    {
        $prefix = 'SO';
        $date = now()->format('Ymd');
        $sequence = self::whereDate('created_at', now()->toDateString())->count() + 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }

    /**
     * Business Logic: Calculate totals from items
     */
    public function calculateTotals(): void
    {
        $this->subtotal_amount = $this->items()->sum('line_total');
        $this->tax_amount = $this->subtotal_amount * 0.11; // 11% Indonesian PPN
        $this->total_amount = $this->subtotal_amount + $this->tax_amount - $this->discount_amount;
    }

    /**
     * Business Logic: Confirm order
     */
    public function confirm(): bool
    {
        if ($this->order_status !== self::STATUS_DRAFT) {
            return false;
        }

        $this->order_status = self::STATUS_CONFIRMED;
        return $this->save();
    }

    /**
     * Business Logic: Approve order
     */
    public function approve(int $approvedBy): bool
    {
        if ($this->order_status !== self::STATUS_CONFIRMED) {
            return false;
        }

        $this->order_status = self::STATUS_APPROVED;
        $this->approved_by = $approvedBy;
        return $this->save();
    }

    /**
     * Business Logic: Cancel order
     */
    public function cancel(int $cancelledBy, string $reason): bool
    {
        if (!$this->can_be_cancelled) {
            return false;
        }

        $this->order_status = self::STATUS_CANCELLED;
        $this->cancelled_by = $cancelledBy;
        $this->cancellation_reason = $reason;
        return $this->save();
    }

    /**
     * Business Logic: Check stock availability for all items
     */
    public function checkStockAvailability(): array
    {
        $unavailableItems = [];

        foreach ($this->items as $item) {
            $stock = WarehouseStock::where('warehouse_id', $this->warehouse_id)
                ->where('product_id', $item->product_id)
                ->first();

            if (!$stock || $stock->quantity < $item->quantity_ordered) {
                $unavailableItems[] = [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'required_quantity' => $item->quantity_ordered,
                    'available_quantity' => $stock ? $stock->quantity : 0
                ];
            }
        }

        return $unavailableItems;
    }

    /**
     * Scope: Filter by status
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('order_status', $status);
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeInDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('order_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Filter by customer
     */
    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope: Filter by sales rep
     */
    public function scopeForSalesRep($query, int $salesRepId)
    {
        return $query->where('sales_rep_id', $salesRepId);
    }

    /**
     * Scope: Overdue orders
     */
    public function scopeOverdue($query)
    {
        return $query->where('requested_delivery_date', '<', now()->toDateString())
            ->whereNotIn('order_status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }
}
