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
            'category' => $this->category->name,
            'supplier' => $this->supplier->name,
            'description' => $this->description,
            'amount' => $this->amount,
            'due_at' => $this->due_at,
            'is_paid' => $this->is_paid,
        ];
    }
}
