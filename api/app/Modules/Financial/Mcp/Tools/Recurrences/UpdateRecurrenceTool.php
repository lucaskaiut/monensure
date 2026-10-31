<?php

namespace App\Modules\Financial\Mcp\Tools\Recurrences;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Enums\RecurrenceFrequency;
use App\Modules\Financial\Http\Requests\UpdateRecurrenceRequest;
use App\Modules\Financial\Http\Resources\FinancialRecurrenceResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Models\FinancialRecurrence;
use App\Modules\Financial\Services\RecurrenceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class UpdateRecurrenceTool extends FinancialTool
{
    protected string $name = 'update_recurrence';

    protected string $title = 'Atualizar recorrência';

    protected string $description = 'Atualiza uma recorrência (envie apenas os campos a alterar). Use active=false para pausar. Contas já geradas não são alteradas; novas contas faltantes do horizonte de 12 meses são geradas.';

    public function __construct(private readonly RecurrenceService $service) {}

    protected function permission(): Permission
    {
        return Permission::RECURRENCE_UPDATE;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            ...(new UpdateRecurrenceRequest)->rules(),
        ]);

        $recurrence = FinancialRecurrence::query()->where('uuid', $validated['id'])->first();

        if ($recurrence === null) {
            return $this->notFound('Recorrência');
        }

        $recurrence = $this->service->update($recurrence, Arr::except($validated, 'id'), $this->actor());

        return $this->success('Recorrência atualizada com sucesso.', [
            'recurrence' => $this->resource(FinancialRecurrenceResource::make($recurrence->load('supplier', 'category'))),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Id (uuid) da recorrência.')->required(),
            'description' => $schema->string()->max(255)->description('Nova descrição.'),
            'default_value' => $schema->number()->min(0.01)->description('Novo valor padrão em reais.'),
            'due_day' => $schema->integer()->min(1)->max(31)->description('Novo dia de vencimento.'),
            'frequency' => $schema->string()->enum(RecurrenceFrequency::values())->description('Nova frequência.'),
            'supplier_id' => $schema->string()->nullable()->description('Id (uuid) do fornecedor, ou null para remover.'),
            'category_id' => $schema->string()->nullable()->description('Id (uuid) da categoria, ou null para remover.'),
            'active' => $schema->boolean()->description('Ativa/pausa a recorrência.'),
            'generate_automatically' => $schema->boolean()->description('Liga/desliga a geração automática.'),
        ];
    }
}
