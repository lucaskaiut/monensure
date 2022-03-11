<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

trait UuidKey
{
    public static function bootUuidKey()
    {
        static::creating(function (Model $model) {
            $model->id = Uuid::uuid4();
        });
    }
}