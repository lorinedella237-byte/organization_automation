<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Existing protected templates predate the personal self-service baseline.
        // Preserve broader grants and never modify custom roles.
        $personal = [
            'staff.view' => 'own', 'projects.view' => 'assigned_project', 'tasks.view' => 'own',
            'activities.view' => 'own', 'activities.create' => 'own', 'reports.view' => 'own',
            'reports.create' => 'own', 'attendance.view' => 'own', 'attendance.clock_in' => 'own',
            'attendance.clock_out' => 'own', 'leave.view' => 'own', 'leave.request' => 'own',
            'payslips.view' => 'own', 'documents.view' => 'own', 'announcements.view' => 'organization',
        ];
        DB::transaction(function () use ($personal) {
            foreach (DB::table('roles')->whereNotNull('organization_id')->where('is_system_role', true)
                ->whereIn('code', ['organization_admin', 'hr_officer', 'finance_officer', 'manager'])->get() as $role) {
                foreach ($personal as $ability => $scope) {
                    $permissionId = DB::table('permissions')->where('code', $ability)->value('id');
                    if ($permissionId && ! DB::table('role_permissions')->where('role_id', $role->id)->where('permission_id', $permissionId)->exists()) {
                        DB::table('role_permissions')->insert([
                            'organization_id' => $role->organization_id, 'role_id' => $role->id,
                            'permission_id' => $permissionId, 'scope' => $scope,
                        ]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Data-only template upgrade. Restoring prior grants requires the verified pre-upgrade backup.
    }
};
