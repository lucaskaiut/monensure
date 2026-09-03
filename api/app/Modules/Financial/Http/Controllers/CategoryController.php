<?php

namespace App\Modules\Financial\Http\Controllers;

use App\Modules\Financial\Http\Requests\StoreCategoryRequest;
use App\Modules\Financial\Http\Requests\UpdateCategoryRequest;
use App\Modules\Financial\Http\Resources\CategoryResource;
use App\Modules\Financial\Models\Category;
use App\Modules\Financial\Services\CategoryService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends ApiController
{
    public function __construct(private readonly CategoryService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $categories = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
        );

        return $this->paginated(CategoryResource::collection($categories));
    }

    public function all(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        return $this->success(CategoryResource::collection($this->service->all()));
    }

    public function tree(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        return $this->success(CategoryResource::collection($this->service->tree()));
    }

    public function show(Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        return $this->success(CategoryResource::make($category->load('parent', 'children')));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = $this->service->create($request->validated());

        return $this->created(CategoryResource::make($category), 'Categoria criada com sucesso.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category = $this->service->update($category, $request->validated());

        return $this->success(CategoryResource::make($category), 'Categoria atualizada com sucesso.');
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $this->service->delete($category);

        return $this->success(null, 'Categoria removida com sucesso.');
    }
}
