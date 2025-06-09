<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'credit_limit',
        'current_balance',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
    ];

    protected $casts = [
        'payment_terms' => 'integer',
        'status' => 'string',
        'credit_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
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

    public function purchasePayments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function supplierBalance(): HasOne
    {
        return $this->hasOne(SupplierBalance::class);
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

    // Payment-related methods
    public function getTotalOutstandingAmount(): float
    {
        return $this->purchaseOrders()
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->sum('outstanding_amount');
    }

    public function getTotalPaidAmount(): float
    {
        return $this->purchasePayments()
            ->where('status', 'completed')
            ->sum('amount');
    }

    public function getFormattedCreditLimitAttribute(): string
    {
        return 'Rp ' . number_format($this->credit_limit, 0, ',', '.');
    }

    public function getFormattedCurrentBalanceAttribute(): string
    {
        return 'Rp ' . number_format($this->current_balance, 0, ',', '.');
    }

    public function updateBalance(): void
    {
        // Get or create supplier balance record
        $balance = $this->supplierBalance()->firstOrCreate(['supplier_id' => $this->id]);

        // Update the balance
        $balance->updateBalance();

        // Update current_balance field in suppliers table
        $this->update(['current_balance' => $balance->total_outstanding]);
    }

    public function canMakeNewPurchase(float $amount): bool
    {
        if ($this->credit_limit <= 0) {
            return true; // No credit limit set
        }

        return ($this->current_balance + $amount) <= $this->credit_limit;
    }

    public function getDueDateForNewOrder(): \Carbon\Carbon
    {
        return now()->addDays($this->payment_terms);
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::created(function ($supplier) {
            // Create supplier balance record when supplier is created
            $supplier->supplierBalance()->create(['supplier_id' => $supplier->id]);
        });
    }
}
