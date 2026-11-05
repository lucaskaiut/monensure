<?php

namespace App\Modules\Payout\Models;

use App\Modules\Financial\Models\FinancialRecurrence;
use App\Modules\Financial\Models\Payable;
use App\Modules\Payout\Enums\PayoutMethod;
use App\Modules\Payout\Enums\PayoutStatus;
use App\Modules\Payout\Enums\PixKeyType;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Database\Factories\PayoutOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ordem de pagamento de uma conta via Asaas (boleto, PIX copia e cola ou chave PIX).
 *
 * @property PayoutMethod $method
 * @property PayoutStatus $status
 */
class PayoutOrder extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PayoutOrderFactory> */
    use HasFactory;

    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'payable_id',
        'recurrence_id',
        'created_by',
        'method',
        'status',
        'value',
        'paid_value',
        'payment_code',
        'pix_key_type',
        'beneficiary_name',
        'beneficiary_document',
        'preview',
        'scheduled_for',
        'submitted_at',
        'authorized_at',
        'settled_at',
        'external_id',
        'external_transfer_id',
        'external_status',
        'receipt_url',
        'failure_reason',
        'active_lock',
    ];

    protected function casts(): array
    {
        return [
            'method' => PayoutMethod::class,
            'status' => PayoutStatus::class,
            'pix_key_type' => PixKeyType::class,
            'value' => 'decimal:2',
            'paid_value' => 'decimal:2',
            'preview' => 'array',
            'scheduled_for' => 'datetime',
            'submitted_at' => 'datetime',
            'authorized_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class)->withTrashed();
    }

    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(FinancialRecurrence::class, 'recurrence_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PayoutOrderEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PayoutOrderEvent::class)->orderBy('id');
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    protected static function newFactory(): PayoutOrderFactory
    {
        return PayoutOrderFactory::new();
    }

    public function record(string $type, string $message, ?array $data = null): void
    {
        $this->events()->create([
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'created_at' => now(),
        ]);
    }
}
