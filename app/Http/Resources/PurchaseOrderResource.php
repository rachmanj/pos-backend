<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
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
            'po_number' => $this->po_number,
            'status' => $this->status,
            'status_badge' => $this->status_badge,
            'order_date' => $this->order_date?->format('Y-m-d'),
            'expected_delivery_date' => $this->expected_delivery_date?->format('Y-m-d'),
            'approved_date' => $this->approved_date?->format('Y-m-d'),
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'formatted_subtotal' => $this->formatted_subtotal,
            'formatted_tax' => $this->formatted_tax,
            'formatted_total' => $this->formatted_total,
            'notes' => $this->notes,
            'terms_conditions' => $this->terms_conditions,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Relationships
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'creator' => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
                'email' => $this->creator?->email,
            ],
            'approver' => $this->when($this->approver, [
                'id' => $this->approver?->id,
                'name' => $this->approver?->name,
                'email' => $this->approver?->email,
            ]),

            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'receipts' => PurchaseReceiptResource::collection($this->whenLoaded('receipts')),

            // Computed properties
            'can_be_edited' => $this->canBeEdited(),
            'can_be_approved' => $this->canBeApproved(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'is_fully_received' => $this->isFullyReceived(),
            'items_count' => $this->whenCounted('items'),
            'receipts_count' => $this->whenCounted('receipts'),
        ];
    }
}
