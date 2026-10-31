<?php

namespace App\Modules\Financial\Mcp\Tools\Categories;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Http\Resources\CategoryResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Services\CategoryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListCategoriesTool extends FinancialTool
{
    protected string $name = 'list_categories';

    protected string $title = 'Listar categorias';

    protected string $description = 'Lista as categorias financeiras do tenant. Use format="tree" para obter a hierarquia completa (pais com subcategorias) ou format="list" (padrão) para uma listagem paginada com busca por nome.';

    public function __construct(private readonly CategoryService $service) {}

    protected function permission(): Permission
    {
        return Permission::CATEGORY_READ;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $request->validate(['format' => ['nullable', 'in:list,tree']]);

        if ($request->get('format') === 'tree') {
            return Response::structured([
                'data' => $this->resource(CategoryResource::collection($this->service->tree())),
            ]);
        }

        $paginator = $this->service->paginate(
            $this->perPage($request),
            $request->string('search')->toString() ?: null,
            $this->page($request),
        );

        return $this->paginated(CategoryResource::collection($paginator), $paginator);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'format' => $schema->string()->enum(['list', 'tree'])->description('"list" (paginado, padrão) ou "tree" (hierarquia completa).'),
            'search' => $schema->string()->description('Busca por nome (apenas format=list).'),
            'page' => $schema->integer()->min(1)->description('Página (padrão 1).'),
            'per_page' => $schema->integer()->min(1)->max(100)->description('Itens por página (padrão 15, máx. 100).'),
        ];
    }
}
