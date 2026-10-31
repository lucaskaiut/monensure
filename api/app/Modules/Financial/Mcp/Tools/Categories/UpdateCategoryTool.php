<?php

namespace App\Modules\Financial\Mcp\Tools\Categories;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Http\Requests\UpdateCategoryRequest;
use App\Modules\Financial\Http\Resources\CategoryResource;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Models\Category;
use App\Modules\Financial\Services\CategoryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UpdateCategoryTool extends FinancialTool
{
    protected string $name = 'update_category';

    protected string $title = 'Atualizar categoria';

    protected string $description = 'Renomeia uma categoria e/ou altera a categoria pai. Envie parent_id=null para torná-la raiz.';

    public function __construct(private readonly CategoryService $service) {}

    protected function permission(): Permission
    {
        return Permission::CATEGORY_UPDATE;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            ...(new UpdateCategoryRequest)->rules(),
        ]);

        $category = Category::query()->where('uuid', $validated['id'])->first();

        if ($category === null) {
            return $this->notFound('Categoria');
        }

        $category = $this->service->update($category, Arr::except($validated, 'id'));

        return $this->success('Categoria atualizada com sucesso.', [
            'category' => $this->resource(CategoryResource::make($category)),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Id (uuid) da categoria.')->required(),
            'name' => $schema->string()->max(255)->description('Novo nome.'),
            'parent_id' => $schema->string()->nullable()->description('Id (uuid) da nova categoria pai, ou null para raiz.'),
        ];
    }
}
