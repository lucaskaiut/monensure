<?php

namespace App\Http\Validators;

use App\Interfaces\ValidatorInterface;
use App\Traits\Validator;
use Illuminate\Validation\Rule;

final class UserValidator implements ValidatorInterface
{
    use Validator;

    public function __construct()
    {

        $rules = [
            'firstname' => [
                Rule::requiredIf(!request()->id),
                'min:4',
                'max:128'
            ],
            'lastname' => [
                Rule::requiredIf(!request()->id),
                'min:4',
                'max:128'
            ],
            'email' => [
                Rule::requiredIf(!request()->id),
                Rule::unique('users', 'email')->ignore(request()->id),
                'email'
            ],
            'password' => [
                function($attribute, $value, $fail){
                    if(request()->id)
                        $fail("O campo senha não é permitido na atualização de um usuário");
                },
                'min:4',
                'max:32',
                'confirmed'
            ],
            'phone' => [
                'required',
                'min:11',
                'max:11',
            ],
        ];

        $messages = [
            'firstname.required' => 'O nome é obrigatório',
            'firstname.min' => 'O nome precisa ter entre 4 e 128 caracteres.',
            'firstname.max' => 'O nome precisa ter entre 4 e 128 caracteres',
            'lastname.required' => 'O sobrenome é obrigatório',
            'lastname.min' => 'O sobrenome precisa ter entre 4 e 128 caracteres.',
            'lastname.max' => 'O sobrenome precisa ter entre 4 e 128 caracteres',
            'email.unique' => 'Este E-Mail já está sendo utilizado.',
            'email.required' => 'O E-Mail é obrigatório.',
            'email.email' => 'O E-Mail deve ter um formato válido.',
            'password.required' => 'O campo senha é obrigatório.',
            'password.min' => 'O campo senha deve ter entre 4 e 32 caracteres.',
            'password.max' => 'O campo senha deve ter entre 4 e 32 caracteres.',
            'password.confirmed' => 'O campo senha precisa de confirmação.',
            'required' => 'O campo telefone é obrigatório',
            'phone.min' => 'O campo  telefone deve ter 11 caracteres',
            'phone.max' => 'O campo  telefone deve ter 11 caracteres',
        ];

        $this->setRules($rules);

        $this->setMessages($messages);
    }
}