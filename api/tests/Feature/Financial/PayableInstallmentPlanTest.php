<?php

namespace Tests\Feature\Financial;

use App\Modules\Financial\Models\Payable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PayableInstallmentPlanTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_installment_plan_generates_correct_numbering_and_due_dates(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($child));

        $response = $this->postJson('/api/financial/payables/installments', [
            'description' => 'Financiamento carro',
            'value' => 3495,
            'first_due_date' => '2026-09-05',
            'first_installment_number' => 12,
            'total_installments' => 37,
        ])->assertCreated();

        $response
            ->assertJsonPath('data.first_installment_number', 12)
            ->assertJsonPath('data.total_installments', 37)
            ->assertJsonCount(37, 'data.payables');

        $payables = $response->json('data.payables');

        $this->assertSame(12, $payables[0]['installment_number']);
        $this->assertSame(48, $payables[0]['installment_total']);
        $this->assertSame('12/48', $payables[0]['installment_label']);
        $this->assertSame('2026-09-05', $payables[0]['due_date']);

        $this->assertSame(48, $payables[36]['installment_number']);
        $this->assertSame('48/48', $payables[36]['installment_label']);
        $this->assertSame('2026-10-05', $payables[1]['due_date']);

        $this->assertSame(37, Payable::query()->count());
    }

    public function test_installment_plan_requires_minimum_installments(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();

        Sanctum::actingAs($this->createAdmin($child));

        $this->postJson('/api/financial/payables/installments', [
            'description' => 'Financiamento',
            'value' => 100,
            'first_due_date' => '2026-09-05',
            'first_installment_number' => 0,
            'total_installments' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors(['first_installment_number', 'total_installments']);
    }
}
