<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'cost_price' => $this->cost_price,
            'selling_price' => $this->selling_price,
            'min_stock_level' => $this->min_stock_level,
            'max_stock_level' => $this->max_stock_level,
            'tax_rate' => $this->tax_rate,
            'image' => $this->image,
            'status' => $this->status,

            // Computed fields
            'profit_margin' => $this->profit_margin,
            'profit' => $this->profit,
            'formatted_price' => $this->formatted_price,
            'formatted_cost' => $this->formatted_cost,

            // Stock information
            'current_stock' => $this->whenLoaded('stock', function () {
                return $this->stock->current_stock ?? 0;
            }),
            'reserved_stock' => $this->whenLoaded('stock', function () {
                return $this->stock->reserved_stock ?? 0;
            }),
            'available_stock' => $this->whenLoaded('stock', function () {
                return $this->stock->available_stock ?? 0;
            }),
            'stock_status' => $this->whenLoaded('stock', function () {
                return $this->stock->stock_status ?? 'normal';
            }),
            'stock_value' => $this->whenLoaded('stock', function () {
                return $this->stock->stock_value ?? 0;
            }),
            'is_low_stock' => $this->isLowStock(),
            'is_out_of_stock' => $this->isOutOfStock(),

            // Relationships
            'category' => new CategoryResource($this->whenLoaded('category')),
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'stock_movements' => $this->whenLoaded('stockMovements', function () {
                return $this->stockMovements->map(function ($movement) {
                    return [
                        'id' => $movement->id,
                        'movement_type' => $movement->movement_type,
                        'quantity' => $movement->quantity,
                        'signed_quantity' => $movement->signed_quantity,
                        'unit_cost' => $movement->unit_cost,
                        'reference_type' => $movement->reference_type,
                        'reference_id' => $movement->reference_id,
                        'notes' => $movement->notes,
                        'created_at' => $movement->created_at,
                        'user' => $movement->whenLoaded('user', [
                            'id' => $movement->user->id,
                            'name' => $movement->user->name,
                        ]),
                    ];
                });
            }),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
