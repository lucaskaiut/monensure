<?php

namespace App\Modules\Payout\Http\Resources;

use App\Modules\Payout\Models\PayoutOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayoutOrder
 */
class PayoutOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'method' => $this->method->value,
            'source' => $this->recurrence_id !== null ? 'recurrence' : 'manual',
            'method_label' => $this->method->label(),
            'status' => $this->status->value,
            'value' => $this->value,
            'paid_value' => $this->paid_value,
            'payment_code' => $this->payment_code,
            'pix_key_type' => $this->pix_key_type?->value,
            'beneficiary_name' => $this->beneficiary_name,
            'beneficiary_document' => $this->beneficiary_document,
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'authorized_at' => $this->authorized_at?->toIso8601String(),
            'settled_at' => $this->settled_at?->toIso8601String(),
            'external_id' => $this->external_id,
            'external_status' => $this->external_status,
            'receipt_url' => $this->receipt_url,
            'failure_reason' => $this->failure_reason,
            'payable' => $this->whenLoaded('payable', fn () => $this->payable === null ? null : [
                'id' => $this->payable->uuid,
                'description' => $this->payable->description,
                'value' => $this->payable->value,
                'due_date' => $this->payable->due_date?->toDateString(),
                'status' => $this->payable->status?->value,
                'supplier' => $this->payable->relationLoaded('supplier') && $this->payable->supplier
                    ? ['id' => $this->payable->supplier->uuid, 'name' => $this->payable->supplier->name]
                    : null,
            ]),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($event) => [
                'type' => $event->type,
                'message' => $event->message,
                'created_at' => $event->created_at?->toIso8601String(),
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
