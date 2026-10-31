<?php

namespace App\Modules\Financial\Mcp\Tools\Suppliers;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Http\Resources\SupplierResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Services\SupplierService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListSuppliersTool extends FinancialTool
{
    protected string $name = 'list_suppliers';

    protected string $title = 'Listar fornecedores';

    protected string $description = 'Lista os fornecedores do tenant (paginado). Permite buscar por nome, documento (CPF/CNPJ) ou e-mail.';

    public function __construct(private readonly SupplierService $service) {}

    protected function permission(): Permission
    {
        return Permission::SUPPLIER_READ;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $paginator = $this->service->paginate(
            $this->perPage($request),
            $request->string('search')->toString() ?: null,
            $this->page($request),
        );

        return $this->paginated(SupplierResource::collection($paginator), $paginator);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Termo de busca (nome, documento ou e-mail).'),
            'page' => $schema->integer()->min(1)->description('Página (padrão 1).'),
            'per_page' => $schema->integer()->min(1)->max(100)->description('Itens por página (padrão 15, máx. 100).'),
        ];
    }
}
