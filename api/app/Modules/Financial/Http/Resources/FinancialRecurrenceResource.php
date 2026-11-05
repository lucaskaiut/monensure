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
            'auto_pay' => [
                'enabled' => (bool) $this->auto_pay_enabled,
                'pix_key' => $this->auto_pay_pix_key,
                'pix_key_type' => $this->auto_pay_pix_key_type,
                'time' => $this->auto_pay_time,
                'max_value' => $this->auto_pay_max_value,
                'approved_at' => $this->auto_pay_approved_at?->toIso8601String(),
                'approved_by' => $this->whenLoaded('autoPayApprover', fn () => $this->autoPayApprover?->name),
            ],
            'supplier' => $this->whenLoaded('supplier', fn () => SupplierResource::make($this->supplier)),
            'category' => $this->whenLoaded('category', fn () => CategoryResource::make($this->category)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
