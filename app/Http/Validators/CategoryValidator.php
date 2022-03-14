<?php

namespace App\Http\Validators;

use App\Interfaces\ValidatorInterface;
use App\Traits\Validator;
use Illuminate\Validation\Rule;

final class CategoryValidator implements ValidatorInterface
{
    use Validator;

    public function __construct()
    {

        $rules = [
            'name' => [
                Rule::requiredIf(!request()->id),
                'min:4',
                'max:128'
            ],
            'description' => [
                'min:4',
                'max:1024'
            ],
        ];

        $messages = [
            'name.required' => 'O nome é obrigatório',
            'name.min' => 'O nome precisa ter entre 4 e 128 caracteres.',
            'name.max' => 'O nome precisa ter entre 4 e 128 caracteres',
            'description.min' => 'O descrição precisa ter entre 4 e 1024 caracteres.',
            'description.max' => 'O descrição precisa ter entre 4 e 1024 caracteres',
        ];

        $this->setRules($rules);

        $this->setMessages($messages);
    }
}