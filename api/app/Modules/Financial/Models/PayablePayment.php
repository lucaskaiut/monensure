<?php

namespace App\Modules\Financial\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayablePayment extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'payable_id',
        'paid_at',
        'paid_value',
        'difference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'date',
            'paid_value' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class);
    }
}
