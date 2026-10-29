<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name', 180);
            $table->string('code', 40)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('planned')->index();
            $table->foreignId('department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->foreignId('owner_membership_id')->constrained('organization_memberships')->restrictOnDelete();
            $table->foreignId('lead_membership_id')->nullable()->constrained('organization_memberships')->restrictOnDelete();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained('organization_memberships')->restrictOnDelete();
            $table->string('role', 50)->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'membership_id']);
            $table->unique(['organization_id', 'id']);
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->date('due_on')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();
            $table->index(['organization_id', 'project_id']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignee_membership_id')->nullable()->constrained('organization_memberships')->nullOnDelete();
            $table->string('title', 220);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('todo')->index();
            $table->string('priority', 20)->default('normal');
            $table->date('due_on')->nullable()->index();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['organization_id', 'project_id', 'status']);
        });

        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('label', 255);
            $table->boolean('completed')->default(false);
            $table->foreignId('completed_by_membership_id')->nullable()->constrained('organization_memberships')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained('organization_memberships')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('daily_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_id')->constrained('organization_memberships')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->date('activity_date')->index();
            $table->string('title', 180);
            $table->text('narrative')->nullable();
            $table->text('blockers')->nullable();
            $table->unsignedSmallInteger('progress')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->index(['organization_id', 'membership_id', 'activity_date'], 'daily_activities_org_member_date_idx');
        });

        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_id')->constrained('organization_memberships')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date')->index();
            $table->decimal('hours', 8, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->text('comment')->nullable();
            $table->foreignId('approved_by_membership_id')->nullable()->constrained('organization_memberships')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name', 180);
            $table->string('code', 60);
            $table->string('period', 20)->default('weekly');
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('template_id')->constrained('report_templates')->restrictOnDelete();
            $table->foreignId('membership_id')->constrained('organization_memberships')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'template_id', 'membership_id', 'period_start', 'period_end'], 'reports_period_unique');
        });

        Schema::create('report_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('created_by_membership_id')->constrained('organization_memberships')->restrictOnDelete();
            $table->json('snapshot');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['report_id', 'version']);
        });

        Schema::create('report_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_membership_id')->constrained('organization_memberships')->restrictOnDelete();
            $table->string('decision', 20);
            $table->text('comment')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['report_reviews', 'report_versions', 'reports', 'report_templates', 'work_logs', 'daily_activities', 'task_comments', 'task_checklist_items', 'tasks', 'milestones', 'project_members', 'projects'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
