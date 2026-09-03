<?php

namespace App\Modules\Financial\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\PayableInstallmentGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayableInstallmentGroup extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PayableInstallmentGroupFactory> */
    use HasFactory;

    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'description',
        'total_value',
        'total_installments',
        'first_installment_number',
        'first_due_date',
        'supplier_id',
        'category_id',
    ];

    protected function casts(): array
    {
        return [
            'total_value' => 'decimal:2',
            'total_installments' => 'integer',
            'first_installment_number' => 'integer',
            'first_due_date' => 'date',
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

    /**
     * @return HasMany<Payable, $this>
     */
    public function payables(): HasMany
    {
        return $this->hasMany(Payable::class, 'installment_group_uuid', 'uuid');
    }

    protected static function newFactory(): PayableInstallmentGroupFactory
    {
        return PayableInstallmentGroupFactory::new();
    }
}
