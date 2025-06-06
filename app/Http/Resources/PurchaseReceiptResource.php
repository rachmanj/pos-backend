<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReceiptResource extends JsonResource
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
            'receipt_number' => $this->receipt_number,
            'purchase_order_id' => $this->purchase_order_id,
            'received_by' => $this->received_by,
            'receipt_date' => $this->receipt_date?->format('Y-m-d'),
            'formatted_receipt_date' => $this->formatted_receipt_date,
            'status' => $this->status,
            'status_badge' => $this->status_badge,
            'notes' => $this->notes,
            'quality_check_notes' => $this->quality_check_notes,
            'stock_updated' => $this->stock_updated,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Relationships
            'purchase_order' => [
                'id' => $this->purchaseOrder?->id,
                'po_number' => $this->purchaseOrder?->po_number,
                'status' => $this->purchaseOrder?->status,
            ],
            'receiver' => [
                'id' => $this->receiver?->id,
                'name' => $this->receiver?->name,
                'email' => $this->receiver?->email,
            ],
            'items' => PurchaseReceiptItemResource::collection($this->whenLoaded('items')),

            // Computed properties
            'can_be_edited' => $this->canBeEdited(),
            'can_update_stock' => $this->canUpdateStock(),
            'is_complete' => $this->isComplete(),
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
