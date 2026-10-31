<?php

namespace App\Modules\Financial\Mcp\Tools\Recurrences;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Services\RecurrenceService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class GenerateRecurringPayablesTool extends FinancialTool
{
    protected string $name = 'generate_recurring_payables';

    protected string $title = 'Gerar contas recorrentes';

    protected string $description = 'Gera as contas a pagar faltantes (próximos 12 meses) de todas as recorrências ativas com geração automática. Idempotente: não duplica contas existentes.';

    public function __construct(private readonly RecurrenceService $service) {}

    protected function permission(): Permission
    {
        return Permission::RECURRENCE_UPDATE;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $generated = $this->service->generateForTenant();

        $message = $generated > 0
            ? "{$generated} conta(s) gerada(s) com sucesso."
            : 'Nenhuma conta nova para gerar — as recorrências ativas já estão atualizadas.';

        return $this->success($message, ['generated' => $generated]);
    }
}
