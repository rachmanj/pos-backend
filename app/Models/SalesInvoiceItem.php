<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_invoice_id',
        'product_id',
        'sales_order_item_id',
        'delivery_order_item_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'line_total',
        'product_name',
        'product_sku',
        'description',
        'item_notes'
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2'
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->calculateTotals();
        });

        static::updating(function ($model) {
            if ($model->isDirty(['quantity', 'unit_price', 'discount_amount', 'tax_rate'])) {
                $model->calculateTotals();
            }
        });
    }

    /**
     * Relationships
     */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    public function deliveryOrderItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrderItem::class);
    }

    /**
     * Business Logic
     */
    private function calculateTotals(): void
    {
        $subtotal = ($this->quantity * $this->unit_price) - $this->discount_amount;
        $this->tax_amount = $subtotal * ($this->tax_rate / 100);
        $this->line_total = $subtotal + $this->tax_amount;
    }
}
