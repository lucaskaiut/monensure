<?php

namespace Database\Factories;

use App\Modules\Financial\Models\Payable;
use App\Modules\Payout\Enums\PayoutMethod;
use App\Modules\Payout\Enums\PayoutStatus;
use App\Modules\Payout\Models\PayoutOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayoutOrder>
 */
class PayoutOrderFactory extends Factory
{
    protected $model = PayoutOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'method' => PayoutMethod::Boleto,
            'status' => PayoutStatus::Scheduled,
            'value' => 150,
            'payment_code' => str_repeat('1', 47),
            'beneficiary_name' => 'Fornecedor LTDA',
            'beneficiary_document' => '12345678000199',
            'preview' => ['value' => 150],
            'scheduled_for' => now()->addDay(),
        ];
    }

    public function forPayable(Payable $payable): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $payable->tenant_id,
            'payable_id' => $payable->getKey(),
            'active_lock' => $payable->getKey(),
        ]);
    }

    /**
     * Ordem já enviada ao Asaas, aguardando autorização/confirmação.
     */
    public function submitted(string $externalId = 'bill-1'): static
    {
        return $this->state(fn (): array => [
            'status' => PayoutStatus::Processing,
            'scheduled_for' => now()->subMinute(),
            'submitted_at' => now()->subSeconds(5),
            'paid_value' => 150,
            'external_id' => $externalId,
            'external_status' => 'PENDING',
        ]);
    }
}
