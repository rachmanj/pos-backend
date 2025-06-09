<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class DeliveryRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_name',
        'route_code',
        'route_date',
        'planned_start_time',
        'planned_end_time',
        'actual_start_time',
        'actual_end_time',
        'driver_id',
        'vehicle_id',
        'vehicle_type',
        'vehicle_capacity',
        'total_distance',
        'estimated_duration',
        'actual_duration',
        'total_stops',
        'completed_stops',
        'route_status',
        'start_latitude',
        'start_longitude',
        'end_latitude',
        'end_longitude',
        'optimization_notes',
        'route_waypoints',
        'fuel_cost',
        'driver_cost',
        'total_route_cost',
        'route_notes',
        'cancellation_reason',
        'created_by'
    ];

    protected $casts = [
        'route_date' => 'date',
        'planned_start_time' => 'datetime:H:i',
        'planned_end_time' => 'datetime:H:i',
        'actual_start_time' => 'datetime:H:i',
        'actual_end_time' => 'datetime:H:i',
        'vehicle_capacity' => 'decimal:2',
        'total_distance' => 'decimal:2',
        'fuel_cost' => 'decimal:2',
        'driver_cost' => 'decimal:2',
        'total_route_cost' => 'decimal:2',
        'start_latitude' => 'decimal:8',
        'start_longitude' => 'decimal:8',
        'end_latitude' => 'decimal:8',
        'end_longitude' => 'decimal:8',
        'route_waypoints' => 'json'
    ];

    // Status constants
    const STATUS_PLANNED = 'planned';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_DELAYED = 'delayed';

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->route_code) {
                $model->route_code = $model->generateRouteCode();
            }
            if (!$model->created_by) {
                $model->created_by = Auth::id();
            }
        });
    }

    /**
     * Relationships
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(DeliveryRouteStop::class)->orderBy('stop_sequence');
    }

    /**
     * Business Logic
     */
    private function generateRouteCode(): string
    {
        $prefix = 'RT';
        $date = now()->format('md');
        $sequence = self::whereDate('route_date', now()->toDateString())->count() + 1;

        return sprintf('%s-%s-%03d', $prefix, $date, $sequence);
    }

    /**
     * Scopes
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('route_status', $status);
    }

    public function scopeForDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeScheduledFor($query, string $date)
    {
        return $query->whereDate('route_date', $date);
    }
}
