<?php

namespace App\Modules\Financial\Mcp\Tools\Recurrences;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Http\Resources\FinancialRecurrenceResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Models\FinancialRecurrence;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetRecurrenceTool extends FinancialTool
{
    protected string $name = 'get_recurrence';

    protected string $title = 'Detalhar recorrência';

    protected string $description = 'Retorna uma recorrência pelo id (uuid), com fornecedor e categoria.';

    protected function permission(): Permission
    {
        return Permission::RECURRENCE_READ;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $request->validate(['id' => ['required', 'string']]);

        $recurrence = FinancialRecurrence::query()->where('uuid', $request->get('id'))->first();

        if ($recurrence === null) {
            return $this->notFound('Recorrência');
        }

        return Response::structured($this->resource(FinancialRecurrenceResource::make($recurrence->load('supplier', 'category'))));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Id (uuid) da recorrência.')->required(),
        ];
    }
}
