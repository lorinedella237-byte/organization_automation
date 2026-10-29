<?php

namespace App\Services;

use App\Models\OrganizationMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditRecorder
{
    public function record(int $organizationId, ?OrganizationMembership $actor, string $action, string $entity, int $id, array $metadata = []): void
    {
        // Explicit scalar/ID fields only: never accept tokens, identity passwords or payroll/medical payloads.
        $safe = array_intersect_key($metadata, array_flip([
            'before_status', 'after_status', 'reason', 'role_ids', 'changed_fields', 'invitation_id',
        ]));
        DB::table('audit_logs')->insert([
            'uuid' => (string) Str::uuid(), 'organization_id' => $organizationId,
            'user_id' => $actor?->user_id, 'actor_membership_id' => $actor?->id,
            'event_type' => 'workspace', 'module' => 'identity', 'action' => $action,
            'entity_type' => $entity, 'entity_id' => $id,
            'metadata' => json_encode($safe, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }
}
