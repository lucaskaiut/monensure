<?php

namespace Tests\Feature\Payout;

use App\Modules\Financial\Models\Payable;
use App\Modules\Payout\Enums\PayoutMethod;
use App\Modules\Payout\Enums\PayoutStatus;
use App\Modules\Payout\Models\PayoutOrder;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PayoutTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private const BOLETO = '23793381286000000000300000000401800000000015000';

    private const AUTH_TOKEN = 'auth-token-123';

    private const EVENTS_TOKEN = 'events-token-123';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'asaas.base_url' => 'https://api-sandbox.asaas.com/v3',
            'asaas.payouts.enabled' => true,
            'asaas.payouts.api_key' => 'test-payout-key',
            'asaas.payouts.max_per_payment' => 1000,
            'asaas.payouts.max_per_day' => 2000,
            'asaas.payouts.authorization_token' => self::AUTH_TOKEN,
            'asaas.payouts.webhook_token' => self::EVENTS_TOKEN,
        ]);

        [, $this->tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($this->tenant));
    }

    public function test_preview_returns_beneficiary_from_bill_simulation(): void
    {
        $this->fakeAsaas();

        $this->postJson('/api/financial/payouts/preview', [
            'method' => 'boleto',
            'payment_code' => '23793.38128 60000.000003 00000.000401 8 00000000015000',
        ])
            ->assertOk()
            ->assertJsonPath('data.beneficiary_name', 'Fornecedor LTDA')
            ->assertJsonPath('data.beneficiary_document', '12345678000199')
            ->assertJsonPath('data.value', 150)
            ->assertJsonPath('data.payment_code', self::BOLETO);
    }

    public function test_schedule_requires_correct_password(): void
    {
        $this->fakeAsaas();
        $payable = $this->payable();

        $this->postJson("/api/financial/payables/{$payable->uuid}/payouts", $this->boletoPayload(['password' => 'wrong']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('payout_orders', 0);
    }

    public function test_schedule_future_payment_locks_the_payable(): void
    {
        $this->fakeAsaas();
        $payable = $this->payable();

        $response = $this->postJson("/api/financial/payables/{$payable->uuid}/payouts", $this->boletoPayload([
            'scheduled_for' => now()->addDay()->toIso8601String(),
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.value', '150.00')
            ->assertJsonPath('data.beneficiary_name', 'Fornecedor LTDA');

        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/bill'));

        // Baixa manual, cancelamento e segundo agendamento ficam bloqueados.
        $this->postJson("/api/financial/payables/{$payable->uuid}/pay", ['paid_at' => '2026-11-05', 'paid_value' => 150])
            ->assertUnprocessable();
        $this->postJson("/api/financial/payables/{$payable->uuid}/cancel")->assertUnprocessable();
        $this->postJson("/api/financial/payables/{$payable->uuid}/payouts", $this->boletoPayload([
            'scheduled_for' => now()->addDays(2)->toIso8601String(),
        ]))->assertUnprocessable();

        $this->getJson("/api/financial/payables/{$payable->uuid}")
            ->assertJsonPath('data.active_payout.status', 'scheduled');

        // Cancelado o agendamento, a conta volta a aceitar baixa manual.
        $orderId = $response->json('data.id');
        $this->postJson("/api/financial/payouts/{$orderId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->postJson("/api/financial/payables/{$payable->uuid}/pay", ['paid_at' => '2026-11-05', 'paid_value' => 150])
            ->assertOk();
    }

    public function test_schedule_is_blocked_when_disabled_or_above_limits(): void
    {
        $this->fakeAsaas(billValue: 1500);
        $payable = $this->payable(1500);

        $this->postJson("/api/financial/payables/{$payable->uuid}/payouts", $this->boletoPayload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'O valor excede o limite por pagamento (R$ 1.000,00).');

        config(['asaas.payouts.enabled' => false]);

        $this->postJson("/api/financial/payables/{$payable->uuid}/payouts", $this->boletoPayload())
            ->assertUnprocessable();

        $this->assertDatabaseCount('payout_orders', 0);
    }

    public function test_daily_limit_counts_other_payments_of_the_day(): void
    {
        $this->fakeAsaas(billValue: 900);

        foreach ([1, 2] as $i) {
            $this->postJson("/api/financial/payables/{$this->payable(900)->uuid}/payouts", $this->boletoPayload([
                'scheduled_for' => now()->addHour()->toIso8601String(),
            ]))->assertCreated();
        }

        $this->postJson("/api/financial/payables/{$this->payable(900)->uuid}/payouts", $this->boletoPayload([
            'scheduled_for' => now()->addHour()->toIso8601String(),
        ]))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'O valor excede o limite diário (R$ 2.000,00; já comprometido no dia: R$ 1.800,00).']);
    }

    public function test_pay_now_submits_the_bill_to_asaas(): void
    {
        $this->fakeAsaas();
        $payable = $this->payable();

        $this->postJson("/api/financial/payables/{$payable->uuid}/payouts", $this->boletoPayload())
            ->assertCreated();

        $order = PayoutOrder::query()->firstOrFail();

        $this->assertSame(PayoutStatus::Processing, $order->status);
        $this->assertSame('bill-1', $order->external_id);
        $this->assertSame('150.00', $order->paid_value);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/bill')
            && $request['identificationField'] === self::BOLETO
            && $request['externalReference'] === $order->uuid
            // Paga hoje (Brasília), e não no vencimento do boleto.
            && $request['scheduleDate'] === now('America/Sao_Paulo')->toDateString()
            && $request->header('access_token')[0] === 'test-payout-key');

        // Ainda não houve baixa: só com a confirmação do Asaas.
        $this->assertSame('pendente', $payable->refresh()->status->value);
    }

    public function test_execution_aborts_when_bill_value_increased(): void
    {
        $payable = $this->payable();
        $order = PayoutOrder::factory()->forPayable($payable)->create(['scheduled_for' => now()->subMinute()]);

        $this->fakeAsaas(billValue: 180);

        $this->artisan('payouts:dispatch-due')->assertSuccessful();

        $order->refresh();
        $this->assertSame(PayoutStatus::Failed, $order->status);
        $this->assertStringContainsString('maior que o aprovado', (string) $order->failure_reason);
        $this->assertNull($order->active_lock);
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/bill'));
    }

    public function test_execution_aborts_when_beneficiary_changed(): void
    {
        $payable = $this->payable();
        $order = PayoutOrder::factory()->forPayable($payable)->create(['scheduled_for' => now()->subMinute()]);

        $this->fakeAsaas(beneficiaryDocument: '99999999000199');

        $this->artisan('payouts:dispatch-due')->assertSuccessful();

        $this->assertSame(PayoutStatus::Failed, $order->refresh()->status);
        $this->assertStringContainsString('beneficiário mudou', (string) $order->failure_reason);
    }

    public function test_execution_aborts_without_balance(): void
    {
        $payable = $this->payable();
        $order = PayoutOrder::factory()->forPayable($payable)->create(['scheduled_for' => now()->subMinute()]);

        $this->fakeAsaas(balance: 100);

        $this->artisan('payouts:dispatch-due')->assertSuccessful();

        $this->assertSame(PayoutStatus::Failed, $order->refresh()->status);
        $this->assertStringContainsString('Saldo insuficiente', (string) $order->failure_reason);
    }

    public function test_dispatch_only_runs_due_orders(): void
    {
        $this->fakeAsaas();

        $due = PayoutOrder::factory()->forPayable($this->payable())->create(['scheduled_for' => now()->subMinute()]);
        $future = PayoutOrder::factory()->forPayable($this->payable())->create(['scheduled_for' => now()->addHour()]);

        $this->artisan('payouts:dispatch-due')->assertSuccessful();

        $this->assertSame(PayoutStatus::Processing, $due->refresh()->status);
        $this->assertSame(PayoutStatus::Scheduled, $future->refresh()->status);
    }

    public function test_connection_failure_on_submit_requires_review_and_is_not_retried(): void
    {
        $payable = $this->payable();
        $order = PayoutOrder::factory()->forPayable($payable)->create(['scheduled_for' => now()->subMinute()]);

        $this->fakeAsaas(billFails: true);

        $this->artisan('payouts:dispatch-due')->assertSuccessful();
        $this->artisan('payouts:dispatch-due')->assertSuccessful();

        $order->refresh();
        $this->assertSame(PayoutStatus::NeedsReview, $order->status);
        $this->assertSame($payable->getKey(), $order->active_lock);
        // simulate + balance registrados uma única vez (a chamada que falha não é
        // registrada pelo fake); a segunda rodada não reenvia nada.
        Http::assertSentCount(2);

        $this->postJson("/api/financial/payouts/{$order->uuid}/resolve", ['outcome' => 'failed', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.status', 'failed');

        $this->assertNull($order->refresh()->active_lock);
    }

    public function test_authorization_webhook_approves_matching_order_once(): void
    {
        $order = PayoutOrder::factory()->forPayable($this->payable())->submitted('bill-1')->create();

        $payload = ['type' => 'BILL', 'bill' => ['object' => 'bill', 'id' => 'bill-1', 'value' => 150, 'status' => 'PENDING', 'identificationField' => str_repeat('1', 47)]];

        $this->postJson('/api/asaas/payouts/authorization', $payload, ['asaas-access-token' => self::AUTH_TOKEN])
            ->assertOk()
            ->assertExactJson(['status' => 'APPROVED']);

        $this->assertNotNull($order->refresh()->authorized_at);

        $this->postJson('/api/asaas/payouts/authorization', $payload, ['asaas-access-token' => self::AUTH_TOKEN])
            ->assertOk()
            ->assertJsonPath('status', 'REFUSED');
    }

    public function test_authorization_webhook_refuses_unknown_or_divergent_operations(): void
    {
        PayoutOrder::factory()->forPayable($this->payable())->submitted('bill-1')->create();
        $headers = ['asaas-access-token' => self::AUTH_TOKEN];

        $this->postJson('/api/asaas/payouts/authorization', [
            'type' => 'TRANSFER',
            'transfer' => ['id' => 'unknown', 'value' => 10],
        ], $headers)->assertJsonPath('status', 'REFUSED');

        $this->postJson('/api/asaas/payouts/authorization', [
            'type' => 'BILL',
            'bill' => ['id' => 'bill-1', 'value' => 151, 'identificationField' => str_repeat('1', 47)],
        ], $headers)->assertJsonPath('status', 'REFUSED');

        $this->postJson('/api/asaas/payouts/authorization', [
            'type' => 'TRANSFER',
            'transfer' => ['id' => 'bill-1', 'value' => 150],
        ], $headers)->assertJsonPath('status', 'REFUSED');

        $this->postJson('/api/asaas/payouts/authorization', [
            'type' => 'MOBILE_PHONE_RECHARGE',
            'mobilePhoneRecharge' => ['id' => 'x', 'value' => 20],
        ], $headers)->assertJsonPath('status', 'REFUSED');

        $this->postJson('/api/asaas/payouts/authorization', [
            'type' => 'BILL',
            'bill' => ['id' => 'bill-1', 'value' => 150],
        ], ['asaas-access-token' => 'wrong'])->assertUnauthorized();
    }

    public function test_authorization_webhook_refuses_outside_window(): void
    {
        PayoutOrder::factory()->forPayable($this->payable())->submitted('bill-1')->create([
            'submitted_at' => now()->subHour(),
        ]);

        $this->postJson('/api/asaas/payouts/authorization', [
            'type' => 'BILL',
            'bill' => ['id' => 'bill-1', 'value' => 150, 'identificationField' => str_repeat('1', 47)],
        ], ['asaas-access-token' => self::AUTH_TOKEN])->assertJsonPath('status', 'REFUSED');
    }

    public function test_authorization_matches_by_external_reference_before_id_is_stored(): void
    {
        $order = PayoutOrder::factory()->forPayable($this->payable())->create([
            'status' => PayoutStatus::Submitting,
            'submitted_at' => now(),
            'paid_value' => 150,
        ]);

        $this->postJson('/api/asaas/payouts/authorization', [
            'type' => 'BILL',
            'bill' => ['id' => 'bill-77', 'value' => 150, 'externalReference' => $order->uuid, 'identificationField' => str_repeat('1', 47)],
        ], ['asaas-access-token' => self::AUTH_TOKEN])->assertExactJson(['status' => 'APPROVED']);

        $this->assertSame('bill-77', $order->refresh()->external_id);
    }

    public function test_bill_authorization_requires_the_approved_digitable_line(): void
    {
        $order = PayoutOrder::factory()->forPayable($this->payable())->submitted('bill-1')->create();
        $headers = ['asaas-access-token' => self::AUTH_TOKEN];

        // Outro boleto com o mesmo id/valor de uma ordem válida.
        $this->postJson('/api/asaas/payouts/authorization', [
            'type' => 'BILL',
            'bill' => ['id' => 'bill-1', 'value' => 150, 'identificationField' => str_repeat('9', 47)],
        ], $headers)
            ->assertJsonPath('status', 'REFUSED')
            ->assertJsonPath('refuseReason', 'Linha digitável divergente da aprovada.');

        // Pedido sem a linha digitável.
        $this->postJson('/api/asaas/payouts/authorization', [
            'type' => 'BILL',
            'bill' => ['id' => 'bill-1', 'value' => 150],
        ], $headers)
            ->assertJsonPath('status', 'REFUSED')
            ->assertJsonPath('refuseReason', 'Linha digitável ausente no pedido de autorização.');

        $this->assertNull($order->refresh()->authorized_at);

        // A mesma linha com pontuação é aceita.
        $formatted = '11111.11111 11111.111111 11111.111111 1 11111111111111';
        $this->assertSame(str_repeat('1', 47), preg_replace('/\\D+/', '', $formatted));

        $this->postJson('/api/asaas/payouts/authorization', [
            'type' => 'BILL',
            'bill' => ['id' => 'bill-1', 'value' => 150, 'identificationField' => $formatted],
        ], $headers)->assertExactJson(['status' => 'APPROVED']);
    }

    public function test_preview_stores_digitable_line_returned_by_asaas(): void
    {
        $this->fakeAsaas();
        $payable = $this->payable();

        // Informado o código de barras (44 dígitos), a ordem guarda a linha digitável do Asaas.
        $this->postJson("/api/financial/payables/{$payable->uuid}/payouts", $this->boletoPayload([
            'payment_code' => '23791000000000150003381260000000000300000004',
            'scheduled_for' => now()->addDay()->toIso8601String(),
        ]))->assertCreated();

        $this->assertSame(self::BOLETO, PayoutOrder::query()->firstOrFail()->payment_code);
    }

    public function test_pix_key_authorization_checks_the_key(): void
    {
        $order = PayoutOrder::factory()->forPayable($this->payable())->submitted('tr-1')->create([
            'method' => PayoutMethod::PixKey,
            'payment_code' => 'fornecedor@example.com',
            'pix_key_type' => 'EMAIL',
        ]);

        $this->postJson('/api/asaas/payouts/authorization', [
            'type' => 'TRANSFER',
            'transfer' => ['id' => 'tr-1', 'value' => 150, 'bankAccount' => ['pixAddressKey' => 'outra@example.com']],
        ], ['asaas-access-token' => self::AUTH_TOKEN])->assertJsonPath('status', 'REFUSED');

        $this->postJson('/api/asaas/payouts/authorization', [
            'type' => 'TRANSFER',
            'transfer' => ['id' => 'tr-1', 'value' => 150, 'bankAccount' => ['pixAddressKey' => 'fornecedor@example.com']],
        ], ['asaas-access-token' => self::AUTH_TOKEN])->assertExactJson(['status' => 'APPROVED']);

        $this->assertNotNull($order->refresh()->authorized_at);
    }

    public function test_paid_event_settles_order_and_registers_payment(): void
    {
        $payable = $this->payable();
        $order = PayoutOrder::factory()->forPayable($payable)->submitted('bill-1')->create();

        $this->fakeAsaas(billStatus: 'PAID');

        // O status do corpo é ignorado: vale a consulta à API (PAID).
        $this->postJson('/api/asaas/payouts/events', [
            'id' => 'evt_1',
            'event' => 'BILL_PENDING',
            'bill' => ['id' => 'bill-1', 'status' => 'PENDING'],
        ], ['asaas-access-token' => self::EVENTS_TOKEN])
            ->assertOk()
            ->assertJsonPath('applied', true);

        $order->refresh();
        $this->assertSame(PayoutStatus::Paid, $order->status);
        $this->assertNull($order->active_lock);
        $this->assertSame('https://sandbox.asaas.com/comprovantes/1', $order->receipt_url);

        $payable->refresh();
        $this->assertSame('pago', $payable->status->value);
        $this->assertSame('150.00', $payable->paid_value);
        $this->assertDatabaseHas('payable_payments', [
            'payable_id' => $payable->getKey(),
            'tenant_id' => $this->tenant->getKey(),
            'notes' => "Pago via Asaas (ordem {$order->uuid})",
        ]);

        // Evento repetido não registra a baixa de novo.
        $this->postJson('/api/asaas/payouts/events', [
            'id' => 'evt_1',
            'event' => 'BILL_PAID',
            'bill' => ['id' => 'bill-1'],
        ], ['asaas-access-token' => self::EVENTS_TOKEN])->assertOk();

        $this->assertSame(1, $payable->payments()->count());
    }

    public function test_failed_event_releases_the_payable(): void
    {
        $payable = $this->payable();
        $order = PayoutOrder::factory()->forPayable($payable)->submitted('bill-1')->create();

        $this->fakeAsaas(billStatus: 'FAILED');

        $this->postJson('/api/asaas/payouts/events', [
            'event' => 'BILL_FAILED',
            'bill' => ['id' => 'bill-1'],
        ], ['asaas-access-token' => self::EVENTS_TOKEN])->assertOk();

        $order->refresh();
        $this->assertSame(PayoutStatus::Failed, $order->status);
        $this->assertStringContainsString('Saldo bloqueado', (string) $order->failure_reason);
        $this->assertSame('pendente', $payable->refresh()->status->value);
    }

    public function test_events_webhook_rejects_invalid_token(): void
    {
        $this->postJson('/api/asaas/payouts/events', ['event' => 'BILL_PAID', 'bill' => ['id' => 'x']], ['asaas-access-token' => 'nope'])
            ->assertUnauthorized();
    }

    public function test_reconcile_settles_processing_orders(): void
    {
        $payable = $this->payable();
        $order = PayoutOrder::factory()->forPayable($payable)->submitted('bill-1')->create();

        $this->fakeAsaas(billStatus: 'PAID');

        $this->artisan('payouts:reconcile')->assertSuccessful();

        $this->assertSame(PayoutStatus::Paid, $order->refresh()->status);
        $this->assertSame('pago', $payable->refresh()->status->value);
    }

    public function test_api_tokens_cannot_schedule_payouts(): void
    {
        $this->app['auth']->forgetGuards();
        $payable = $this->payable();

        $this->postJson("/api/financial/payables/{$payable->uuid}/payouts", $this->boletoPayload())
            ->assertUnauthorized();
    }

    private function payable(float $value = 150): Payable
    {
        return Payable::factory()->forTenant($this->tenant)->create(['value' => $value]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function boletoPayload(array $overrides = []): array
    {
        return [
            'method' => 'boleto',
            'payment_code' => self::BOLETO,
            'password' => 'password',
            ...$overrides,
        ];
    }

    private function fakeAsaas(
        float $billValue = 150,
        string $beneficiaryDocument = '12.345.678/0001-99',
        float $balance = 5000,
        string $billStatus = 'PENDING',
        bool $billFails = false,
    ): void {
        Http::fake([
            '*/bill/simulate' => Http::response([
                'minimumScheduleDate' => '2026-11-05',
                'fee' => 0,
                'bankSlipInfo' => [
                    'identificationField' => self::BOLETO,
                    'value' => $billValue,
                    'dueDate' => '2026-11-10',
                    'beneficiaryName' => 'Fornecedor LTDA',
                    'beneficiaryCpfCnpj' => $beneficiaryDocument,
                    'allowChangeValue' => false,
                    'isOverdue' => false,
                ],
            ]),
            '*/finance/balance' => Http::response(['balance' => $balance]),
            '*/bill/bill-1' => Http::response([
                'object' => 'bill',
                'id' => 'bill-1',
                'status' => $billStatus,
                'value' => 150,
                'paymentDate' => $billStatus === 'PAID' ? '2026-11-05' : null,
                'transactionReceiptUrl' => $billStatus === 'PAID' ? 'https://sandbox.asaas.com/comprovantes/1' : null,
                'failReasons' => $billStatus === 'FAILED' ? ['Saldo bloqueado'] : null,
            ]),
            '*/bill' => $billFails
                ? fn () => throw new ConnectionException('timeout')
                : Http::response([
                    'object' => 'bill',
                    'id' => 'bill-1',
                    'status' => 'PENDING',
                    'value' => $billValue,
                    'canBeCancelled' => true,
                ]),
        ]);
    }
}
