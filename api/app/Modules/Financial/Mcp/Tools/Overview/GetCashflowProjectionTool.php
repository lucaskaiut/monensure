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
class GetCashflowProjectionTool extends FinancialTool
{
    protected string $name = 'get_cashflow_projection';

    protected string $title = 'Projeção de fluxo de caixa';

    protected string $description = 'Projeção de saídas futuras (contas pendentes) acumuladas para hoje, 7, 15, 30, 60 e 90 dias, além do total já vencido.';

    public function __construct(private readonly FinancialOverviewService $service) {}

    protected function permission(): Permission
    {
        return Permission::CASHFLOW_READ;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        return Response::structured([
            'reference_date' => now()->toDateString(),
            ...$this->service->projection(),
        ]);
    }
}
