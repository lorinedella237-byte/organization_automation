<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL is not transactional; allow retry after a later table creation fails.
        if (! Schema::hasTable('organization_invitations')) {
            Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('email');
            $table->char('token_hash', 64)->unique();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('invited_by_membership_id')->nullable();
            $table->foreignId('platform_actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('expires_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'email']);
            $table->foreign(['organization_id', 'role_id'], 'invitation_role_fk')->references(['organization_id', 'id'])->on('roles')->restrictOnDelete();
            $table->foreign(['organization_id', 'invited_by_membership_id'], 'invitation_actor_fk')->references(['organization_id', 'id'])->on('organization_memberships')->restrictOnDelete();
            });
        }
        Schema::table('organization_invitations', function (Blueprint $table) {
            $table->timestamp('expires_at')->useCurrent()->change();
        });
        Schema::create('workspace_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('organization_membership_id');
            $table->string('event_key', 150);
            $table->string('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['organization_membership_id', 'event_key'], 'workspace_notification_event_unique');
            $table->foreign(['organization_id', 'organization_membership_id'], 'notification_member_fk')->references(['organization_id', 'id'])->on('organization_memberships')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('organization_invitations')->exists()
            || \Illuminate\Support\Facades\DB::table('workspace_notifications')->exists()) {
            throw new RuntimeException('Invitation rollback requires empty tables; restore a verified backup to preserve history.');
        }
        Schema::dropIfExists('workspace_notifications');
        Schema::dropIfExists('organization_invitations');
    }
};
