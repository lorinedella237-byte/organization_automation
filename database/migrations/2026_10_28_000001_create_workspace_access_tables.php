<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('status', 20)->default('active')->index();
            $table->string('timezone', 80)->default('UTC');
            $table->char('currency', 3)->default('NGN');
            $table->string('contact_email')->nullable();
            $table->text('address')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
        foreach (['departments', 'teams'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->restrictOnDelete();
                $table->string('name', 100);
                $table->timestamps();
                $table->unique(['organization_id', 'name']);
                $table->unique(['organization_id', 'id']);
            });
        }
        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('employee_number', 80)->nullable();
            $table->string('title', 150)->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('manager_membership_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
            $table->unique(['organization_id', 'employee_number']);
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'status']);
            $table->foreign(['organization_id', 'department_id'], 'membership_department_fk')->references(['organization_id', 'id'])->on('departments')->restrictOnDelete();
            $table->foreign(['organization_id', 'team_id'], 'membership_team_fk')->references(['organization_id', 'id'])->on('teams')->restrictOnDelete();
        });
        Schema::table('organization_memberships', function (Blueprint $table) {
            $table->foreign(['organization_id', 'manager_membership_id'], 'membership_manager_fk')->references(['organization_id', 'id'])->on('organization_memberships')->restrictOnDelete();
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $table->dropUnique(['code']);
            $table->unique(['organization_id', 'code']);
            $table->unique(['organization_id', 'id']);
        });
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('role_id');
            $table->foreignId('permission_id')->constrained()->restrictOnDelete();
            $table->string('scope', 30);
            $table->unique(['role_id', 'permission_id', 'scope']);
            $table->foreign(['organization_id', 'role_id'], 'role_permission_tenant_fk')->references(['organization_id', 'id'])->on('roles')->restrictOnDelete();
        });
        Schema::create('membership_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('organization_membership_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('assigned_by_membership_id')->nullable();
            $table->timestamp('created_at');
            $table->unique(['organization_membership_id', 'role_id']);
            $table->foreign(['organization_id', 'organization_membership_id'], 'membership_role_member_fk')->references(['organization_id', 'id'])->on('organization_memberships')->restrictOnDelete();
            $table->foreign(['organization_id', 'role_id'], 'membership_role_tenant_fk')->references(['organization_id', 'id'])->on('roles')->restrictOnDelete();
            $table->foreign(['organization_id', 'assigned_by_membership_id'], 'membership_role_actor_fk')->references(['organization_id', 'id'])->on('organization_memberships')->restrictOnDelete();
        });
        Schema::create('platform_administrators', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->restrictOnDelete();
            $table->timestamp('created_at');
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('actor_membership_id')->nullable();
            $table->foreign(['organization_id', 'actor_membership_id'], 'audit_membership_fk')->references(['organization_id', 'id'])->on('organization_memberships')->restrictOnDelete();
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        // Runtime organization data must be restored from backup, never silently dropped.
        if (\Illuminate\Support\Facades\DB::table('organizations')->exists()) {
            throw new RuntimeException('Workspace rollback requires an empty organization table. Restore a verified backup instead of deleting workspace history.');
        }
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(Schema::getConnection()->getDriverName() === 'sqlite' ? ['organization_id', 'actor_membership_id'] : 'audit_membership_fk');
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id', 'created_at']);
            $table->dropColumn(['organization_id', 'actor_membership_id']);
        });
        Schema::dropIfExists('platform_administrators');
        Schema::dropIfExists('membership_roles');
        Schema::dropIfExists('role_permissions');
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropUnique(['organization_id', 'code']);
            $table->dropUnique(['organization_id', 'id']);
            $table->dropColumn('organization_id');
            $table->unique('code');
        });
        Schema::table('organization_memberships', fn (Blueprint $table) => $table->dropForeign(Schema::getConnection()->getDriverName() === 'sqlite' ? ['organization_id', 'manager_membership_id'] : 'membership_manager_fk'));
        Schema::dropIfExists('organization_memberships');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('organizations');
    }
};
