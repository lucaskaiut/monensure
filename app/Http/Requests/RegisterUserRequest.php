<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'firstname' => [
                'required',
                'min:4',
                'max:128'
            ],
            'lastname' => [
                'required',
                'min:4',
                'max:128'
            ],
            'email' => [
                'required',
                Rule::unique('users', 'email'),
                'email'
            ],
            'password' => [
                'required',
                'min:4',
                'max:32',
                'confirmed'
            ],
            'phone' => [
                'required',
                'min:11',
                'max:11',
            ],
            'group_name' => [
                'required',
                'min:4',
                'max:64'
            ]
        ];
    }

    public function messages(): array
    {
        return [
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
            'phone.min' => 'O campo  telefone deve ter 11 caracteres',
            'phone.max' => 'O campo  telefone deve ter 11 caracteres',
            'group_name.required' => 'O nome do grupo é obrigatório',
            'group_name.min' => 'O nome do grupo precisa ter entre 4 e 128 caracteres.',
            'group_name.max' => 'O nome do grupo precisa ter entre 4 e 128 caracteres',
        ];
    }
}
