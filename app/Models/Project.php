<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends \Illuminate\Database\Eloquent\Model
{
    protected $guarded = ['id'];
    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date'];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function owner(): BelongsTo { return $this->belongsTo(OrganizationMembership::class, 'owner_membership_id'); }
    public function lead(): BelongsTo { return $this->belongsTo(OrganizationMembership::class, 'lead_membership_id'); }
    public function members(): BelongsToMany { return $this->belongsToMany(OrganizationMembership::class, 'project_members', 'project_id', 'membership_id')->withPivot('role')->withTimestamps(); }
    public function milestones(): HasMany { return $this->hasMany(Milestone::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
    public function history(): HasMany { return $this->hasMany(ProjectHistory::class)->latest('created_at'); }
}
