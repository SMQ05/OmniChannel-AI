<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $table = 'plans';

    protected $fillable = [
        'code',
        'name',
        'description',
        'included_quotas',
        'feature_flags',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'included_quotas' => 'array',
            'feature_flags' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(BusinessSubscription::class);
    }
}
