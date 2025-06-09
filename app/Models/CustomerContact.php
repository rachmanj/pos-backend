<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'name',
        'position',
        'department',
        'phone',
        'mobile',
        'email',
        'whatsapp',
        'is_primary',
        'is_decision_maker',
        'receives_invoices',
        'receives_marketing',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_decision_maker' => 'boolean',
        'receives_invoices' => 'boolean',
        'receives_marketing' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeDecisionMakers($query)
    {
        return $query->where('is_decision_maker', true);
    }

    public function scopeInvoiceRecipients($query)
    {
        return $query->where('receives_invoices', true);
    }

    public function scopeMarketingRecipients($query)
    {
        return $query->where('receives_marketing', true);
    }

    // Accessors
    public function getFullContactInfoAttribute(): string
    {
        $info = [];

        if ($this->phone) {
            $info[] = "Phone: {$this->phone}";
        }

        if ($this->mobile) {
            $info[] = "Mobile: {$this->mobile}";
        }

        if ($this->email) {
            $info[] = "Email: {$this->email}";
        }

        if ($this->whatsapp) {
            $info[] = "WhatsApp: {$this->whatsapp}";
        }

        return implode(' | ', $info);
    }

    public function getDisplayNameAttribute(): string
    {
        $name = $this->name;

        if ($this->position) {
            $name .= " ({$this->position})";
        }

        if ($this->is_primary) {
            $name .= " [Primary]";
        }

        return $name;
    }

    // Business Logic Methods
    public function makePrimary(): bool
    {
        // Remove primary status from other contacts of the same customer
        static::where('customer_id', $this->customer_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        // Set this contact as primary
        return $this->update(['is_primary' => true]);
    }

    public function canReceiveInvoices(): bool
    {
        return $this->is_active && $this->receives_invoices && !empty($this->email);
    }

    public function canReceiveMarketing(): bool
    {
        return $this->is_active && $this->receives_marketing;
    }

    public function getPreferredContactMethod(): ?string
    {
        if ($this->mobile) return 'mobile';
        if ($this->phone) return 'phone';
        if ($this->email) return 'email';
        if ($this->whatsapp) return 'whatsapp';

        return null;
    }
}
