<?php

namespace App\Modules\Financial\Http\Resources;

use App\Modules\Financial\Models\FinancialRecurrence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinancialRecurrence
 */
class FinancialRecurrenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'description' => $this->description,
            'default_value' => $this->default_value,
            'due_day' => $this->due_day,
            'frequency' => $this->frequency?->value,
            'active' => (bool) $this->active,
            'generate_automatically' => (bool) $this->generate_automatically,
            'last_generated_at' => $this->last_generated_at?->toDateString(),
            'supplier' => $this->whenLoaded('supplier', fn () => SupplierResource::make($this->supplier)),
            'category' => $this->whenLoaded('category', fn () => CategoryResource::make($this->category)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
