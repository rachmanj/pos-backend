<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'movement_type' => $this->movement_type,
            'quantity' => $this->quantity,
            'signed_quantity' => $this->signed_quantity,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->quantity * $this->unit_cost,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'notes' => $this->notes,

            // Relationships
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'sku' => $this->product->sku,
                    'barcode' => $this->product->barcode,
                ];
            }),

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),

            // Movement display information
            'movement_display' => $this->getMovementDisplay(),
            'reference_display' => $this->getReferenceDisplay(),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get formatted movement display
     */
    private function getMovementDisplay(): string
    {
        $prefix = $this->movement_type === 'in' ? '+' : '-';
        return "{$prefix}{$this->quantity}";
    }

    /**
     * Get formatted reference display
     */
    private function getReferenceDisplay(): string
    {
        if (!$this->reference_type) {
            return 'Manual';
        }

        $display = ucfirst(str_replace('_', ' ', $this->reference_type));

        if ($this->reference_id) {
            $display .= " #{$this->reference_id}";
        }

        return $display;
    }
}
