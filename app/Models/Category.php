<?php

namespace App\Models;

use App\Scopes\GroupScope;
use App\Traits\UuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory, UuidKey;

    public $incrementing = false;
    protected $guarded = [];
    protected $keyType = 'string';

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    protected static function booted()
    {
        static::addGlobalScope(new GroupScope(auth('sanctum')->user()));
    }
}
