<?php

namespace App\Http\Validators;

use App\Interfaces\ValidatorInterface;
use App\Traits\Validator;
use Illuminate\Validation\Rule;

final class SupplierValidator implements ValidatorInterface
{
    use Validator;

    public function __construct()
    {

        $rules = [
            'name' => [
                'required',
                'min:4',
                'max:128'
            ],
        ];

        $messages = [
            'name.required' => 'O nome é obrigatório',
            'name.min' => 'O nome precisa ter entre 4 e 128 caracteres.',
            'name.max' => 'O nome precisa ter entre 4 e 128 caracteres',
        ];

        $this->setRules($rules);

        $this->setMessages($messages);
    }
}