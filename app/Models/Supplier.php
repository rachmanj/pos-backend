<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'tax_number',
        'payment_terms',
        'status',
    ];

    protected $casts = [
        'payment_terms' => 'integer',
        'status' => 'string',
    ];

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

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
