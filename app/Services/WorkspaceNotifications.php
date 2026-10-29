<?php

namespace App\Services;

use App\Models\OrganizationMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceNotifications
{
    public function record(OrganizationMembership $recipient, string $eventKey, string $message): void
    {
        DB::table('workspace_notifications')->insertOrIgnore([
            'id' => (string) Str::uuid(), 'organization_id' => $recipient->organization_id,
            'organization_membership_id' => $recipient->id, 'event_key' => $eventKey,
            'message' => $message, 'created_at' => now(),
        ]);
    }
}
