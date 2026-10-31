<?php

namespace App\Modules\Financial\Mcp\Tools\Payables;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\DTOs\CreateInstallmentPlanDTO;
use App\Modules\Financial\Http\Requests\StoreInstallmentPlanRequest;
use App\Modules\Financial\Http\Resources\PayableResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Services\PayableService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class CreateInstallmentPlanTool extends FinancialTool
{
    protected string $name = 'create_installment_plan';

    protected string $title = 'Criar parcelamento';

    protected string $description = 'Cria um parcelamento: gera uma conta a pagar por parcela, com vencimentos mensais a partir de first_due_date. "value" é o valor de CADA parcela. Use first_installment_number > 1 para registrar um parcelamento já em andamento (ex.: parcelas 12 a 48).';

    public function __construct(private readonly PayableService $service) {}

    protected function permission(): Permission
    {
        return Permission::PAYABLE_CREATE;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $data = $request->validate((new StoreInstallmentPlanRequest)->rules());

        $group = $this->service->createInstallmentPlan(CreateInstallmentPlanDTO::fromArray($data), $this->actor());

        return $this->success('Parcelamento criado com sucesso.', [
            'installment_group_uuid' => $group->uuid,
            'description' => $group->description,
            'total_installments' => $group->total_installments,
            'first_installment_number' => $group->first_installment_number,
            'payables' => $this->resource(PayableResource::collection($group->payables)),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'description' => $schema->string()->max(255)->description('Descrição do parcelamento.')->required(),
            'value' => $schema->number()->min(0.01)->description('Valor de cada parcela em reais.')->required(),
            'first_due_date' => $schema->string()->format('date')->description('Vencimento da primeira parcela gerada (YYYY-MM-DD).')->required(),
            'first_installment_number' => $schema->integer()->min(1)->description('Número da primeira parcela gerada (1 para um parcelamento novo).')->required(),
            'total_installments' => $schema->integer()->min(1)->max(600)->description('Quantidade de parcelas a gerar.')->required(),
            'issue_date' => $schema->string()->format('date')->description('Data de emissão (YYYY-MM-DD).'),
            'supplier_id' => $schema->string()->description('Id (uuid) do fornecedor.'),
            'category_id' => $schema->string()->description('Id (uuid) da categoria.'),
        ];
    }
}
