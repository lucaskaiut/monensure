<?php

namespace App\Modules\Financial\Agents;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Assistant\Contracts\AssistantAgent;
use App\Modules\Assistant\Support\Tool;
use App\Modules\Assistant\Support\ToolRegistry;
use App\Modules\Financial\DTOs\CreateInstallmentPlanDTO;
use App\Modules\Financial\DTOs\CreatePayableDTO;
use App\Modules\Financial\DTOs\CreateRecurrenceDTO;
use App\Modules\Financial\DTOs\PayPayableDTO;
use App\Modules\Financial\Http\Resources\PayableResource;
use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Services\CategoryService;
use App\Modules\Financial\Services\FinancialOverviewService;
use App\Modules\Financial\Services\PayableService;
use App\Modules\Financial\Services\RecurrenceService;
use App\Modules\Financial\Services\SupplierService;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Assistente financeiro operacional.
 *
 * Registra as ferramentas do domínio financeiro (contas, parcelamentos,
 * recorrências, fluxo de caixa) e um system prompt que orienta o modelo a
 * nunca inventar valores e sempre consultar o sistema antes de responder.
 */
final class FinancialAssistantAgent implements AssistantAgent
{
    public function __construct(
        private readonly PayableService $payables,
        private readonly SupplierService $suppliers,
        private readonly CategoryService $categories,
        private readonly RecurrenceService $recurrences,
        private readonly FinancialOverviewService $overview,
    ) {}

    public function systemPrompt(): string
    {
        return <<<'TXT'
Você é a assistente financeira operacional do sistema. Você tem acesso às ferramentas financeiras para operar contas a pagar, parcelamentos, recorrências e fluxo de caixa.

Regras fundamentais:
- Responda SEMPRE em português do Brasil.
- NUNCA invente valores, datas ou dados: consulte sempre as ferramentas antes de responder sobre finanças.
- Antes de responder qualquer pergunta sobre valores (total a pagar, contas vencidas, fluxo de caixa), consulte as ferramentas correspondentes.
- Ao criar contas parceladas, use obrigatoriamente create_installment_plan.
- Ao criar contas recorrentes, use obrigatoriamente create_recurrence.
- Ao criar uma conta avulsa, use create_payable.
- Operações destrutivas (pagar, cancelar, excluir) exigem confirmação explícita do usuário.
- Ao concluir qualquer operação, informe um resumo claro do que foi executado e dos valores envolvidos.
- Ao listar vários itens, prefira tabelas Markdown.
- Valores monetários devem ser apresentados em reais (R$).
TXT;
    }

