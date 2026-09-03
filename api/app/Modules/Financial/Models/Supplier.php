<?php

namespace App\Modules\Financial\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'document',
        'phone',
        'email',
        'observations',
    ];

    /**
     * @return HasMany<Payable, $this>
     */
    public function payables(): HasMany
    {
        return $this->hasMany(Payable::class);
    }

    protected static function newFactory(): SupplierFactory
    {
        return SupplierFactory::new();
    }
}
