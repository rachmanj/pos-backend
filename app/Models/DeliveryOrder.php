<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class DeliveryOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_order_number',
        'sales_order_id',
        'warehouse_id',
        'delivery_date',
        'delivery_address',
        'delivery_contact',
        'delivery_phone',
        'delivery_status',
        'driver_id',
        'vehicle_id',
        'delivery_notes',
        'packed_at',
        'shipped_at',
        'delivered_at',
        'delivery_confirmed_by',
        'delivery_latitude',
        'delivery_longitude',
        'delivery_signature',
        'special_instructions',
        'cancellation_reason'
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'packed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'delivery_latitude' => 'decimal:8',
        'delivery_longitude' => 'decimal:8'
    ];

    protected $appends = [
        'status_label',
        'total_items',
        'total_packages',
        'is_completed',
        'can_be_packed',
        'can_be_shipped',
        'can_be_delivered'
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PACKED = 'packed';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->delivery_order_number) {
                $model->delivery_order_number = $model->generateDeliveryNumber();
            }
        });
    }

    /**
     * Relationships
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function deliveryConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_confirmed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }

    public function routeStops(): HasMany
    {
        return $this->hasMany(DeliveryRouteStop::class);
    }

    /**
     * Accessors
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->delivery_status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PACKED => 'Packed',
            self::STATUS_IN_TRANSIT => 'In Transit',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Unknown'
        };
    }

    public function getTotalItemsAttribute(): int
    {
        return $this->items()->count();
    }

    public function getTotalPackagesAttribute(): int
    {
        return $this->items()->sum('total_packages') ?? 1;
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->delivery_status === self::STATUS_DELIVERED;
    }

    public function getCanBePackedAttribute(): bool
    {
        return $this->delivery_status === self::STATUS_PENDING;
    }

    public function getCanBeShippedAttribute(): bool
    {
        return $this->delivery_status === self::STATUS_PACKED;
    }

    public function getCanBeDeliveredAttribute(): bool
    {
        return $this->delivery_status === self::STATUS_IN_TRANSIT;
    }

    /**
     * Business Logic Methods
     */
    private function generateDeliveryNumber(): string
    {
        $prefix = 'DO';
        $date = now()->format('Ymd');
        $sequence = self::whereDate('created_at', now()->toDateString())->count() + 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }

    public function markAsPacked(int $userId): bool
    {
        if (!$this->can_be_packed) return false;

        $this->delivery_status = self::STATUS_PACKED;
        $this->packed_at = now();
        return $this->save();
    }

    public function markAsShipped(int $driverId): bool
    {
        if (!$this->can_be_shipped) return false;

        $this->delivery_status = self::STATUS_IN_TRANSIT;
        $this->driver_id = $driverId;
        $this->shipped_at = now();
        return $this->save();
    }

    public function markAsDelivered(int $confirmedBy, ?float $lat = null, ?float $lng = null): bool
    {
        if (!$this->can_be_delivered) return false;

        $this->delivery_status = self::STATUS_DELIVERED;
        $this->delivered_at = now();
        $this->delivery_confirmed_by = $confirmedBy;
        $this->delivery_latitude = $lat;
        $this->delivery_longitude = $lng;

        return $this->save();
    }

    /**
     * Scopes
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('delivery_status', $status);
    }

    public function scopeForDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeScheduledFor($query, string $date)
    {
        return $query->whereDate('delivery_date', $date);
    }
}
