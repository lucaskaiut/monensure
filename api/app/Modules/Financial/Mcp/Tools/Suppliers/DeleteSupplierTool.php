<?php

namespace App\Modules\Financial\Mcp\Tools\Suppliers;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Models\Supplier;
use App\Modules\Financial\Services\SupplierService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteSupplierTool extends FinancialTool
{
    protected string $name = 'delete_supplier';

    protected string $title = 'Excluir fornecedor';

    protected string $description = 'Exclui um fornecedor. Ação destrutiva: confirme com o usuário antes de executar.';

    public function __construct(private readonly SupplierService $service) {}

    protected function permission(): Permission
    {
        return Permission::SUPPLIER_DELETE;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $request->validate(['id' => ['required', 'string']]);

        $supplier = Supplier::query()->where('uuid', $request->get('id'))->first();

        if ($supplier === null) {
            return $this->notFound('Fornecedor');
        }

        $this->service->delete($supplier);

        return $this->success('Fornecedor removido com sucesso.', ['id' => $supplier->uuid]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Id (uuid) do fornecedor.')->required(),
        ];
    }
}
