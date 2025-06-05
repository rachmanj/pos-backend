<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
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
            'symbol' => $this->symbol,
            'conversion_factor' => $this->conversion_factor,
            'display_name' => $this->display_name,
            'is_base_unit' => $this->isBaseUnit(),

            // Relationships
            'base_unit' => new UnitResource($this->whenLoaded('baseUnit')),
            'derived_units' => UnitResource::collection($this->whenLoaded('derivedUnits')),
            'products_count' => $this->whenLoaded('products', function () {
                return $this->products->count();
            }),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
