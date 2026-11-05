<?php

namespace Database\Seeders;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Models\Role;
use App\Modules\ACL\Services\RoleService;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Financial\DTOs\CreateRecurrenceDTO;
use App\Modules\Financial\Enums\PayableStatus;
use App\Modules\Financial\Enums\RecurrenceFrequency;
use App\Modules\Financial\Models\Category;
use App\Modules\Financial\Models\FinancialRecurrence;
use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Models\PayablePayment;
use App\Modules\Financial\Models\Supplier;
use App\Modules\Financial\Services\RecurrenceService;
use App\Modules\Payout\Enums\PayoutMethod;
use App\Modules\Payout\Enums\PayoutStatus;
use App\Modules\Payout\Enums\PixKeyType;
use App\Modules\Payout\Models\PayoutOrder;
use App\Modules\Payout\Services\RecurrenceAutoPayService;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\CurrentTenant;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Dados fictícios para testar o pagamento de contas via Asaas.
 *
 * Cria (ou recria) a empresa filha "Finanças Demo" com assinatura ativa, um
 * administrador próprio, fornecedores, categorias, contas a pagar e ordens de
 * pagamento em todos os estados.
 *
 * php artisan db:seed --class=PayoutDemoSeeder
 * Login: financeiro@demo.localhost / password
 */
class PayoutDemoSeeder extends Seeder
{
    private const DOMAIN = 'financas.demo.localhost';

    private const EMAIL = 'financeiro@demo.localhost';

    public function run(): void
    {
        $umbrella = Tenant::query()->whereNull('parent_id')->orderBy('id')->first();

        if ($umbrella === null) {
            $this->call(DatabaseSeeder::class);
            $umbrella = Tenant::query()->whereNull('parent_id')->orderBy('id')->firstOrFail();
        }

        $this->cleanup();

        $tenant = Tenant::query()->create([
            'parent_id' => $umbrella->getKey(),
            'name' => 'Finanças Demo',
            'document' => '45723174000110',
            'email' => 'financas@demo.localhost',
            'phone' => '41988887777',
            'domain' => self::DOMAIN,
        ]);

        app(RoleService::class)->createDefaultRolesFor($tenant);
        app(CurrentTenant::class)->set($tenant);

        $this->subscribe($umbrella, $tenant);
        $admin = $this->admin($tenant);

        $suppliers = $this->suppliers();
        $categories = $this->categories();

        $this->payables($tenant, $admin, $suppliers, $categories);
        $this->recurrences($admin, $suppliers, $categories);

        app(CurrentTenant::class)->forget();

        $this->command?->info('Empresa "Finanças Demo" criada. Login: '.self::EMAIL.' / password');
    }

    private function cleanup(): void
    {
        $existing = Tenant::query()->where('domain', self::DOMAIN)->first();

        User::query()->where('email', self::EMAIL)->delete();

        if ($existing !== null) {
            PayoutOrder::query()->withoutTenancy()->where('tenant_id', $existing->getKey())->delete();
            Payable::query()->withoutTenancy()->withTrashed()->where('tenant_id', $existing->getKey())->forceDelete();
            FinancialRecurrence::query()->withoutTenancy()->withTrashed()->where('tenant_id', $existing->getKey())->forceDelete();
            // Categorias têm FK para a categoria-pai: o cascade do MySQL não resolve.
            Category::query()->withoutTenancy()->withTrashed()->where('tenant_id', $existing->getKey())->whereNotNull('parent_id')->forceDelete();
            Category::query()->withoutTenancy()->withTrashed()->where('tenant_id', $existing->getKey())->forceDelete();
            $existing->delete();
        }
    }

    private function subscribe(Tenant $umbrella, Tenant $tenant): void
    {
        $plan = Plan::query()->forTenant($umbrella)->orderBy('id')->first();

        if ($plan === null) {
            $this->command?->warn('Nenhum plano encontrado no tenant principal: assinatura não criada.');

            return;
        }

        Subscription::query()->withoutTenancy()->create([
            'tenant_id' => $tenant->getKey(),
            'plan_id' => $plan->getKey(),
            'payment_gateway' => 'mockPix',
            'status' => SubscriptionStatus::ACTIVE,
            'started_at' => now()->subMonths(3),
            'last_billed_at' => now()->subDays(5),
            'next_billing_at' => now()->addYear(),
            'is_complimentary' => true,
        ]);
    }

