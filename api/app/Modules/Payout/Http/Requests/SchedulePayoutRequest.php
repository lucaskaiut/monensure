<?php

namespace App\Modules\Payout\Http\Requests;

class SchedulePayoutRequest extends PreviewPayoutRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'value' => ['nullable', 'numeric', 'min:0.01', 'max:9999999'],
            'scheduled_for' => ['nullable', 'date'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'value' => 'valor',
            'scheduled_for' => 'data e hora do pagamento',
            'password' => 'senha',
        ];
    }
}
