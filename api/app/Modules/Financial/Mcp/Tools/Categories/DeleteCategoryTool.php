<?php

namespace App\Modules\Financial\Mcp\Tools\Categories;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Mcp\Tools\FinancialTool;
use App\Modules\Financial\Models\Category;
use App\Modules\Financial\Services\CategoryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteCategoryTool extends FinancialTool
{
    protected string $name = 'delete_category';

    protected string $title = 'Excluir categoria';

    protected string $description = 'Exclui uma categoria. Falha se ela tiver subcategorias ou contas vinculadas. Ação destrutiva: confirme com o usuário antes de executar.';

    public function __construct(private readonly CategoryService $service) {}

    protected function permission(): Permission
    {
        return Permission::CATEGORY_DELETE;
    }

    protected function execute(Request $request): Response|ResponseFactory
    {
        $request->validate(['id' => ['required', 'string']]);

        $category = Category::query()->where('uuid', $request->get('id'))->first();

        if ($category === null) {
            return $this->notFound('Categoria');
        }

        $this->service->delete($category);

        return $this->success('Categoria removida com sucesso.', ['id' => $category->uuid]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Id (uuid) da categoria.')->required(),
        ];
    }
}