    private function admin(Tenant $tenant): User
    {
        $user = new User([
            'name' => 'Financeiro Demo',
            'email' => self::EMAIL,
            'phone' => '41988887777',
            'document' => '39053344705',
            'password' => Hash::make('password'),
            'is_master' => false,
        ]);
        $user->tenant_id = $tenant->getKey();
        $user->save();

        $user->assignRole(
            Role::query()->forTenant($tenant)->where('name', DefaultRole::ADMINISTRATOR->value)->firstOrFail(),
        );

        return $user;
    }

    /**
     * @return array<string, Supplier>
     */
    private function suppliers(): array
    {
        // Chaves PIX de teste publicadas pelo Asaas para o sandbox
        // (https://docs.asaas.com/docs/testando-transfer%C3%AAncias).
        $rows = [
            'energia' => ['Copel Distribuição S.A.', '04368898000106', 'atendimento@copel.example', null],
            'internet' => ['Fibra Rápida Telecom LTDA', '11222333000181', 'cobranca@fibrarapida.example', null],
            'aluguel' => ['Imobiliária Horizonte LTDA', '07526557000100', 'financeiro@horizonte.example', 'cliente-a00001@pix.bcb.gov.br'],
            'contador' => ['Silva & Souza Contabilidade', '33683111000107', 'contato@silvasouza.example', 'cliente-a00002@pix.bcb.gov.br'],
            'software' => ['Nuvem Sistemas LTDA', '60701190000104', 'billing@nuvem.example', null],
            'limpeza' => ['Maria Aparecida Limpezas', '52998224725', 'maria.limpezas@example.com', 'cliente-a00003@pix.bcb.gov.br'],
        ];

        $suppliers = [];

        foreach ($rows as $key => [$name, $document, $email, $pixKey]) {
            $suppliers[$key] = Supplier::query()->create([
                'name' => $name,
                'document' => $document,
                'email' => $email,
                'phone' => '4133334444',
                'pix_key' => $pixKey,
                'pix_key_type' => $pixKey !== null ? 'EMAIL' : null,
            ]);
        }

        return $suppliers;
    }

    /**
     * @return array<string, Category>
     */
    private function categories(): array
    {
        $operacional = Category::query()->create(['name' => 'Despesas operacionais']);
        $administrativo = Category::query()->create(['name' => 'Administrativo']);

        return [
            'utilidades' => Category::query()->create(['name' => 'Água, luz e internet', 'parent_id' => $operacional->getKey()]),
            'aluguel' => Category::query()->create(['name' => 'Aluguel', 'parent_id' => $operacional->getKey()]),
            'servicos' => Category::query()->create(['name' => 'Serviços terceirizados', 'parent_id' => $administrativo->getKey()]),
            'software' => Category::query()->create(['name' => 'Software e assinaturas', 'parent_id' => $administrativo->getKey()]),
        ];
    }

