<?php

namespace App\Modules\Financial\Mcp\Tools\Payables;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Enums\PayableEditScope;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Services\PayableService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class CancelPayableTool extends FinancialTool
{
    protected string $name = 'cancel_payable';

    protected string $title = 'Cancelar conta a pagar';

    protected string $description = 'Cancela conta(s) a pagar pendente(s) (contas já pagas não são afetadas). Para parcelas, "scope" define o alcance: this (padrão), this_and_next ou all. Confirme com o usuário antes de executar.';

    public function __construct(private readonly PayableService $service) {}

    protected function permission(): Permission
    {
        return Permission::PAYABLE_UPDATE;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'scope' => ['nullable', 'string', Rule::enum(PayableEditScope::class)],
        ]);

        $payable = Payable::query()->where('uuid', $validated['id'])->first();

        if ($payable === null) {
            return $this->notFound('Conta a pagar');
        }

        $scope = filled($validated['scope'] ?? null) ? PayableEditScope::from($validated['scope']) : null;

        $affected = $this->service->cancel($payable, $scope, $this->actor());

        return $this->success('Conta(s) cancelada(s) com sucesso.', ['cancelled' => $affected]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Id (uuid) da conta a pagar.')->required(),
            'scope' => $schema->string()->enum(PayableEditScope::values())->description('Alcance em parcelamentos: this (padrão), this_and_next, all.'),
        ];
    }
}
