<?php

namespace App\Modules\Financial\Mcp\Tools\Categories;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Http\Requests\StoreCategoryRequest;
use App\Modules\Financial\Http\Resources\CategoryResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Services\CategoryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class CreateCategoryTool extends FinancialTool
{
    protected string $name = 'create_category';

    protected string $title = 'Criar categoria';

    protected string $description = 'Cria uma categoria financeira. Informe parent_id (uuid) para criar como subcategoria.';

    public function __construct(private readonly CategoryService $service) {}

    protected function permission(): Permission
    {
        return Permission::CATEGORY_CREATE;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $data = $request->validate((new StoreCategoryRequest)->rules());

        $category = $this->service->create($data);

        return $this->success('Categoria criada com sucesso.', [
            'category' => $this->resource(CategoryResource::make($category)),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->max(255)->description('Nome da categoria.')->required(),
            'parent_id' => $schema->string()->description('Id (uuid) da categoria pai (opcional).'),
        ];
    }
}