    public function tools(User $user): ToolRegistry
    {
        $registry = new ToolRegistry;

        $registry->register(new Tool(
            name: 'create_payable',
            description: 'Cria uma conta a pagar avulsa. Use para despesas pontuais. Para parcelamentos use create_installment_plan; para contas recorrentes use create_recurrence.',
            parameters: [
                'description' => ['type' => 'string', 'description' => 'Descrição da conta (ex.: "Conta de internet Vivo").', 'required' => true],
                'value' => ['type' => 'number', 'description' => 'Valor em reais (ex.: 120).', 'required' => true],
                'due_date' => ['type' => 'string', 'description' => 'Data de vencimento no formato YYYY-MM-DD.', 'required' => true],
                'supplier' => ['type' => 'string', 'description' => 'Nome do fornecedor (opcional).'],
                'category' => ['type' => 'string', 'description' => 'Nome da categoria (opcional).'],
            ],
            handler: fn (array $args) => $this->createPayable($user, $args),
            writes: true,
        ));

        $registry->register(new Tool(
            name: 'create_installment_plan',
            description: 'Cria um parcelamento (ex.: financiamento em N parcelas). Gera automaticamente todas as parcelas com seus vencimentos mensais.',
            parameters: [
                'description' => ['type' => 'string', 'description' => 'Descrição do parcelamento (ex.: "Financiamento carro").', 'required' => true],
                'value' => ['type' => 'number', 'description' => 'Valor de cada parcela em reais.', 'required' => true],
                'first_due_date' => ['type' => 'string', 'description' => 'Vencimento da primeira parcela (YYYY-MM-DD).', 'required' => true],
                'first_installment_number' => ['type' => 'integer', 'description' => 'Número da primeira parcela (ex.: 12 se já existirem 11 anteriores).', 'required' => true],
                'total_installments' => ['type' => 'integer', 'description' => 'Quantidade de parcelas a gerar.', 'required' => true],
                'supplier' => ['type' => 'string', 'description' => 'Nome do fornecedor (opcional).'],
                'category' => ['type' => 'string', 'description' => 'Nome da categoria (opcional).'],
            ],
            handler: fn (array $args) => $this->createInstallmentPlan($user, $args),
            writes: true,
        ));

        $registry->register(new Tool(
            name: 'create_recurrence',
            description: 'Cria uma conta recorrente (ex.: água, luz, internet, aluguel). O sistema gera os lançamentos futuros automaticamente.',
            parameters: [
                'description' => ['type' => 'string', 'description' => 'Descrição da recorrência (ex.: "Internet Vivo").', 'required' => true],
                'value' => ['type' => 'number', 'description' => 'Valor padrão em reais.', 'required' => true],
                'due_day' => ['type' => 'integer', 'description' => 'Dia do mês do vencimento (1 a 31).', 'required' => true],
                'frequency' => ['type' => 'string', 'enum' => ['mensal', 'bimestral', 'trimestral', 'semestral', 'anual'], 'description' => 'Frequência de cobrança.', 'required' => true],
                'supplier' => ['type' => 'string', 'description' => 'Nome do fornecedor (opcional).'],
                'category' => ['type' => 'string', 'description' => 'Nome da categoria (opcional).'],
            ],
            handler: fn (array $args) => $this->createRecurrence($user, $args),
            writes: true,
        ));

        $registry->register(new Tool(
            name: 'list_payables',
            description: 'Lista contas a pagar com filtros por status, período, fornecedor e categoria.',
            parameters: [
                'status' => ['type' => 'string', 'enum' => ['pendente', 'pago', 'cancelado'], 'description' => 'Filtra por status.'],
                'from' => ['type' => 'string', 'description' => 'Vencimento inicial (YYYY-MM-DD).'],
                'to' => ['type' => 'string', 'description' => 'Vencimento final (YYYY-MM-DD).'],
                'supplier' => ['type' => 'string', 'description' => 'Nome do fornecedor.'],
                'category' => ['type' => 'string', 'description' => 'Nome da categoria.'],
                'search' => ['type' => 'string', 'description' => 'Termo de busca na descrição.'],
            ],
            handler: fn (array $args) => $this->listPayables($user, $args),
        ));

        $registry->register(new Tool(
            name: 'mark_payable_paid',
            description: 'Marca uma conta a pagar como paga. Permite informar valor pago diferente do valor original (divergência).',
            parameters: [
                'payable_id' => ['type' => 'string', 'description' => 'Identificador (id) da conta a pagar.', 'required' => true],
                'paid_at' => ['type' => 'string', 'description' => 'Data do pagamento (YYYY-MM-DD). Padrão: hoje.'],
                'paid_value' => ['type' => 'number', 'description' => 'Valor pago. Padrão: valor da conta.'],
            ],
            handler: fn (array $args) => $this->markPayablePaid($user, $args),
            writes: true,
        ));

        $registry->register(new Tool(
            name: 'get_cashflow_projection',
            description: 'Retorna o fluxo de caixa futuro (hoje, 7, 15, 30, 60 e 90 dias) e o total de contas vencidas.',
            parameters: [],
            handler: fn () => $this->getCashflowProjection($user),
        ));

        $registry->register(new Tool(
            name: 'get_financial_summary',
            description: 'Retorna o resumo financeiro: vencidas, vencendo hoje, próximos 7 e 30 dias e total em aberto.',
            parameters: [],
            handler: fn () => $this->getFinancialSummary($user),
        ));

        return $registry;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function createPayable(User $user, array $args): array
    {
        $this->assertPermission($user, Permission::PAYABLE_CREATE);

        $payable = $this->payables->create(CreatePayableDTO::fromArray([
            'description' => $args['description'],
            'value' => $args['value'],
            'due_date' => $args['due_date'],
            'supplier_id' => $this->resolveSupplierId($args['supplier'] ?? null),
            'category_id' => $this->resolveCategoryId($args['category'] ?? null),
        ]), $user);

        return ['message' => 'Conta criada com sucesso.', 'payable' => PayableResource::make($payable)->resolve()];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function createInstallmentPlan(User $user, array $args): array
    {
        $this->assertPermission($user, Permission::PAYABLE_CREATE);

        $group = $this->payables->createInstallmentPlan(CreateInstallmentPlanDTO::fromArray([
            'description' => $args['description'],
            'value' => $args['value'],
            'first_due_date' => $args['first_due_date'],
            'first_installment_number' => $args['first_installment_number'],
            'total_installments' => $args['total_installments'],
            'supplier_id' => $this->resolveSupplierId($args['supplier'] ?? null),
            'category_id' => $this->resolveCategoryId($args['category'] ?? null),
        ]), $user);

        $last = $group->first_installment_number + $group->total_installments - 1;

        return [
            'message' => "Parcelamento criado: {$group->total_installments} parcelas de {$args['first_installment_number']}/{$last} até {$last}/{$last}.",
            'installment_group_uuid' => $group->uuid,
            'first_due_date' => $group->first_due_date?->toDateString(),
            'payables' => PayableResource::collection($group->payables)->resolve(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function createRecurrence(User $user, array $args): array
    {
        $this->assertPermission($user, Permission::RECURRENCE_CREATE);

        $recurrence = $this->recurrences->create(CreateRecurrenceDTO::fromArray([
            'description' => $args['description'],
            'default_value' => $args['value'],
            'due_day' => $args['due_day'],
            'frequency' => $args['frequency'],
            'supplier_id' => $this->resolveSupplierId($args['supplier'] ?? null),
            'category_id' => $this->resolveCategoryId($args['category'] ?? null),
        ]), $user);

        return ['message' => 'Recorrência criada com sucesso.', 'recurrence' => [
            'id' => $recurrence->uuid,
            'description' => $recurrence->description,
            'default_value' => $recurrence->default_value,
            'due_day' => $recurrence->due_day,
            'frequency' => $recurrence->frequency?->value,
        ]];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function listPayables(User $user, array $args): array
    {
        $this->assertPermission($user, Permission::PAYABLE_READ);

        $payables = $this->payables->paginate([
            'status' => $args['status'] ?? null,
            'supplier_id' => $this->resolveSupplierId($args['supplier'] ?? null, required: false),
            'category_id' => $this->resolveCategoryId($args['category'] ?? null, required: false),
            'from' => $args['from'] ?? null,
            'to' => $args['to'] ?? null,
            'search' => $args['search'] ?? null,
        ], 50);

        return [
            'total' => $payables->total(),
            'payables' => PayableResource::collection($payables)->resolve(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function markPayablePaid(User $user, array $args): array
    {
        $this->assertPermission($user, Permission::PAYABLE_PAY);

        $payable = Payable::query()->where('uuid', $args['payable_id'])->firstOrFail();

        $paidAt = isset($args['paid_at']) ? CarbonImmutable::parse($args['paid_at']) : now();
        $paidValue = $args['paid_value'] ?? $payable->value;

        $payable = $this->payables->pay($payable, PayPayableDTO::fromArray([
            'paid_at' => $paidAt,
            'paid_value' => $paidValue,
        ]), $user);

        return ['message' => 'Conta marcada como paga.', 'payable' => PayableResource::make($payable)->resolve()];
    }

    private function getCashflowProjection(User $user): array
    {
        $this->assertPermission($user, Permission::CASHFLOW_READ);

        return $this->overview->projection();
    }

    private function getFinancialSummary(User $user): array
    {
        $this->assertPermission($user, Permission::PAYABLE_READ);

        return $this->overview->summary();
    }

    private function assertPermission(User $user, Permission $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new RuntimeException('Você não possui permissão para executar esta operação.');
        }
    }

    private function resolveSupplierId(?string $name, bool $required = false): ?string
    {
        return $this->resolveId($name, 'Fornecedor', $this->suppliers->all()->keyBy(fn ($s) => mb_strtolower($s->name)), $required);
    }

    private function resolveCategoryId(?string $name, bool $required = false): ?string
    {
        return $this->resolveId($name, 'Categoria', $this->categories->all()->keyBy(fn ($c) => mb_strtolower($c->name)), $required);
    }

    /**
     * @param  Collection<string, mixed>  $lookup
     */
    private function resolveId(?string $name, string $label, Collection $lookup, bool $required): ?string
    {
        if (blank($name)) {
            return null;
        }

        $match = $lookup->get(mb_strtolower(trim($name)));

        if ($match === null && $required) {
            throw new RuntimeException("{$label} \"{$name}\" não encontrado.");
        }

        return $match?->uuid;
    }
}
