<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'contact_person',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'tax_number',
        'payment_terms',
        'status',
    ];

    protected $casts = [
        'payment_terms' => 'integer',
        'status' => 'string',
    ];

    // Relationships
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function activePurchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class)->whereNotIn('status', ['cancelled']);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('contact_person', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });
    }

    // Accessors
    public function getDisplayNameAttribute(): string
    {
        return $this->contact_person
            ? "{$this->name} ({$this->contact_person})"
            : $this->name;
    }

    public function getFormattedAddressAttribute(): string
    {
        return $this->address ? nl2br(e($this->address)) : '';
    }

    public function getPaymentTermsTextAttribute(): string
    {
        return $this->payment_terms === 1
            ? '1 day'
            : "{$this->payment_terms} days";
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getTotalPurchaseOrdersCount(): int
    {
        return $this->purchaseOrders()->count();
    }

    public function getActivePurchaseOrdersCount(): int
    {
        return $this->activePurchaseOrders()->count();
    }
}
