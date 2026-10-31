<?php

namespace App\Modules\Financial\Mcp;

use App\Modules\Financial\Mcp\Tools\Categories\CreateCategoryTool;
use App\Modules\Financial\Mcp\Tools\Categories\DeleteCategoryTool;
use App\Modules\Financial\Mcp\Tools\Categories\GetCategoryTool;
use App\Modules\Financial\Mcp\Tools\Categories\ListCategoriesTool;
use App\Modules\Financial\Mcp\Tools\Categories\UpdateCategoryTool;
use App\Modules\Financial\Mcp\Tools\Overview\GetCashflowProjectionTool;
use App\Modules\Financial\Mcp\Tools\Overview\GetFinancialSummaryTool;
use App\Modules\Financial\Mcp\Tools\Overview\ListOverduePayablesTool;
use App\Modules\Financial\Mcp\Tools\Payables\CancelPayableTool;
use App\Modules\Financial\Mcp\Tools\Payables\CreateInstallmentPlanTool;
use App\Modules\Financial\Mcp\Tools\Payables\CreatePayableTool;
use App\Modules\Financial\Mcp\Tools\Payables\DeletePayableTool;
use App\Modules\Financial\Mcp\Tools\Payables\GetPayableTool;
use App\Modules\Financial\Mcp\Tools\Payables\ListPayablesTool;
use App\Modules\Financial\Mcp\Tools\Payables\PayPayableTool;
use App\Modules\Financial\Mcp\Tools\Payables\UpdatePayableTool;
use App\Modules\Financial\Mcp\Tools\Recurrences\CreateRecurrenceTool;
use App\Modules\Financial\Mcp\Tools\Recurrences\DeleteRecurrenceTool;
use App\Modules\Financial\Mcp\Tools\Recurrences\GenerateRecurringPayablesTool;
use App\Modules\Financial\Mcp\Tools\Recurrences\GetRecurrenceTool;
use App\Modules\Financial\Mcp\Tools\Recurrences\ListRecurrencesTool;
use App\Modules\Financial\Mcp\Tools\Recurrences\UpdateRecurrenceTool;
use App\Modules\Financial\Mcp\Tools\Suppliers\CreateSupplierTool;
use App\Modules\Financial\Mcp\Tools\Suppliers\DeleteSupplierTool;
use App\Modules\Financial\Mcp\Tools\Suppliers\GetSupplierTool;
use App\Modules\Financial\Mcp\Tools\Suppliers\ListSuppliersTool;
use App\Modules\Financial\Mcp\Tools\Suppliers\UpdateSupplierTool;
use Laravel\Mcp\Server;

/**
 * Servidor MCP de gestão financeira (contas a pagar, parcelamentos,
 * recorrências, fornecedores, categorias e fluxo de caixa).
 *
 * Exposto via HTTP em /api/mcp/financial (ver routes/ai.php). A autenticação
 * usa os tokens já existentes da plataforma (API token api_* ou token
 * Sanctum de usuário) e cada ferramenta respeita o ACL (ver FinancialTool).
 */
class FinancialServer extends Server
{
    protected string $name = 'Monensure Financeiro';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Servidor de gestão financeira do Monensure. Opera sobre os dados do tenant (empresa) vinculado ao token de acesso.

        Convenções:
        - Identificadores ("id", "supplier_id", "category_id") são UUIDs. Use as ferramentas list_* para descobri-los antes de criar ou editar registros — nunca invente ids.
        - Datas no formato YYYY-MM-DD. Valores monetários em reais (número decimal, ex.: 1234.56).
        - Status de contas: "pendente", "pago", "cancelado". Frequências de recorrência: "mensal", "bimestral", "trimestral", "semestral", "anual".
        - Despesa pontual → create_payable. Compra parcelada → create_installment_plan. Conta que se repete → create_recurrence.
        - Operações destrutivas ou que movimentam dinheiro (pay_payable, cancel_payable, delete_*) devem ser confirmadas explicitamente com o usuário antes da execução.
        - Antes de responder sobre valores (totais, vencidas, fluxo de caixa), consulte as ferramentas; nunca estime números.
        - Só aparecem as ferramentas permitidas pelas permissões do usuário ou pelos escopos do token.
    MARKDOWN;

    /**
     * Devolve todas as ferramentas em uma única página de tools/list.
     */
    public int $defaultPaginationLength = 50;

    protected array $capabilities = [
        self::CAPABILITY_TOOLS => [
            'listChanged' => false,
        ],
    ];

    protected array $tools = [
        // Visão geral
        GetFinancialSummaryTool::class,
        GetCashflowProjectionTool::class,
        ListOverduePayablesTool::class,

        // Contas a pagar
        ListPayablesTool::class,
        GetPayableTool::class,
        CreatePayableTool::class,
        CreateInstallmentPlanTool::class,
        UpdatePayableTool::class,
        PayPayableTool::class,
        CancelPayableTool::class,
        DeletePayableTool::class,

        // Recorrências
        ListRecurrencesTool::class,
        GetRecurrenceTool::class,
        CreateRecurrenceTool::class,
        UpdateRecurrenceTool::class,
        DeleteRecurrenceTool::class,
        GenerateRecurringPayablesTool::class,

        // Fornecedores
        ListSuppliersTool::class,
        GetSupplierTool::class,
        CreateSupplierTool::class,
        UpdateSupplierTool::class,
        DeleteSupplierTool::class,

        // Categorias
        ListCategoriesTool::class,
        GetCategoryTool::class,
        CreateCategoryTool::class,
        UpdateCategoryTool::class,
        DeleteCategoryTool::class,
    ];
}
