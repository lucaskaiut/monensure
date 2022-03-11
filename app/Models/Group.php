<?php

namespace App\Models;

use App\Traits\UuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory, UuidKey;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function suppliers()
    {
        return $this->hasMany(Supplier::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

}
