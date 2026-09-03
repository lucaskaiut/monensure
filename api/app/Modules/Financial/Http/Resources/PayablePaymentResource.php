<?php

namespace App\Modules\Financial\Http\Resources;

use App\Modules\Financial\Models\PayablePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayablePayment
 */
class PayablePaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'paid_at' => $this->paid_at?->toDateString(),
            'paid_value' => $this->paid_value,
            'difference' => $this->difference,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
