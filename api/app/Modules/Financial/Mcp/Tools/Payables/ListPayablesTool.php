<?php

namespace App\Modules\Financial\Mcp\Tools\Payables;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Enums\PayableStatus;
use App\Modules\Financial\Http\Resources\PayableResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Services\PayableService;
use App\Modules\Financial\Support\ResolvesRelations;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListPayablesTool extends FinancialTool
{
    use ResolvesRelations;

    protected string $name = 'list_payables';

    protected string $title = 'Listar contas a pagar';

    protected string $description = 'Lista contas a pagar (paginado, ordenado por vencimento) com filtros por status, período de vencimento, fornecedor, categoria e descrição. O campo meta.value_total traz a soma dos valores de todas as contas que atendem aos filtros (não só da página).';

    public function __construct(private readonly PayableService $service) {}

    protected function permission(): Permission
    {
        return Permission::PAYABLE_READ;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(PayableStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'supplier_id' => ['nullable', 'string'],
            'category_id' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
        ]);

        $supplierId = $this->resolveSupplierId($validated['supplier_id'] ?? null);
        $categoryId = $this->resolveCategoryId($validated['category_id'] ?? null);

        if (filled($validated['supplier_id'] ?? null) && $supplierId === null) {
            return $this->notFound('Fornecedor');
        }

        if (filled($validated['category_id'] ?? null) && $categoryId === null) {
            return $this->notFound('Categoria');
        }

        $filters = [
            'status' => $validated['status'] ?? null,
            'supplier_id' => $supplierId,
            'category_id' => $categoryId,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'search' => $validated['search'] ?? null,
        ];

        $paginator = $this->service->paginate($filters, $this->perPage($request), $this->page($request));

        return $this->paginated(PayableResource::collection($paginator), $paginator, [
            'value_total' => $this->service->sumValues($filters),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(PayableStatus::values())->description('Status: pendente, pago ou cancelado.'),
            'from' => $schema->string()->format('date')->description('Vencimento a partir de (YYYY-MM-DD).'),
            'to' => $schema->string()->format('date')->description('Vencimento até (YYYY-MM-DD).'),
            'supplier_id' => $schema->string()->description('Id (uuid) do fornecedor.'),
            'category_id' => $schema->string()->description('Id (uuid) da categoria.'),
            'search' => $schema->string()->description('Busca na descrição.'),
            'page' => $schema->integer()->min(1)->description('Página (padrão 1).'),
            'per_page' => $schema->integer()->min(1)->max(100)->description('Itens por página (padrão 15, máx. 100).'),
        ];
    }
}
