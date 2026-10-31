<?php

namespace App\Modules\Financial\Mcp\Tools\Categories;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Http\Resources\CategoryResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetCategoryTool extends FinancialTool
{
    protected string $name = 'get_category';

    protected string $title = 'Detalhar categoria';

    protected string $description = 'Retorna uma categoria pelo id (uuid), incluindo a categoria pai e as subcategorias.';

    protected function permission(): Permission
    {
        return Permission::CATEGORY_READ;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $request->validate(['id' => ['required', 'string']]);

        $category = Category::query()->where('uuid', $request->get('id'))->first();

        if ($category === null) {
            return $this->notFound('Categoria');
        }

        return Response::structured($this->resource(CategoryResource::make($category->load('parent', 'children'))));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Id (uuid) da categoria.')->required(),
        ];
    }
}
