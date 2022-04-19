<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BillResource extends JsonResource
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
            'category' => new CategoryResource($this->category),
            'group' => new GroupResource($this->group),
            'supplier' => new SupplierResource($this->supplier),
            'reference_at' => $this->reference_at,
            'description' => $this->description,
            'amount' => $this->amount,
            'due_at' => $this->due_at,
            'original_due_at' => $this->original_due_at,
            'is_paid' => $this->is_paid,
            'is_credit_card' => $this->is_credit_card,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