    /**
     * @param  array<string, Supplier>  $s
     * @param  array<string, Category>  $c
     */
    private function payables(Tenant $tenant, User $admin, array $s, array $c): void
    {
        $today = CarbonImmutable::today();

        // Contas pendentes livres para testar "Pagar via Asaas".
        $this->payable($tenant, 'Energia elétrica', 412.37, $today->addDays(3), $s['energia'], $c['utilidades']);
        $this->payable($tenant, 'Internet fibra 500MB', 129.90, $today->addDays(5), $s['internet'], $c['utilidades']);
        $this->payable($tenant, 'Honorários contábeis', 850.00, $today->addDays(8), $s['contador'], $c['servicos']);
        $this->payable($tenant, 'Faxina quinzenal', 240.00, $today->addDays(2), $s['limpeza'], $c['servicos']);
        $this->payable($tenant, 'ERP - mensalidade', 189.00, $today->addDays(12), $s['software'], $c['software']);
        $this->payable($tenant, 'Aluguel do escritório', 3200.00, $today->addDays(10), $s['aluguel'], $c['aluguel']);
        $this->payable($tenant, 'Energia elétrica (vencida)', 398.12, $today->subDays(4), $s['energia'], $c['utilidades']);

        // Agendado para amanhã às 09:00 (boleto).
        $order = $this->order($tenant, $admin, $this->payable($tenant, 'Internet - filial', 99.90, $today->addDay(), $s['internet'], $c['utilidades']), [
            'method' => PayoutMethod::Boleto,
            'status' => PayoutStatus::Scheduled,
            'scheduled_for' => $today->addDay()->setTime(9, 0, 0, 0)->shiftTimezone('America/Sao_Paulo')->utc(),
        ]);
        $order->record('scheduled', "Pagamento de R$ 99,90 agendado para {$today->addDay()->format('d/m/Y')} 09:00 por {$admin->name}.");

        // Agendado para daqui a 6 dias (PIX por chave).
        $order = $this->order($tenant, $admin, $this->payable($tenant, 'Licença antivírus', 74.50, $today->addDays(6), $s['software'], $c['software']), [
            'method' => PayoutMethod::PixKey,
            'status' => PayoutStatus::Scheduled,
            'payment_code' => 'billing@nuvem.example',
            'pix_key_type' => PixKeyType::Email,
            'beneficiary_name' => null,
            'beneficiary_document' => null,
            'scheduled_for' => $today->addDays(6)->setTime(14, 30, 0, 0)->shiftTimezone('America/Sao_Paulo')->utc(),
        ]);
        $order->record('scheduled', "Pagamento de R$ 74,50 agendado por {$admin->name}.");

        // Em processamento no Asaas.
        $order = $this->order($tenant, $admin, $this->payable($tenant, 'Manutenção do ar-condicionado', 560.00, $today, $s['contador'], $c['servicos']), [
            'method' => PayoutMethod::Boleto,
            'status' => PayoutStatus::Processing,
            'scheduled_for' => now()->subMinutes(20),
            'submitted_at' => now()->subMinutes(20),
            'authorized_at' => now()->subMinutes(20),
            'paid_value' => 560.00,
            'external_id' => 'demo-bill-processing',
            'external_status' => 'BANK_PROCESSING',
        ]);
        $order->record('scheduled', "Pagamento de R$ 560,00 agendado por {$admin->name}.");
        $order->record('submitted', 'Enviado ao Asaas (R$ 560,00). Status: PENDING.');
        $order->record('authorized', 'Saída autorizada no webhook de validação do Asaas.');

        // Em conferência (falha de comunicação no envio).
        $order = $this->order($tenant, $admin, $this->payable($tenant, 'Material de escritório', 318.40, $today->subDay(), $s['software'], $c['servicos']), [
            'method' => PayoutMethod::PixQrCode,
            'status' => PayoutStatus::NeedsReview,
            'payment_code' => '00020126580014br.gov.bcb.pix0136demo-qr-code-ficticio-0000000000005204000053039865406318.405802BR5913LOJA DEMO LTDA6008CURITIBA62070503***6304ABCD',
            'beneficiary_name' => 'LOJA DEMO LTDA',
            'beneficiary_document' => '***.333.000-**',
            'scheduled_for' => now()->subHours(2),
            'submitted_at' => now()->subHours(2),
            'paid_value' => 318.40,
            'failure_reason' => 'Falha de comunicação no envio; o pagamento pode ter sido criado: cURL error 28: Operation timed out after 60001 milliseconds.',
        ]);
        $order->record('scheduled', "Pagamento de R$ 318,40 agendado por {$admin->name}.");
        $order->record('needs_review', 'Falha de comunicação no envio; o pagamento pode ter sido criado.');

        // Pago via Asaas (conta baixada).
        $paid = $this->payable($tenant, 'Aluguel do escritório (mês anterior)', 3200.00, $today->subMonth()->addDays(10), $s['aluguel'], $c['aluguel']);
        $order = $this->order($tenant, $admin, $paid, [
            'method' => PayoutMethod::Boleto,
            'status' => PayoutStatus::Paid,
            'scheduled_for' => $today->subMonth()->addDays(10)->setTime(8, 0)->utc(),
            'submitted_at' => $today->subMonth()->addDays(10)->setTime(8, 0)->utc(),
            'authorized_at' => $today->subMonth()->addDays(10)->setTime(8, 0, 6)->utc(),
            'settled_at' => $today->subMonth()->addDays(10)->setTime(10, 12)->utc(),
            'paid_value' => 3200.00,
            'external_id' => 'demo-bill-paid',
            'external_status' => 'PAID',
            'receipt_url' => 'https://sandbox.asaas.com/comprovantes/demo',
            'active_lock' => null,
        ]);
        $order->record('scheduled', "Pagamento de R$ 3.200,00 agendado por {$admin->name}.");
        $order->record('submitted', 'Enviado ao Asaas (R$ 3.200,00). Status: PENDING.');
        $order->record('authorized', 'Saída autorizada no webhook de validação do Asaas.');
        $order->record('paid', 'Pagamento confirmado pelo Asaas.');
        $this->markPaid($paid, 3200.00, $today->subMonth()->addDays(10), "Pago via Asaas (ordem {$order->uuid})");

        // Falhou (valor do boleto aumentou) — conta segue pendente.
        $order = $this->order($tenant, $admin, $this->payable($tenant, 'Água e esgoto', 87.65, $today->subDays(2), $s['energia'], $c['utilidades']), [
            'method' => PayoutMethod::Boleto,
            'status' => PayoutStatus::Failed,
            'value' => 87.65,
            'scheduled_for' => $today->subDays(2)->setTime(9, 0)->utc(),
            'submitted_at' => $today->subDays(2)->setTime(9, 0)->utc(),
            'settled_at' => $today->subDays(2)->setTime(9, 0, 2)->utc(),
            'failure_reason' => 'O valor atual (R$ 89,40) é maior que o aprovado (R$ 87,65). Pagamento não enviado.',
            'active_lock' => null,
        ]);
        $order->record('failed', 'O valor atual (R$ 89,40) é maior que o aprovado (R$ 87,65). Pagamento não enviado.');

        // Cancelado pelo usuário — conta segue pendente.
        $order = $this->order($tenant, $admin, $this->payable($tenant, 'Coffee break evento', 450.00, $today->addDays(15), $s['limpeza'], $c['servicos']), [
            'method' => PayoutMethod::PixKey,
            'status' => PayoutStatus::Cancelled,
            'payment_code' => '52998224725',
            'pix_key_type' => PixKeyType::Cpf,
            'beneficiary_name' => null,
            'beneficiary_document' => null,
            'scheduled_for' => $today->addDays(15)->setTime(10, 0)->utc(),
            'active_lock' => null,
        ]);
        $order->record('cancelled', "Agendamento cancelado por {$admin->name}.");

        // Contas pagas manualmente no mês anterior (histórico).
        foreach ([['Internet fibra 500MB (mês anterior)', 129.90, $s['internet'], $c['utilidades']], ['ERP - mensalidade (mês anterior)', 189.00, $s['software'], $c['software']]] as [$description, $value, $supplier, $category]) {
            $payable = $this->payable($tenant, $description, $value, $today->subMonth()->addDays(5), $supplier, $category);
            $this->markPaid($payable, $value, $today->subMonth()->addDays(5), null);
        }
    }

