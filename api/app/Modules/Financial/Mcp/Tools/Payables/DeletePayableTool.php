<?php

namespace App\Modules\Financial\Mcp\Tools\Payables;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Services\PayableService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeletePayableTool extends FinancialTool
{
    protected string $name = 'delete_payable';

    protected string $title = 'Excluir conta a pagar';

    protected string $description = 'Exclui uma conta a pagar. Prefira cancel_payable para manter o histórico. Ação destrutiva: confirme com o usuário antes de executar.';

    public function __construct(private readonly PayableService $service) {}

    protected function permission(): Permission
    {
        return Permission::PAYABLE_DELETE;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $request->validate(['id' => ['required', 'string']]);

        $payable = Payable::query()->where('uuid', $request->get('id'))->first();

        if ($payable === null) {
            return $this->notFound('Conta a pagar');
        }

        $this->service->destroy($payable, $this->actor());

        return $this->success('Conta a pagar removida com sucesso.', ['id' => $payable->uuid]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Id (uuid) da conta a pagar.')->required(),
        ];
    }
}
