<?php

namespace App\Modules\Financial\Mcp\Tools\Payables;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\DTOs\CreatePayableDTO;
use App\Modules\Financial\Http\Requests\StorePayableRequest;
use App\Modules\Financial\Http\Resources\PayableResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Services\PayableService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class CreatePayableTool extends FinancialTool
{
    protected string $name = 'create_payable';

    protected string $title = 'Criar conta a pagar';

    protected string $description = 'Cria uma conta a pagar avulsa (despesa pontual). Para parcelamentos use create_installment_plan; para contas que se repetem (aluguel, luz, internet) use create_recurrence.';

    public function __construct(private readonly PayableService $service) {}

    protected function permission(): Permission
    {
        return Permission::PAYABLE_CREATE;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $data = $request->validate((new StorePayableRequest)->rules());

        $payable = $this->service->create(CreatePayableDTO::fromArray($data), $this->actor());

        return $this->success('Conta a pagar criada com sucesso.', [
            'payable' => $this->resource(PayableResource::make($payable->load('supplier', 'category'))),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'description' => $schema->string()->max(255)->description('Descrição da conta.')->required(),
            'value' => $schema->number()->min(0.01)->description('Valor em reais (ex.: 120.50).')->required(),
            'due_date' => $schema->string()->format('date')->description('Vencimento (YYYY-MM-DD).')->required(),
            'issue_date' => $schema->string()->format('date')->description('Data de emissão (YYYY-MM-DD).'),
            'supplier_id' => $schema->string()->description('Id (uuid) do fornecedor.'),
            'category_id' => $schema->string()->description('Id (uuid) da categoria.'),
        ];
    }
}
