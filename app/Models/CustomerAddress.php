<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'type',
        'label',
        'address_line_1',
        'address_line_2',
        'city',
        'state_province',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'is_primary',
        'is_active',
        'delivery_notes',
        'business_hours',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'business_hours' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
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

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBilling($query)
    {
        return $query->where('type', 'billing');
    }

    public function scopeShipping($query)
    {
        return $query->where('type', 'shipping');
    }

    public function scopeByCity($query, string $city)
    {
        return $query->where('city', 'like', "%{$city}%");
    }

    public function scopeByState($query, string $state)
    {
        return $query->where('state_province', 'like', "%{$state}%");
    }

    // Accessors
    public function getFullAddressAttribute(): string
    {
        $address = $this->address_line_1;

        if ($this->address_line_2) {
            $address .= "\n" . $this->address_line_2;
        }

        $address .= "\n" . $this->city;

        if ($this->state_province) {
            $address .= ", " . $this->state_province;
        }

        if ($this->postal_code) {
            $address .= " " . $this->postal_code;
        }

        $address .= "\n" . $this->country;

        return $address;
    }

    public function getDisplayLabelAttribute(): string
    {
        if ($this->label) {
            return $this->label;
        }

        $label = ucfirst($this->type) . " Address";

        if ($this->is_primary) {
            $label .= " (Primary)";
        }

        return $label;
    }

    public function getFormattedAddressAttribute(): string
    {
        $parts = [];

        $parts[] = $this->address_line_1;

        if ($this->address_line_2) {
            $parts[] = $this->address_line_2;
        }

        $cityState = $this->city;
        if ($this->state_province) {
            $cityState .= ", " . $this->state_province;
        }
        if ($this->postal_code) {
            $cityState .= " " . $this->postal_code;
        }
        $parts[] = $cityState;

        $parts[] = $this->country;

        return implode(", ", $parts);
    }

    public function getCoordinatesAttribute(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return [
                'lat' => (float) $this->latitude,
                'lng' => (float) $this->longitude
            ];
        }

        return null;
    }

    // Business Logic Methods
    public function makePrimary(): bool
    {
        // Remove primary status from other addresses of the same customer and type
        static::where('customer_id', $this->customer_id)
            ->where('type', $this->type)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        // Set this address as primary
        return $this->update(['is_primary' => true]);
    }

    public function isWithinDeliveryRadius(float $centerLat, float $centerLng, float $radiusKm): bool
    {
        if (!$this->latitude || !$this->longitude) {
            return false;
        }

        $distance = $this->calculateDistance($centerLat, $centerLng);
        return $distance <= $radiusKm;
    }

    public function calculateDistance(float $lat, float $lng): float
    {
        if (!$this->latitude || !$this->longitude) {
            return 0;
        }

        $earthRadius = 6371; // Earth's radius in kilometers

        $latDelta = deg2rad($lat - $this->latitude);
        $lngDelta = deg2rad($lng - $this->longitude);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($this->latitude)) * cos(deg2rad($lat)) *
            sin($lngDelta / 2) * sin($lngDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function isBusinessOpen(?string $dayOfWeek = null, ?string $time = null): bool
    {
        if (!$this->business_hours) {
            return true; // Assume always open if no hours specified
        }

        $dayOfWeek = $dayOfWeek ?: strtolower(date('l'));
        $time = $time ?: date('H:i');

        if (!isset($this->business_hours[$dayOfWeek])) {
            return false;
        }

        $hours = $this->business_hours[$dayOfWeek];

        if ($hours['closed'] ?? false) {
            return false;
        }

        $openTime = $hours['open'] ?? '00:00';
        $closeTime = $hours['close'] ?? '23:59';

        return $time >= $openTime && $time <= $closeTime;
    }

    public function getDeliveryInstructions(): string
    {
        $instructions = [];

        if ($this->delivery_notes) {
            $instructions[] = $this->delivery_notes;
        }

        if ($this->business_hours) {
            $instructions[] = "Business Hours: " . $this->getBusinessHoursText();
        }

        return implode("\n", $instructions);
    }

    private function getBusinessHoursText(): string
    {
        if (!$this->business_hours) {
            return "Not specified";
        }

        $hoursText = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            if (isset($this->business_hours[$day])) {
                $hours = $this->business_hours[$day];
                if ($hours['closed'] ?? false) {
                    $hoursText[] = ucfirst($day) . ": Closed";
                } else {
                    $open = $hours['open'] ?? '00:00';
                    $close = $hours['close'] ?? '23:59';
                    $hoursText[] = ucfirst($day) . ": {$open} - {$close}";
                }
            }
        }

        return implode(", ", $hoursText);
    }
}
