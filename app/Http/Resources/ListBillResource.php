<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ListBillResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'value' => $this->category->id,
                'label' => $this->category->name,
                'icon' => $this->category->icon
            ],
            'supplier' => [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
                'value' => $this->supplier->id,
                'label' => $this->supplier->name,
            ],
            'description' => $this->description,
            'amount' => $this->amount,
            'due_at' => $this->due_at,
            'reference_at' => $this->reference_at,
            'is_paid' => $this->is_paid,
            'type' => $this->type,
        ];
    }
}
