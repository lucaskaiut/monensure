<?php

namespace App\Modules\Financial\Models;

use App\Modules\Financial\Enums\PayableStatus;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\PayableFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payable extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PayableFactory> */
    use HasFactory;

    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'category_id',
        'recurrence_id',
        'installment_group_uuid',
        'installment_number',
        'installment_total',
        'issue_date',
        'due_date',
        'description',
        'value',
        'status',
        'paid_at',
        'paid_value',
    ];

    protected function casts(): array
    {
        return [
            'installment_number' => 'integer',
            'installment_total' => 'integer',
            'issue_date' => 'date',
            'due_date' => 'date',
            'value' => 'decimal:2',
            'paid_at' => 'date',
            'paid_value' => 'decimal:2',
            'status' => PayableStatus::class,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(FinancialRecurrence::class, 'recurrence_id');
    }

    /**
     * @return HasMany<PayablePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PayablePayment::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PayableStatus::Pending);
    }

    public function isInstallment(): bool
    {
        return filled($this->installment_group_uuid);
    }

    public function isPending(): bool
    {
        return $this->status === PayableStatus::Pending;
    }

    protected static function newFactory(): PayableFactory
    {
        return PayableFactory::new();
    }
}
