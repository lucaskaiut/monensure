<?php

namespace App\Modules\Financial\Mcp\Tools\Overview;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Services\FinancialOverviewService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetFinancialSummaryTool extends FinancialTool
{
    protected string $name = 'get_financial_summary';

    protected string $title = 'Resumo financeiro';

    protected string $description = 'Resumo das contas pendentes: vencidas, vencendo hoje, próximos 7 e 30 dias, mês corrente e total em aberto (quantidade e soma em reais).';

    public function __construct(private readonly FinancialOverviewService $service) {}

    protected function permission(): Permission
    {
        return Permission::PAYABLE_READ;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        return Response::structured([
            'reference_date' => now()->toDateString(),
            ...$this->service->summary(),
        ]);
    }
}
