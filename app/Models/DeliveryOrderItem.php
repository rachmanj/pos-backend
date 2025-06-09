<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_order_id',
        'sales_order_item_id',
        'product_id',
        'quantity_to_deliver',
        'quantity_delivered',
        'quantity_damaged',
        'quantity_returned',
        'unit_price',
        'line_total',
        'item_status',
        'warehouse_zone_id',
        'pick_location',
        'delivery_notes',
        'quality_notes',
        'damage_reason'
    ];

    protected $casts = [
        'quantity_to_deliver' => 'decimal:3',
        'quantity_delivered' => 'decimal:3',
        'quantity_damaged' => 'decimal:3',
        'quantity_returned' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2'
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PACKED = 'packed';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_DAMAGED = 'damaged';
    const STATUS_RETURNED = 'returned';

    /**
     * Relationships
     */
    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouseZone(): BelongsTo
    {
        return $this->belongsTo(WarehouseZone::class);
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->line_total = $model->quantity_to_deliver * $model->unit_price;
        });

        static::updating(function ($model) {
            if ($model->isDirty(['quantity_to_deliver', 'unit_price'])) {
                $model->line_total = $model->quantity_to_deliver * $model->unit_price;
            }
        });
    }

    /**
     * Scopes
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('item_status', $status);
    }

    public function scopePendingDelivery($query)
    {
        return $query->where('item_status', self::STATUS_PENDING);
    }
}
