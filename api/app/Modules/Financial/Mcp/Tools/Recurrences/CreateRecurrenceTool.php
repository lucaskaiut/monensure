<?php

namespace App\Modules\Financial\Mcp\Tools\Recurrences;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\DTOs\CreateRecurrenceDTO;
use App\Modules\Financial\Enums\RecurrenceFrequency;
use App\Modules\Financial\Http\Requests\StoreRecurrenceRequest;
use App\Modules\Financial\Http\Resources\FinancialRecurrenceResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Services\RecurrenceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class CreateRecurrenceTool extends FinancialTool
{
    protected string $name = 'create_recurrence';

    protected string $title = 'Criar recorrência';

    protected string $description = 'Cria uma conta recorrente (aluguel, luz, internet...). Com active e generate_automatically ligados (padrão), o sistema já gera as contas a pagar dos próximos 12 meses.';

    public function __construct(private readonly RecurrenceService $service) {}

    protected function permission(): Permission
    {
        return Permission::RECURRENCE_CREATE;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $data = $request->validate((new StoreRecurrenceRequest)->rules());

        $recurrence = $this->service->create(CreateRecurrenceDTO::fromArray($data), $this->actor());

        return $this->success('Recorrência criada com sucesso.', [
            'recurrence' => $this->resource(FinancialRecurrenceResource::make($recurrence->refresh()->load('supplier', 'category'))),
            'generated_payables' => $recurrence->payables()->count(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'description' => $schema->string()->max(255)->description('Descrição da recorrência.')->required(),
            'default_value' => $schema->number()->min(0.01)->description('Valor padrão de cada ocorrência, em reais.')->required(),
            'due_day' => $schema->integer()->min(1)->max(31)->description('Dia do vencimento (1–31; ajustado ao último dia em meses mais curtos).')->required(),
            'frequency' => $schema->string()->enum(RecurrenceFrequency::values())->description('Frequência.')->required(),
            'supplier_id' => $schema->string()->description('Id (uuid) do fornecedor.'),
            'category_id' => $schema->string()->description('Id (uuid) da categoria.'),
            'active' => $schema->boolean()->description('Recorrência ativa (padrão true).'),
            'generate_automatically' => $schema->boolean()->description('Gerar contas automaticamente (padrão true).'),
        ];
    }
}
