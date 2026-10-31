<?php

namespace App\Modules\Financial\Mcp\Tools\Recurrences;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Models\FinancialRecurrence;
use App\Modules\Financial\Services\RecurrenceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteRecurrenceTool extends FinancialTool
{
    protected string $name = 'delete_recurrence';

    protected string $title = 'Excluir recorrência';

    protected string $description = 'Exclui uma recorrência (as contas a pagar já geradas são mantidas). Para apenas pausar, use update_recurrence com active=false. Ação destrutiva: confirme com o usuário antes de executar.';

    public function __construct(private readonly RecurrenceService $service) {}

    protected function permission(): Permission
    {
        return Permission::RECURRENCE_DELETE;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $request->validate(['id' => ['required', 'string']]);

        $recurrence = FinancialRecurrence::query()->where('uuid', $request->get('id'))->first();

        if ($recurrence === null) {
            return $this->notFound('Recorrência');
        }

        $this->service->delete($recurrence, $this->actor());

        return $this->success('Recorrência removida com sucesso.', ['id' => $recurrence->uuid]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Id (uuid) da recorrência.')->required(),
        ];
    }
}
