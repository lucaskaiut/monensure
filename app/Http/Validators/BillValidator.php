<?php

namespace App\Http\Validators;

use App\Interfaces\ValidatorInterface;
use App\Traits\Validator;
use Illuminate\Validation\Rule;

final class BillValidator implements ValidatorInterface
{
    use Validator;

    public function __construct()
    {

        $rules = [
            'supplier_id' => [
                Rule::requiredIf(!request()->id),
                'uuid'
            ],
            'category_id' => [
                Rule::requiredIf(!request()->id),
                'uuid'
            ],
            'reference_at' => [
                'date',
            ],
            'due_at' => [
                'date',
            ],
            'original_due_at' => [
                'date',
            ],
            'description' => [
                'min:4',
                'max:128'
            ],
            'amount' => [
                Rule::requiredIf(!request()->id),
                'numeric',
            ],
            'is_paid' => [
                'boolean',
            ],
            'is_credit_card' => [
                'boolean',
            ],
            'type' => [
                'required',
                Rule::in(['pay', 'receive']),
            ],
            'installments' => [
                'numeric',
                'sometimes',
                'nullable',
            ],
        ];

        $messages = [
            'supplier_id.required' => 'O fornecedor é obrigatório',
            'supplier_id.uuid' => 'O fornecedor precisa ter um formato válido',
            'category_id.required' => 'A categoria é obrigatório',
            'category_id.uuid' => 'A categoria precisa ter um formato válido',
            'reference_at.date' => 'A data de referência precisa ter uma formato de data válido.',
            'due_at.date' => 'O vencimento precisa ter uma formato de data válido.',
            'original_at.date' => 'O vencimento original precisa ter uma formato de data válido.',
            'description.min' => 'Caso informada, a descrição precisa ter entre 4 e 128 caracteres.',
            'description.max' => 'Caso informada, a descrição precisa ter entre 4 e 128 caracteres.',
            'amount.required' => 'O valor é obrigatório.',
            'amount.numeric' => 'O valor precisa ter um formato válido.',
            'is_paid.boolean' => 'O campo pago precisa ter um formato válido.',
            'is_credit_card.boolean' => 'O campo cartão de crédito precisa ter um formato válido.'
        ];

        $this->setRules($rules);

        $this->setMessages($messages);
    }
}