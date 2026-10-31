<?php

namespace App\Modules\Financial\Mcp\Tools\Recurrences;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Http\Resources\FinancialRecurrenceResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Services\RecurrenceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListRecurrencesTool extends FinancialTool
{
    protected string $name = 'list_recurrences';

    protected string $title = 'Listar recorrências';

    protected string $description = 'Lista as contas recorrentes (modelos que geram contas a pagar periodicamente), com busca pela descrição.';

    public function __construct(private readonly RecurrenceService $service) {}

    protected function permission(): Permission
    {
        return Permission::RECURRENCE_READ;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $paginator = $this->service->paginate(
            $this->perPage($request),
            $request->string('search')->toString() ?: null,
            $this->page($request),
        );

        return $this->paginated(FinancialRecurrenceResource::collection($paginator), $paginator);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Busca na descrição.'),
            'page' => $schema->integer()->min(1)->description('Página (padrão 1).'),
            'per_page' => $schema->integer()->min(1)->max(100)->description('Itens por página (padrão 15, máx. 100).'),
        ];
    }
}
