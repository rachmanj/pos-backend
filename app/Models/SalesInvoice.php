<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class SalesInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'sales_order_id',
        'delivery_order_id',
        'customer_id',
        'invoice_date',
        'due_date',
        'payment_terms_days',
        'subtotal_amount',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'outstanding_amount',
        'invoice_status',
        'payment_status',
        'invoice_notes',
        'payment_instructions',
        'invoice_file_path',
        'created_by',
        'sent_by',
        'sent_at',
        'viewed_at',
        'paid_at',
        'cancellation_reason',
        'cancelled_by'
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'payment_terms_days' => 'integer',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'paid_at' => 'datetime'
    ];

    protected $appends = [
        'status_label',
        'payment_status_label',
        'is_overdue',
        'days_overdue',
        'payment_percentage'
    ];

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_VIEWED = 'viewed';
    const STATUS_PAID = 'paid';
    const STATUS_PARTIAL_PAID = 'partial_paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CANCELLED = 'cancelled';

    const PAYMENT_UNPAID = 'unpaid';
    const PAYMENT_PARTIAL = 'partial';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_OVERPAID = 'overpaid';

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->invoice_number) {
                $model->invoice_number = $model->generateInvoiceNumber();
            }
            if (!$model->created_by) {
                $model->created_by = Auth::id();
            }
            if (!$model->invoice_date) {
                $model->invoice_date = now()->toDateString();
            }
            if (!$model->due_date && $model->payment_terms_days) {
                $model->due_date = now()->addDays($model->payment_terms_days)->toDateString();
            }
            $model->outstanding_amount = $model->total_amount;
        });
    }

    /**
     * Relationships
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }

    /**
     * Accessors
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->invoice_status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SENT => 'Sent',
            self::STATUS_VIEWED => 'Viewed',
            self::STATUS_PAID => 'Paid',
            self::STATUS_PARTIAL_PAID => 'Partial Paid',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Unknown'
        };
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_UNPAID => 'Unpaid',
            self::PAYMENT_PARTIAL => 'Partial',
            self::PAYMENT_PAID => 'Paid',
            self::PAYMENT_OVERPAID => 'Overpaid',
            default => 'Unknown'
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date < now()->toDateString() &&
            $this->payment_status !== self::PAYMENT_PAID;
    }

    public function getDaysOverdueAttribute(): int
    {
        if (!$this->is_overdue) return 0;
        return now()->diffInDays($this->due_date);
    }

    public function getPaymentPercentageAttribute(): float
    {
        if ($this->total_amount == 0) return 0;
        return round(($this->paid_amount / $this->total_amount) * 100, 2);
    }

    /**
     * Business Logic
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $date = now()->format('Ymd');
        $sequence = self::whereDate('created_at', now()->toDateString())->count() + 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }

    public function markAsSent(int $sentBy): bool
    {
        if ($this->invoice_status !== self::STATUS_DRAFT) return false;

        $this->invoice_status = self::STATUS_SENT;
        $this->sent_by = $sentBy;
        $this->sent_at = now();
        return $this->save();
    }

    public function addPayment(float $amount): bool
    {
        if ($amount <= 0) return false;

        $this->paid_amount += $amount;
        $this->outstanding_amount = $this->total_amount - $this->paid_amount;

        // Update payment status
        if ($this->paid_amount >= $this->total_amount) {
            $this->payment_status = $this->paid_amount > $this->total_amount ?
                self::PAYMENT_OVERPAID : self::PAYMENT_PAID;
            $this->invoice_status = self::STATUS_PAID;
            $this->paid_at = now();
        } else {
            $this->payment_status = self::PAYMENT_PARTIAL;
            $this->invoice_status = self::STATUS_PARTIAL_PAID;
        }

        return $this->save();
    }

    /**
     * Scopes
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('invoice_status', $status);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now()->toDateString())
            ->where('payment_status', '!=', self::PAYMENT_PAID);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
