<?php

namespace App\Modules\Financial\Mcp\Tools\Overview;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Http\Resources\PayableResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Services\FinancialOverviewService;
use App\Modules\Financial\Support\ResolvesRelations;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListOverduePayablesTool extends FinancialTool
{
    use ResolvesRelations;

    protected string $name = 'list_overdue_payables';

    protected string $title = 'Listar contas vencidas';

    protected string $description = 'Lista contas pendentes com vencimento anterior a hoje, das mais antigas para as mais recentes. Filtros opcionais por fornecedor e categoria.';

    public function __construct(private readonly FinancialOverviewService $service) {}

    protected function permission(): Permission
    {
        return Permission::PAYABLE_READ;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'string'],
            'category_id' => ['nullable', 'string'],
        ]);

        $supplierId = $this->resolveSupplierId($validated['supplier_id'] ?? null);
        $categoryId = $this->resolveCategoryId($validated['category_id'] ?? null);

        if (filled($validated['supplier_id'] ?? null) && $supplierId === null) {
            return $this->notFound('Fornecedor');
        }

        if (filled($validated['category_id'] ?? null) && $categoryId === null) {
            return $this->notFound('Categoria');
        }

        $paginator = $this->service->overdue([
            'supplier_id' => $supplierId,
            'category_id' => $categoryId,
        ], $this->perPage($request), $this->page($request));

        return $this->paginated(PayableResource::collection($paginator), $paginator);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'supplier_id' => $schema->string()->description('Id (uuid) do fornecedor.'),
            'category_id' => $schema->string()->description('Id (uuid) da categoria.'),
            'page' => $schema->integer()->min(1)->description('Página (padrão 1).'),
            'per_page' => $schema->integer()->min(1)->max(100)->description('Itens por página (padrão 15, máx. 100).'),
        ];
    }
}
