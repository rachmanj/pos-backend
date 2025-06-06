<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
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
            'purchase_order_id' => $this->purchase_order_id,
            'product_id' => $this->product_id,
            'unit_id' => $this->unit_id,
            'quantity_ordered' => $this->quantity_ordered,
            'quantity_received' => $this->quantity_received,
            'remaining_quantity' => $this->remaining_quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'formatted_unit_price' => $this->formatted_unit_price,
            'formatted_total_price' => $this->formatted_total_price,
            'receipt_percentage' => $this->receipt_percentage,
            'is_fully_received' => $this->is_fully_received,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Relationships
            'product' => [
                'id' => $this->product?->id,
                'name' => $this->product?->name,
                'sku' => $this->product?->sku,
                'image' => $this->product?->image,
            ],
            'unit' => [
                'id' => $this->unit?->id,
                'name' => $this->unit?->name,
                'symbol' => $this->unit?->symbol,
            ],
        ];
    }
}
