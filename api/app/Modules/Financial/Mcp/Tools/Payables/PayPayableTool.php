<?php

namespace App\Modules\Financial\Mcp\Tools\Payables;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\DTOs\PayPayableDTO;
use App\Modules\Financial\Http\Requests\PayPayableRequest;
use App\Modules\Financial\Http\Resources\PayableResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Services\PayableService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class PayPayableTool extends FinancialTool
{
    protected string $name = 'pay_payable';

    protected string $title = 'Registrar pagamento';

    protected string $description = 'Registra o pagamento de uma conta a pagar (marca como "pago"). paid_at padrão: hoje; paid_value padrão: valor da conta. Valor pago diferente do original é aceito e a diferença (juros/desconto) fica registrada. Confirme com o usuário antes de executar.';

    public function __construct(private readonly PayableService $service) {}

    protected function permission(): Permission
    {
        return Permission::PAYABLE_PAY;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $request->validate(['id' => ['required', 'string']]);

        $payable = Payable::query()->where('uuid', $request->get('id'))->first();

        if ($payable === null) {
            return $this->notFound('Conta a pagar');
        }

        $request->merge([
            'paid_at' => $request->get('paid_at') ?? now()->toDateString(),
            'paid_value' => $request->get('paid_value') ?? $payable->value,
        ]);

        $data = $request->validate((new PayPayableRequest)->rules());

        $payable = $this->service->pay($payable, PayPayableDTO::fromArray(Arr::except($data, 'id')), $this->actor());

        return $this->success('Pagamento registrado com sucesso.', [
            'payable' => $this->resource(PayableResource::make($payable)),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Id (uuid) da conta a pagar.')->required(),
            'paid_at' => $schema->string()->format('date')->description('Data do pagamento (YYYY-MM-DD). Padrão: hoje.'),
            'paid_value' => $schema->number()->min(0)->description('Valor efetivamente pago. Padrão: valor da conta.'),
            'notes' => $schema->string()->max(255)->description('Observação do pagamento.'),
        ];
    }
}
