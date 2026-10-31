<?php

namespace App\Modules\Financial\Mcp\Tools\Suppliers;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Http\Resources\SupplierResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Models\Supplier;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetSupplierTool extends FinancialTool
{
    protected string $name = 'get_supplier';

    protected string $title = 'Detalhar fornecedor';

    protected string $description = 'Retorna os dados de um fornecedor pelo id (uuid).';

    protected function permission(): Permission
    {
        return Permission::SUPPLIER_READ;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $request->validate(['id' => ['required', 'string']]);

        $supplier = Supplier::query()->where('uuid', $request->get('id'))->first();

        if ($supplier === null) {
            return $this->notFound('Fornecedor');
        }

        return Response::structured($this->resource(SupplierResource::make($supplier)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Id (uuid) do fornecedor.')->required(),
        ];
    }
}
