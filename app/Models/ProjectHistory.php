<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectHistory extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;

    protected $table = 'project_history';

    protected $guarded = ['id'];

    protected $casts = [
        'before_values' => 'array',
        'after_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function actor(): BelongsTo { return $this->belongsTo(OrganizationMembership::class, 'actor_membership_id'); }
}
