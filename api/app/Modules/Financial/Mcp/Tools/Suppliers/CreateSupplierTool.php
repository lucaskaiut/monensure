<?php

namespace App\Modules\Financial\Mcp\Tools\Suppliers;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Http\Requests\StoreSupplierRequest;
use App\Modules\Financial\Http\Resources\SupplierResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Services\SupplierService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class CreateSupplierTool extends FinancialTool
{
    use NormalizesDocument;

    protected string $name = 'create_supplier';

    protected string $title = 'Criar fornecedor';

    protected string $description = 'Cadastra um novo fornecedor. O documento (CPF ou CNPJ) é opcional e validado.';

    public function __construct(private readonly SupplierService $service) {}

    protected function permission(): Permission
    {
        return Permission::SUPPLIER_CREATE;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $this->normalizeDocument($request);

        $data = $request->validate((new StoreSupplierRequest)->rules());

        $supplier = $this->service->create($data);

        return $this->success('Fornecedor criado com sucesso.', [
            'supplier' => $this->resource(SupplierResource::make($supplier)),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->max(255)->description('Nome do fornecedor.')->required(),
            'document' => $schema->string()->description('CPF ou CNPJ (com ou sem máscara).'),
            'phone' => $schema->string()->max(20)->description('Telefone.'),
            'email' => $schema->string()->format('email')->description('E-mail.'),
            'observations' => $schema->string()->description('Observações livres.'),
        ];
    }
}
