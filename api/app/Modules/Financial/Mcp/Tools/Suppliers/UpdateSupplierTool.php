<?php

namespace App\Modules\Financial\Mcp\Tools\Suppliers;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Http\Requests\UpdateSupplierRequest;
use App\Modules\Financial\Http\Resources\SupplierResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Models\Supplier;
use App\Modules\Financial\Services\SupplierService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UpdateSupplierTool extends FinancialTool
{
    use NormalizesDocument;

    protected string $name = 'update_supplier';

    protected string $title = 'Atualizar fornecedor';

    protected string $description = 'Atualiza um fornecedor. Envie apenas os campos que devem ser alterados.';

    public function __construct(private readonly SupplierService $service) {}

    protected function permission(): Permission
    {
        return Permission::SUPPLIER_UPDATE;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $this->normalizeDocument($request);

        $validated = $request->validate([
            'id' => ['required', 'string'],
            ...(new UpdateSupplierRequest)->rules(),
        ]);

        $supplier = Supplier::query()->where('uuid', $validated['id'])->first();

        if ($supplier === null) {
            return $this->notFound('Fornecedor');
        }

        $supplier = $this->service->update($supplier, Arr::except($validated, 'id'));

        return $this->success('Fornecedor atualizado com sucesso.', [
            'supplier' => $this->resource(SupplierResource::make($supplier)),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Id (uuid) do fornecedor.')->required(),
            'name' => $schema->string()->max(255)->description('Nome do fornecedor.'),
            'document' => $schema->string()->nullable()->description('CPF ou CNPJ (com ou sem máscara). Envie null para remover.'),
            'phone' => $schema->string()->max(20)->nullable()->description('Telefone.'),
            'email' => $schema->string()->format('email')->nullable()->description('E-mail.'),
            'observations' => $schema->string()->nullable()->description('Observações livres.'),
        ];
    }
}