    /**
     * Recorrências com pagamento automático via PIX (chave do fornecedor e
     * chave própria). A primeira vence daqui a 2 dias: a ordem já nasce agendada.
     *
     * @param  array<string, Supplier>  $s
     * @param  array<string, Category>  $c
     */
    private function recurrences(User $admin, array $s, array $c): void
    {
        $recurrences = app(RecurrenceService::class);
        $autoPay = app(RecurrenceAutoPayService::class);
        $today = CarbonImmutable::today('America/Sao_Paulo');

        $contador = $recurrences->create(new CreateRecurrenceDTO(
            description: 'Honorários contábeis (mensal)',
            defaultValue: '650.00',
            dueDay: $today->addDays(2)->day,
            frequency: RecurrenceFrequency::Monthly,
            supplierId: $s['contador']->uuid,
            categoryId: $c['servicos']->uuid,
        ));

        try {
            $autoPay->enable($contador, ['time' => '09:00', 'max_value' => 700], $admin);
        } catch (Throwable $e) {
            $this->command?->warn('Pagamento automático não ativado: '.$e->getMessage());
        }

        $limpeza = $recurrences->create(new CreateRecurrenceDTO(
            description: 'Faxina mensal',
            defaultValue: '280.00',
            dueDay: $today->addDays(12)->day,
            frequency: RecurrenceFrequency::Monthly,
            supplierId: $s['limpeza']->uuid,
            categoryId: $c['servicos']->uuid,
        ));

        try {
            // Chave própria da recorrência, diferente da do fornecedor.
            $autoPay->enable($limpeza, [
                'pix_key' => 'cliente-a00004@pix.bcb.gov.br',
                'pix_key_type' => 'EMAIL',
                'time' => '14:00',
                'max_value' => 300,
            ], $admin);
        } catch (Throwable $e) {
            $this->command?->warn('Pagamento automático não ativado: '.$e->getMessage());
        }

        // Sem pagamento automático (para testar a ativação pela tela).
        $recurrences->create(new CreateRecurrenceDTO(
            description: 'Aluguel do escritório (mensal)',
            defaultValue: '3200.00',
            dueDay: 10,
            frequency: RecurrenceFrequency::Monthly,
            supplierId: $s['aluguel']->uuid,
            categoryId: $c['aluguel']->uuid,
        ));
    }

