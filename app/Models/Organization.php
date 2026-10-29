<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['settings' => 'array'];

    protected static function booted(): void
    {
        static::creating(function (self $organization) {
            $organization->uuid ??= (string) Str::uuid();
        });
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }
}
