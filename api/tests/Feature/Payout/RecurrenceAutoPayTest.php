<?php

namespace Tests\Feature\Payout;

use App\Modules\Financial\Models\FinancialRecurrence;
use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Models\Supplier;
use App\Modules\Payout\Enums\PayoutMethod;
use App\Modules\Payout\Enums\PayoutStatus;
use App\Modules\Payout\Models\PayoutOrder;
use App\Modules\Tenant\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class RecurrenceAutoPayTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private Tenant $tenant;

    private Supplier $supplier;

    private FinancialRecurrence $recurrence;

    protected function setUp(): void
    {
        parent::setUp();

        // 05/11/2026 08:00 em Brasília.
        $this->travelTo(CarbonImmutable::parse('2026-11-05 08:00', 'America/Sao_Paulo'));

        config([
            'asaas.base_url' => 'https://api-sandbox.asaas.com/v3',
            'asaas.payouts.enabled' => true,
            'asaas.payouts.api_key' => 'test-payout-key',
            'asaas.payouts.max_per_payment' => 1000,
            'asaas.payouts.max_per_day' => 2000,
            'asaas.payouts.authorization_token' => 'auth-token',
        ]);

        [, $this->tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($this->tenant));

        $this->supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->getKey(),
            'pix_key' => 'contador@example.com',
            'pix_key_type' => 'EMAIL',
        ]);

        $this->recurrence = FinancialRecurrence::factory()->forTenant($this->tenant)->create([
            'description' => 'Honorários contábeis',
            'default_value' => 300,
            'supplier_id' => $this->supplier->getKey(),
            'generate_automatically' => false,
        ]);
    }

    public function test_supplier_stores_normalized_pix_key(): void
    {
        $this->postJson('/api/financial/suppliers', [
            'name' => 'Diarista',
            'pix_key' => '529.982.247-25',
            'pix_key_type' => 'CPF',
        ])
            ->assertCreated()
            ->assertJsonPath('data.pix_key', '52998224725')
            ->assertJsonPath('data.pix_key_type', 'CPF');

        $this->postJson('/api/financial/suppliers', ['name' => 'Sem tipo', 'pix_key' => 'x@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pix_key_type');
    }

    public function test_enable_requires_password(): void
    {
        $this->putJson($this->url(), ['time' => '09:00', 'max_value' => 400, 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertFalse($this->recurrence->refresh()->auto_pay_enabled);
    }

    public function test_enable_uses_supplier_key_as_snapshot_and_schedules_within_three_days(): void
    {
        $soon = $this->payable('2026-11-08');      // dentro da janela (3 dias)
        $later = $this->payable('2026-11-20');     // fora da janela
        $overdue = $this->payable('2026-11-01');   // vencida: não paga automaticamente

        $this->putJson($this->url(), ['time' => '09:30', 'max_value' => 400, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.auto_pay.enabled', true)
            ->assertJsonPath('data.auto_pay.pix_key', 'contador@example.com')
            ->assertJsonPath('data.auto_pay.pix_key_type', 'EMAIL')
            ->assertJsonPath('data.auto_pay.max_value', '400.00');

        $order = PayoutOrder::query()->where('payable_id', $soon->getKey())->firstOrFail();
        $this->assertSame(PayoutStatus::Scheduled, $order->status);
        $this->assertSame(PayoutMethod::PixKey, $order->method);
        $this->assertSame('contador@example.com', $order->payment_code);
        $this->assertSame($this->recurrence->getKey(), $order->recurrence_id);
        $this->assertSame('2026-11-08 09:30', $order->scheduled_for->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i'));

        $this->assertDatabaseMissing('payout_orders', ['payable_id' => $later->getKey()]);
        $this->assertDatabaseMissing('payout_orders', ['payable_id' => $overdue->getKey()]);

        // Mudar a chave do fornecedor não altera a chave aprovada.
        $this->putJson("/api/financial/suppliers/{$this->supplier->uuid}", ['pix_key' => 'outra@example.com', 'pix_key_type' => 'EMAIL'])
            ->assertOk();
        $this->assertSame('contador@example.com', $this->recurrence->refresh()->auto_pay_pix_key);
    }

    public function test_explicit_key_overrides_supplier_key(): void
    {
        $this->putJson($this->url(), [
            'pix_key' => '11.222.333/0001-81',
            'pix_key_type' => 'CNPJ',
            'time' => '09:00',
            'max_value' => 400,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.auto_pay.pix_key', '11222333000181')
            ->assertJsonPath('data.auto_pay.pix_key_type', 'CNPJ');
    }

    public function test_enable_without_any_key_fails(): void
    {
        $this->supplier->forceFill(['pix_key' => null, 'pix_key_type' => null])->save();

        $this->putJson($this->url(), ['time' => '09:00', 'max_value' => 400, 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Informe a chave PIX ou cadastre uma chave no fornecedor da recorrência.');
    }

    public function test_max_value_cannot_exceed_limit_per_payment(): void
    {
        $this->putJson($this->url(), ['time' => '09:00', 'max_value' => 5000, 'password' => 'password'])
            ->assertUnprocessable();
    }

    public function test_command_schedules_when_payable_enters_window_and_never_recreates_after_cancel(): void
    {
        $this->enable();
        $payable = $this->payable('2026-11-10');

        $this->artisan('payouts:schedule-recurring')->assertSuccessful();
        $this->assertDatabaseMissing('payout_orders', ['payable_id' => $payable->getKey()]);

        $this->travelTo(CarbonImmutable::parse('2026-11-07 06:00', 'America/Sao_Paulo'));
        $this->artisan('payouts:schedule-recurring')->assertSuccessful();

        $order = PayoutOrder::query()->where('payable_id', $payable->getKey())->firstOrFail();
        $this->assertSame(PayoutStatus::Scheduled, $order->status);

        // Rodar de novo não duplica.
        $this->artisan('payouts:schedule-recurring')->assertSuccessful();
        $this->assertSame(1, PayoutOrder::query()->where('payable_id', $payable->getKey())->count());

        // Cancelado pelo usuário, o mês não é reagendado.
        $this->postJson("/api/financial/payouts/{$order->uuid}/cancel")->assertOk();
        $this->artisan('payouts:schedule-recurring')->assertSuccessful();
        $this->assertSame(1, PayoutOrder::query()->where('payable_id', $payable->getKey())->count());
    }

    public function test_value_above_approved_max_creates_failed_order_and_keeps_payable_free(): void
    {
        $this->enable(maxValue: 250);
        $payable = $this->payable('2026-11-06', 300);

        $this->artisan('payouts:schedule-recurring')->assertSuccessful();

        $order = PayoutOrder::query()->where('payable_id', $payable->getKey())->firstOrFail();
        $this->assertSame(PayoutStatus::Failed, $order->status);
        $this->assertNull($order->active_lock);
        $this->assertStringContainsString('máximo aprovado', (string) $order->failure_reason);

        // Conta continua disponível para baixa manual.
        $this->postJson("/api/financial/payables/{$payable->uuid}/pay", ['paid_at' => '2026-11-06', 'paid_value' => 300])
            ->assertOk();
    }

    public function test_disabling_or_deactivating_cancels_scheduled_orders(): void
    {
        $this->enable();
        $payable = $this->payable('2026-11-07');
        $this->artisan('payouts:schedule-recurring')->assertSuccessful();

        $this->deleteJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.auto_pay.enabled', false);

        $this->assertSame(PayoutStatus::Cancelled, PayoutOrder::query()->where('payable_id', $payable->getKey())->firstOrFail()->status);

        // Reativado e depois a recorrência é desativada.
        $other = $this->payable('2026-11-06');
        $this->enable();
        $this->assertSame(PayoutStatus::Scheduled, PayoutOrder::query()->where('payable_id', $other->getKey())->firstOrFail()->status);

        $this->putJson("/api/financial/recurrences/{$this->recurrence->uuid}", ['active' => false])->assertOk();

        $this->assertSame(PayoutStatus::Cancelled, PayoutOrder::query()->where('payable_id', $other->getKey())->firstOrFail()->status);
        $this->assertFalse($this->recurrence->refresh()->auto_pay_enabled);
    }

    public function test_changing_configuration_updates_scheduled_orders(): void
    {
        $this->enable();
        $payable = $this->payable('2026-11-07');
        $this->artisan('payouts:schedule-recurring')->assertSuccessful();

        $this->putJson($this->url(), [
            'pix_key' => 'novo@example.com',
            'pix_key_type' => 'EMAIL',
            'time' => '14:00',
            'max_value' => 400,
            'password' => 'password',
        ])->assertOk();

        $order = PayoutOrder::query()->where('payable_id', $payable->getKey())->firstOrFail();
        $this->assertSame('novo@example.com', $order->payment_code);
        $this->assertSame('14:00', $order->scheduled_for->setTimezone('America/Sao_Paulo')->format('H:i'));
    }

    public function test_payable_value_cannot_change_while_payment_is_scheduled(): void
    {
        $this->enable();
        $payable = $this->payable('2026-11-07');
        $this->artisan('payouts:schedule-recurring')->assertSuccessful();

        $this->putJson("/api/financial/payables/{$payable->uuid}", ['value' => 350])->assertUnprocessable();
        $this->putJson("/api/financial/payables/{$payable->uuid}", ['description' => 'Honorários (nov)'])->assertOk();
    }

    public function test_recurring_order_is_sent_as_pix_transfer_on_due_date(): void
    {
        $this->enable();
        $payable = $this->payable('2026-11-06');
        $this->artisan('payouts:schedule-recurring')->assertSuccessful();

        Http::fake([
            '*/finance/balance' => Http::response(['balance' => 5000]),
            '*/transfers' => Http::response(['id' => 'tr-1', 'status' => 'PENDING', 'value' => 300]),
        ]);

        // Antes do horário: nada é enviado.
        $this->artisan('payouts:dispatch-due')->assertSuccessful();
        Http::assertNothingSent();

        $this->travelTo(CarbonImmutable::parse('2026-11-06 09:01', 'America/Sao_Paulo'));
        $this->artisan('payouts:dispatch-due')->assertSuccessful();

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/transfers')
            && $request['pixAddressKey'] === 'contador@example.com'
            && $request['pixAddressKeyType'] === 'EMAIL'
            && (float) $request['value'] === 300.0);

        $this->assertSame(PayoutStatus::Processing, PayoutOrder::query()->where('payable_id', $payable->getKey())->firstOrFail()->status);
    }

    private function enable(float $maxValue = 400): void
    {
        $this->putJson($this->url(), ['time' => '09:00', 'max_value' => $maxValue, 'password' => 'password'])->assertOk();
    }

    private function payable(string $due, float $value = 300): Payable
    {
        return Payable::factory()->forTenant($this->tenant)->create([
            'recurrence_id' => $this->recurrence->getKey(),
            'supplier_id' => $this->supplier->getKey(),
            'description' => 'Honorários contábeis',
            'value' => $value,
            'due_date' => $due,
        ]);
    }

    private function url(): string
    {
        return "/api/financial/recurrences/{$this->recurrence->uuid}/auto-pay";
    }
}
