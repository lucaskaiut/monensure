<?php

namespace App\Modules\Financial\Mcp\Tools\Payables;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\DTOs\UpdatePayableDTO;
use App\Modules\Financial\Enums\PayableEditScope;
use App\Modules\Financial\Http\Requests\UpdatePayableRequest;
use App\Modules\Financial\Http\Resources\PayableResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Services\PayableService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class UpdatePayableTool extends FinancialTool
{
    protected string $name = 'update_payable';

    protected string $title = 'Atualizar conta a pagar';

    protected string $description = 'Atualiza uma conta a pagar. Envie apenas os campos a alterar. Para parcelas, "scope" define o alcance: "this" (só esta, padrão), "this_and_next" (esta e as seguintes) ou "all" (todas do parcelamento). A data de vencimento só é alterada com scope="this".';

    public function __construct(private readonly PayableService $service) {}

    protected function permission(): Permission
    {
        return Permission::PAYABLE_UPDATE;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            ...(new UpdatePayableRequest)->rules(),
        ]);

        $payable = Payable::query()->where('uuid', $validated['id'])->first();

        if ($payable === null) {
            return $this->notFound('Conta a pagar');
        }

        $targets = $this->service->update($payable, UpdatePayableDTO::fromArray($validated), $this->actor());

        $targets->each(fn (Payable $target) => $target->load('supplier', 'category'));

        return $this->success('Conta(s) atualizada(s) com sucesso.', [
            'updated' => $targets->count(),
            'payables' => $this->resource(PayableResource::collection($targets)),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Id (uuid) da conta a pagar.')->required(),
            'scope' => $schema->string()->enum(PayableEditScope::values())->description('Alcance em parcelamentos: this (padrão), this_and_next, all.'),
            'description' => $schema->string()->max(255)->description('Nova descrição.'),
            'value' => $schema->number()->min(0.01)->description('Novo valor em reais.'),
            'due_date' => $schema->string()->format('date')->description('Novo vencimento (YYYY-MM-DD) — somente com scope=this.'),
            'issue_date' => $schema->string()->format('date')->description('Nova data de emissão (YYYY-MM-DD).'),
            'supplier_id' => $schema->string()->description('Id (uuid) do novo fornecedor.'),
            'category_id' => $schema->string()->description('Id (uuid) da nova categoria.'),
        ];
    }
}
