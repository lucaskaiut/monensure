<?php

namespace App\Models;

use App\Scopes\GroupScope;
use App\Traits\UuidKey;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    use HasFactory, UuidKey;

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'category_id',
        'group_id',
        'supplier_id',
        'reference_at',
        'description',
        'amount',
        'due_at',
        'original_due_at',
        'is_paid',
        'is_credit_card',
        'type',
    ];
    
    protected $keyType = 'string';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    protected static function booted()
    {
        static::addGlobalScope(new GroupScope(auth('sanctum')->user()));
    }

    public function scopeCreatedAfter(Builder $query, $date): Builder
    {
        return $query->where('created_at', '>=', Carbon::parse($date));
    }

    public function scopeCreatedBefore(Builder $query, $date): Builder
    {
        return $query->where('created_at', '<=', Carbon::parse($date));
    }

    public function scopeReferenceAfter(Builder $query, $date): Builder
    {
        return $query->where('reference_at', '>=', Carbon::parse($date));
    }

    public function scopeReferenceBefore(Builder $query, $date): Builder
    {
        return $query->where('reference_at', '<=', Carbon::parse($date));
    }

    public function scopeDueAfter(Builder $query, $date): Builder
    {
        return $query->where('due_at', '>=', Carbon::parse($date));
    }

    public function scopeDueBefore(Builder $query, $date): Builder
    {
        return $query->where('due_at', '<=', Carbon::parse($date));
    }

    public function scopeOriginalDueAfter(Builder $query, $date): Builder
    {
        return $query->where('due_at', '>=', Carbon::parse($date));
    }

    public function scopeOriginalDueBefore(Builder $query, $date): Builder
    {
        return $query->where('due_at', '<=', Carbon::parse($date));
    }

}
