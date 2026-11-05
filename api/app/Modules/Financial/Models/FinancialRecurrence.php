<?php

namespace App\Modules\Financial\Models;

use App\Modules\Financial\Enums\RecurrenceFrequency;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Database\Factories\FinancialRecurrenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialRecurrence extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<FinancialRecurrenceFactory> */
    use HasFactory;

    use HasUuid;
    use SoftDeletes;

    protected $table = 'financial_recurrences';

    protected $fillable = [
        'description',
        'default_value',
        'supplier_id',
        'category_id',
        'due_day',
        'frequency',
        'active',
        'generate_automatically',
        'last_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'default_value' => 'decimal:2',
            'due_day' => 'integer',
            'active' => 'boolean',
            'generate_automatically' => 'boolean',
            'last_generated_at' => 'date',
            'frequency' => RecurrenceFrequency::class,
            'auto_pay_enabled' => 'boolean',
            'auto_pay_max_value' => 'decimal:2',
            'auto_pay_approved_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function autoPayApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auto_pay_approved_by');
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
        return $this->hasMany(Payable::class, 'recurrence_id');
    }

    protected static function newFactory(): FinancialRecurrenceFactory
    {
        return FinancialRecurrenceFactory::new();
    }
}
