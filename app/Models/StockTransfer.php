<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class StockTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_number',
        'from_warehouse_id',
        'to_warehouse_id',
        'type',
        'status',
        'priority',
        'requested_date',
        'expected_date',
        'shipped_date',
        'received_date',
        'requested_by',
        'approved_by',
        'shipped_by',
        'received_by',
        'approved_at',
        'approval_notes',
        'shipped_at',
        'shipping_notes',
        'received_at',
        'receiving_notes',
        'reason',
        'description',
        'total_items',
        'total_quantity',
        'total_value',
        'carrier',
        'tracking_number',
        'shipping_cost',
        'shipping_address',
        'is_urgent',
        'requires_approval',
        'completion_percentage',
        'status_history',
    ];

    protected $casts = [
        'from_warehouse_id' => 'integer',
        'to_warehouse_id' => 'integer',
        'requested_date' => 'date',
        'expected_date' => 'date',
        'shipped_date' => 'date',
        'received_date' => 'date',
        'requested_by' => 'integer',
        'approved_by' => 'integer',
        'shipped_by' => 'integer',
        'received_by' => 'integer',
        'approved_at' => 'datetime',
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
        'total_items' => 'integer',
        'total_quantity' => 'decimal:4',
        'total_value' => 'decimal:4',
        'shipping_cost' => 'decimal:4',
        'is_urgent' => 'boolean',
        'requires_approval' => 'boolean',
        'completion_percentage' => 'decimal:2',
        'status_history' => 'array',
    ];

    // Relationships
    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function shippedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    // Scopes
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeFromWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('from_warehouse_id', $warehouseId);
    }

    public function scopeToWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('to_warehouse_id', $warehouseId);
    }

    public function scopeInvolving(Builder $query, int $warehouseId): Builder
    {
        return $query->where(function ($q) use ($warehouseId) {
            $q->where('from_warehouse_id', $warehouseId)
                ->orWhere('to_warehouse_id', $warehouseId);
        });
    }

    public function scopeUrgent(Builder $query): Builder
    {
        return $query->where('is_urgent', true);
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('status', 'pending_approval')
            ->where('requires_approval', true);
    }

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->where('status', 'in_transit');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('expected_date')
            ->where('expected_date', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled']);
    }

    // Accessors
    public function getCanBeApprovedAttribute(): bool
    {
        return $this->status === 'pending_approval' &&
            $this->requires_approval &&
            !$this->approved_by;
    }

    public function getCanBeShippedAttribute(): bool
    {
        return in_array($this->status, ['approved', 'pending_approval']) &&
            (!$this->requires_approval || $this->approved_by) &&
            !$this->shipped_by;
    }

    public function getCanBeReceivedAttribute(): bool
    {
        return $this->status === 'in_transit' &&
            $this->shipped_by &&
            !$this->received_by;
    }

    public function getCanBeCancelledAttribute(): bool
    {
        return !in_array($this->status, ['completed', 'cancelled', 'in_transit']);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->expected_date &&
            $this->expected_date < now() &&
            !in_array($this->status, ['completed', 'cancelled']);
    }

    public function getDaysOverdueAttribute(): ?int
    {
        if (!$this->is_overdue) {
            return null;
        }

        return now()->diffInDays($this->expected_date);
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'pending_approval' => 'yellow',
            'approved' => 'blue',
            'in_transit' => 'purple',
            'partially_received' => 'orange',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    public function getPriorityBadgeColorAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'gray',
            'normal' => 'blue',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'gray',
        };
    }

    // Methods
    public function approve(int $userId, ?string $notes = null): bool
    {
        if (!$this->can_be_approved) {
            return false;
        }

        $this->status = 'approved';
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->approval_notes = $notes;

        $this->addStatusHistory('approved', $userId, $notes);
        $this->save();

        return true;
    }

    public function ship(int $userId, ?string $notes = null, ?array $shippingData = null): bool
    {
        if (!$this->can_be_shipped) {
            return false;
        }

        $this->status = 'in_transit';
        $this->shipped_by = $userId;
        $this->shipped_at = now();
        $this->shipped_date = now()->toDateString();
        $this->shipping_notes = $notes;

        if ($shippingData) {
            $this->carrier = $shippingData['carrier'] ?? null;
            $this->tracking_number = $shippingData['tracking_number'] ?? null;
            $this->shipping_cost = $shippingData['shipping_cost'] ?? 0;
        }

        // Update item statuses
        $this->items()->update(['status' => 'in_transit']);

        $this->addStatusHistory('shipped', $userId, $notes);
        $this->save();

        return true;
    }

    public function receive(int $userId, ?string $notes = null): bool
    {
        if (!$this->can_be_received) {
            return false;
        }

        // Check if all items are received
        $totalItems = $this->items()->count();
        $receivedItems = $this->items()->where('status', 'received')->count();

        if ($receivedItems === $totalItems) {
            $this->status = 'completed';
            $this->completion_percentage = 100;
        } else {
            $this->status = 'partially_received';
            $this->completion_percentage = $totalItems > 0 ? ($receivedItems / $totalItems) * 100 : 0;
        }

        $this->received_by = $userId;
        $this->received_at = now();
        $this->received_date = now()->toDateString();
        $this->receiving_notes = $notes;

        $this->addStatusHistory('received', $userId, $notes);
        $this->save();

        return true;
    }

    public function cancel(int $userId, ?string $reason = null): bool
    {
        if (!$this->can_be_cancelled) {
            return false;
        }

        $this->status = 'cancelled';

        // Cancel all pending items
        $this->items()->whereIn('status', ['pending', 'shipped'])->update(['status' => 'cancelled']);

        $this->addStatusHistory('cancelled', $userId, $reason);
        $this->save();

        return true;
    }

    public function updateTotals(): void
    {
        $this->total_items = $this->items()->count();
        $this->total_quantity = $this->items()->sum('requested_quantity');
        $this->total_value = $this->items()->sum('total_cost');
        $this->save();
    }

    public function updateCompletionPercentage(): void
    {
        $totalItems = $this->items()->count();

        if ($totalItems === 0) {
            $this->completion_percentage = 0;
            return;
        }

        $receivedItems = $this->items()->where('status', 'received')->count();
        $this->completion_percentage = ($receivedItems / $totalItems) * 100;
        $this->save();
    }

    private function addStatusHistory(string $status, int $userId, ?string $notes = null): void
    {
        $history = $this->status_history ?? [];

        $history[] = [
            'status' => $status,
            'user_id' => $userId,
            'timestamp' => now()->toISOString(),
            'notes' => $notes,
        ];

        $this->status_history = $history;
    }

    // Static methods
    public static function generateTransferNumber(): string
    {
        $year = now()->year;
        $lastTransfer = static::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastTransfer ?
            (int) substr($lastTransfer->transfer_number, -3) + 1 : 1;

        return 'TRF-' . $year . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    public static function getPendingApprovals(): \Illuminate\Database\Eloquent\Collection
    {
        return static::pendingApproval()
            ->with(['fromWarehouse', 'toWarehouse', 'requestedBy'])
            ->orderBy('created_at')
            ->get();
    }

    public static function getOverdueTransfers(): \Illuminate\Database\Eloquent\Collection
    {
        return static::overdue()
            ->with(['fromWarehouse', 'toWarehouse', 'requestedBy'])
            ->orderBy('expected_date')
            ->get();
    }

    public static function getUrgentTransfers(): \Illuminate\Database\Eloquent\Collection
    {
        return static::urgent()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with(['fromWarehouse', 'toWarehouse', 'requestedBy'])
            ->orderBy('created_at')
            ->get();
    }
}
