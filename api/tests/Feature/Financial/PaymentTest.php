<?php

namespace Tests\Feature\Financial;

use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Models\PayableInstallmentGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_pay_records_payment_and_supports_underpayment(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($child));

        $payable = Payable::factory()->forTenant($child)->create(['value' => 100]);

        $this->postJson("/api/financial/payables/{$payable->uuid}/pay", [
            'paid_at' => '2026-09-02',
            'paid_value' => 95,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pago')
            ->assertJsonPath('data.paid_value', '95.00')
            ->assertJsonPath('data.payments.0.paid_value', '95.00')
            ->assertJsonPath('data.payments.0.difference', '-5.00');

        $this->assertDatabaseHas('payable_payments', [
            'payable_id' => $payable->getKey(),
            'paid_value' => 95,
        ]);
    }

    public function test_pay_records_overpayment_history(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($child));

        $payable = Payable::factory()->forTenant($child)->create(['value' => 100]);

        $this->postJson("/api/financial/payables/{$payable->uuid}/pay", [
            'paid_at' => '2026-09-02',
            'paid_value' => 105,
        ])->assertOk()->assertJsonPath('data.payments.0.difference', '5.00');
    }

    public function test_cannot_pay_cancelled_payable(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($child));

        $payable = Payable::factory()->forTenant($child)->cancelled()->create();

        $this->postJson("/api/financial/payables/{$payable->uuid}/pay", [
            'paid_at' => '2026-09-02',
            'paid_value' => 100,
        ])->assertUnprocessable();
    }

    public function test_cancel_batch_cancels_sibling_installments(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($child));

        $group = PayableInstallmentGroup::factory()->forTenant($child)->create();

        for ($i = 1; $i <= 3; $i++) {
            Payable::factory()->forTenant($child)->create([
                'installment_group_uuid' => $group->uuid,
                'installment_number' => $i,
                'installment_total' => 3,
            ]);
        }

        $first = Payable::query()
            ->where('installment_group_uuid', $group->uuid)
            ->where('installment_number', 1)
            ->first();

        $this->postJson("/api/financial/payables/{$first->uuid}/cancel", ['scope' => 'all'])
            ->assertOk()
            ->assertJsonPath('data.cancelled', 3);

        $this->assertSame(0, Payable::query()->where('status', 'pendente')->count());
    }
}
