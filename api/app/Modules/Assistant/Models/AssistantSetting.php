<?php

namespace App\Modules\Assistant\Models;

use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AssistantSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'enabled',
        'endpoint',
        'api_key',
        'model',
        'temperature',
        'max_tokens',
        'additional_prompt',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'api_key' => 'encrypted',
            'temperature' => 'float',
            'max_tokens' => 'integer',
        ];
    }
}
