<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
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
            'token' => [
                'required',
                'numeric'
            ],
            'password' => 'required|confirmed',
            'email' => 'required|email'
        ];
    }

    public function messages()
    {
        return [
            'token' => [
                'required' => 'O campo token é obrigatório',
                'min:6' => 'O campo token deve ter 6 caracteres',
                'max:6' => 'O campo token deve ter 6 caracteres',
                'numeric' => 'O campo token deve ser numérico'
            ],
            'password' => [
                'required' => 'O campo senha é obrigatório',
                'confirmed' => 'O campo confirmação de senha é obrigatório ou não combina'
            ],
            'email' => [
                'required' => 'O campo E-Mail é obrigatório',
                'email' => 'O campo E-Mail precisa ter um formato válido'
            ]
        ];
    }
}
