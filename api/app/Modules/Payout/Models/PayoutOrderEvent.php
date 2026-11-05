<?php

namespace App\Modules\Payout\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linha do tempo de uma ordem (agendamento, envio, autorização, retorno do Asaas).
 * Registra também o que acontece fora de requisições de usuário (jobs e webhooks).
 */
class PayoutOrderEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'payout_order_id',
        'type',
        'message',
        'data',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PayoutOrder::class, 'payout_order_id');
    }
}
