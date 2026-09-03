<?php

namespace App\Modules\Financial\Http\Resources;

use App\Modules\Financial\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'parent_id' => $this->parent?->uuid,
            'parent' => $this->whenLoaded('parent', fn () => self::make($this->parent)),
            'children' => $this->whenLoaded('children', fn () => self::collection($this->children)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
