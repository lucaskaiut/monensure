<?php

namespace App\Modules\Financial\Http\Resources;

use App\Modules\Financial\Models\Payable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payable
 */
class PayableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'description' => $this->description,
            'value' => $this->value,
            'due_date' => $this->due_date?->toDateString(),
            'issue_date' => $this->issue_date?->toDateString(),
            'status' => $this->status?->value,
            'is_installment' => $this->isInstallment(),
            'installment_group_uuid' => $this->installment_group_uuid,
            'installment_number' => $this->installment_number,
            'installment_total' => $this->installment_total,
            'installment_label' => $this->installmentLabel(),
            'paid_at' => $this->paid_at?->toDateString(),
            'paid_value' => $this->paid_value,
            'supplier' => $this->whenLoaded('supplier', fn () => SupplierResource::make($this->supplier)),
            'category' => $this->whenLoaded('category', fn () => CategoryResource::make($this->category)),
            'payments' => PayablePaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function installmentLabel(): ?string
    {
        if (! $this->isInstallment()) {
            return null;
        }

        return "{$this->installment_number}/{$this->installment_total}";
    }
}
