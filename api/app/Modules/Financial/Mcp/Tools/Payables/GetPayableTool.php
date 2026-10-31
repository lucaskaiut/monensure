<?php

namespace App\Modules\Financial\Mcp\Tools\Payables;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Http\Resources\PayableResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Models\Payable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetPayableTool extends FinancialTool
{
    protected string $name = 'get_payable';

    protected string $title = 'Detalhar conta a pagar';

    protected string $description = 'Retorna uma conta a pagar pelo id (uuid), com fornecedor, categoria, dados de parcelamento e histórico de pagamentos.';

    protected function permission(): Permission
    {
        return Permission::PAYABLE_READ;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $request->validate(['id' => ['required', 'string']]);

        $payable = Payable::query()->where('uuid', $request->get('id'))->first();

        if ($payable === null) {
            return $this->notFound('Conta a pagar');
        }

        return Response::structured($this->resource(PayableResource::make($payable->load('supplier', 'category', 'payments'))));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Id (uuid) da conta a pagar.')->required(),
        ];
    }
}
