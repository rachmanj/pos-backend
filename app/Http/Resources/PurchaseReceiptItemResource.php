<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReceiptItemResource extends JsonResource
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
            'purchase_receipt_id' => $this->purchase_receipt_id,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'product_id' => $this->product_id,
            'unit_id' => $this->unit_id,
            'quantity_received' => $this->quantity_received,
            'quantity_accepted' => $this->quantity_accepted,
            'quantity_rejected' => $this->quantity_rejected,
            'quality_status' => $this->quality_status,
            'quality_status_badge' => $this->quality_status_badge,
            'acceptance_rate' => $this->acceptance_rate,
            'rejection_rate' => $this->rejection_rate,
            'quality_notes' => $this->quality_notes,
            'rejection_reason' => $this->rejection_reason,
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
            'purchase_order_item' => [
                'id' => $this->purchaseOrderItem?->id,
                'quantity_ordered' => $this->purchaseOrderItem?->quantity_ordered,
                'unit_price' => $this->purchaseOrderItem?->unit_price,
            ],

            // Computed properties
            'can_be_edited' => $this->canBeEdited(),
        ];
    }
}