    private function payable(Tenant $tenant, string $description, float $value, CarbonImmutable $due, Supplier $supplier, Category $category): Payable
    {
        return Payable::query()->create([
            'tenant_id' => $tenant->getKey(),
            'supplier_id' => $supplier->getKey(),
            'category_id' => $category->getKey(),
            'description' => $description,
            'value' => $value,
            'due_date' => $due->toDateString(),
            'issue_date' => $due->subDays(10)->toDateString(),
            'status' => PayableStatus::Pending,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function order(Tenant $tenant, User $admin, Payable $payable, array $attributes): PayoutOrder
    {
        $value = (float) $payable->value;

        return PayoutOrder::query()->create([
            'tenant_id' => $tenant->getKey(),
            'payable_id' => $payable->getKey(),
            'created_by' => $admin->getKey(),
            'value' => $value,
            'payment_code' => $this->boletoCode($value),
            'beneficiary_name' => $payable->supplier?->name,
            'beneficiary_document' => $payable->supplier?->document,
            'preview' => ['value' => $value],
            'active_lock' => $payable->getKey(),
            ...$attributes,
        ]);
    }

    private function markPaid(Payable $payable, float $value, CarbonImmutable $paidAt, ?string $notes): void
    {
        PayablePayment::query()->create([
            'tenant_id' => $payable->tenant_id,
            'payable_id' => $payable->getKey(),
            'paid_at' => $paidAt->toDateString(),
            'paid_value' => $value,
            'difference' => 0,
            'notes' => $notes,
        ]);

        $payable->forceFill([
            'status' => PayableStatus::Paid,
            'paid_at' => $paidAt->toDateString(),
            'paid_value' => $value,
        ])->save();
    }

    /**
     * Linha digitável fictícia (47 dígitos) com o valor nos últimos 10 dígitos.
     */
    private function boletoCode(float $value): string
    {
        $cents = str_pad((string) (int) round($value * 100), 10, '0', STR_PAD_LEFT);

        $prefix = '2379';

        while (strlen($prefix) < 37) {
            $prefix .= (string) random_int(0, 9);
        }

        return $prefix.$cents;
    }
}
