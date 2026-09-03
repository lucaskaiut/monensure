<?php

namespace App\Modules\Financial\Services;

use App\Modules\Financial\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return Category::query()
            ->with('parent:id,uuid,name')
            ->when(filled($search), fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Lista completa (para selects).
     *
     * @return Collection<int, Category>
     */
    public function all(): Collection
    {
        return Category::query()->orderBy('name')->get();
    }

    /**
     * Árvore de categorias (pais com filhos).
     *
     * @return Collection<int, Category>
     */
    public function tree(): Collection
    {
        $categories = $this->all()->keyBy('id');

        $roots = $categories->filter(fn (Category $category) => $category->parent_id === null);

        $roots->each(function (Category $root) use ($categories): void {
            $root->setRelation('children', $this->childrenOf($root, $categories));
        });

        return $roots->values();
    }

    /**
     * @param  array{name: string, parent_id?: ?string}  $data
     */
    public function create(array $data): Category
    {
        return Category::query()->create([
            'name' => $data['name'],
            'parent_id' => $this->resolveParentId($data['parent_id'] ?? null),
        ]);
    }

    /**
     * @param  array{name?: string, parent_id?: ?string}  $data
     */
    public function update(Category $category, array $data): Category
    {
        $parentId = array_key_exists('parent_id', $data)
            ? $this->resolveParentId($data['parent_id'])
            : $category->parent_id;

        if ($parentId !== null && $parentId === $category->getKey()) {
            throw ValidationException::withMessages([
                'parent_id' => 'A categoria não pode ser pai de si mesma.',
            ]);
        }

        $category->fill([
            ...(array_key_exists('name', $data) ? ['name' => $data['name']] : []),
            ...(array_key_exists('parent_id', $data) ? ['parent_id' => $parentId] : []),
        ]);
        $category->save();

        return $category->refresh();
    }

    public function delete(Category $category): void
    {
        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Não é possível excluir uma categoria que possui subcategorias.',
            ]);
        }

        if ($category->payables()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Não é possível excluir uma categoria com contas vinculadas.',
            ]);
        }

        $category->delete();
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, Category>
     */
    private function childrenOf(Category $parent, Collection $categories): Collection
    {
        return $categories
            ->filter(fn (Category $category) => $category->parent_id === $parent->getKey())
            ->values();
    }

    private function resolveParentId(?string $uuid): ?int
    {
        return filled($uuid)
            ? Category::query()->where('uuid', $uuid)->value('id')
            : null;
    }
}
