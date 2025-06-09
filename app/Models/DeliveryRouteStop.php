<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryRouteStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_route_id',
        'delivery_order_id',
        'stop_sequence',
        'estimated_arrival',
        'actual_arrival',
        'stop_duration',
        'stop_status',
        'stop_notes'
    ];

    protected $casts = [
        'estimated_arrival' => 'datetime',
        'actual_arrival' => 'datetime',
        'stop_duration' => 'integer',
        'stop_sequence' => 'integer'
    ];

    protected $appends = [
        'status_label',
        'duration_formatted'
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_ARRIVED = 'arrived';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Relationship: Delivery Route
     */
    public function deliveryRoute(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class);
    }

    /**
     * Relationship: Delivery Order
     */
    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    /**
     * Accessor: Status Label
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->stop_status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ARRIVED => 'Arrived',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            default => 'Unknown'
        };
    }

    /**
     * Accessor: Duration Formatted
     */
    public function getDurationFormattedAttribute(): ?string
    {
        if (!$this->stop_duration) {
            return null;
        }

        $hours = floor($this->stop_duration / 60);
        $minutes = $this->stop_duration % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

    /**
     * Scope: By Status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('stop_status', $status);
    }

    /**
     * Scope: Pending
     */
    public function scopePending($query)
    {
        return $query->where('stop_status', self::STATUS_PENDING);
    }

    /**
     * Scope: Completed
     */
    public function scopeCompleted($query)
    {
        return $query->where('stop_status', self::STATUS_COMPLETED);
    }

    /**
     * Scope: Failed
     */
    public function scopeFailed($query)
    {
        return $query->where('stop_status', self::STATUS_FAILED);
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($routeStop) {
            // Auto-assign stop sequence if not provided
            if (!$routeStop->stop_sequence) {
                $maxSequence = static::where('delivery_route_id', $routeStop->delivery_route_id)
                    ->max('stop_sequence') ?? 0;
                $routeStop->stop_sequence = $maxSequence + 1;
            }
        });

        static::updated(function ($routeStop) {
            // Update delivery order status when stop status changes
            if ($routeStop->isDirty('stop_status')) {
                $deliveryOrder = $routeStop->deliveryOrder;

                if ($deliveryOrder) {
                    switch ($routeStop->stop_status) {
                        case self::STATUS_COMPLETED:
                            $deliveryOrder->update(['delivery_status' => 'delivered']);
                            break;
                        case self::STATUS_FAILED:
                            $deliveryOrder->update(['delivery_status' => 'failed']);
                            break;
                        case self::STATUS_ARRIVED:
                            if ($deliveryOrder->delivery_status === 'pending') {
                                $deliveryOrder->update(['delivery_status' => 'in_transit']);
                            }
                            break;
                    }
                }
            }
        });
    }
}
