<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_calendars', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name', 120); $table->string('timezone', 80)->default('UTC');
            $table->json('weekly_schedule'); $table->unsignedSmallInteger('grace_minutes')->default(0); $table->time('cutoff_time')->nullable(); $table->boolean('is_default')->default(false); $table->timestamps();
        });
        Schema::create('organization_holidays', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete(); $table->date('holiday_date'); $table->string('name', 160); $table->timestamps(); $table->unique(['organization_id', 'holiday_date']);
        });
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete(); $table->foreignId('membership_id')->constrained('organization_memberships')->restrictOnDelete();
            $table->timestamp('clocked_in_at'); $table->timestamp('clocked_out_at')->nullable(); $table->date('local_date'); $table->unsignedInteger('duration_minutes')->nullable(); $table->string('status', 20)->default('open')->index(); $table->timestamps();
            $table->index(['organization_id', 'membership_id', 'local_date'], 'attendance_org_member_date_idx');
        });
        Schema::create('attendance_correction_requests', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete(); $table->foreignId('membership_id')->constrained('organization_memberships')->restrictOnDelete(); $table->foreignId('attendance_session_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('requested_clocked_in_at')->nullable(); $table->timestamp('requested_clocked_out_at')->nullable(); $table->text('reason'); $table->string('status', 20)->default('pending')->index(); $table->foreignId('decided_by_membership_id')->nullable()->constrained('organization_memberships')->nullOnDelete(); $table->timestamps();
        });
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete(); $table->foreignId('request_id')->constrained('attendance_correction_requests')->restrictOnDelete(); $table->foreignId('session_id')->constrained('attendance_sessions')->restrictOnDelete(); $table->json('before_values'); $table->json('after_values'); $table->foreignId('actor_membership_id')->constrained('organization_memberships')->restrictOnDelete(); $table->timestamp('created_at');
        });

        Schema::create('leave_types', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete(); $table->string('name', 100); $table->boolean('requires_reason')->default(true); $table->boolean('is_paid')->default(true); $table->boolean('is_active')->default(true); $table->timestamps(); $table->unique(['organization_id', 'name']); });
        Schema::create('leave_policies', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete(); $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete(); $table->decimal('annual_days', 8, 2); $table->string('year', 4); $table->timestamps(); $table->unique(['organization_id', 'leave_type_id', 'year']); });
        Schema::create('leave_balances', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete(); $table->foreignId('membership_id')->constrained('organization_memberships')->restrictOnDelete(); $table->foreignId('leave_type_id')->constrained()->restrictOnDelete(); $table->string('year', 4); $table->decimal('allocated_days', 8, 2); $table->decimal('reserved_days', 8, 2)->default(0); $table->decimal('used_days', 8, 2)->default(0); $table->timestamps(); $table->unique(['membership_id', 'leave_type_id', 'year']); });
        Schema::create('leave_requests', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete(); $table->foreignId('membership_id')->constrained('organization_memberships')->restrictOnDelete(); $table->foreignId('leave_type_id')->constrained()->restrictOnDelete(); $table->date('starts_on'); $table->date('ends_on'); $table->decimal('days', 8, 2); $table->text('reason')->nullable(); $table->string('status', 20)->default('pending_manager')->index(); $table->timestamps(); });
        Schema::create('leave_approvals', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete(); $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete(); $table->foreignId('approver_membership_id')->constrained('organization_memberships')->restrictOnDelete(); $table->string('stage', 20); $table->string('decision', 20); $table->text('comment')->nullable(); $table->timestamp('created_at'); $table->unique(['leave_request_id', 'stage']); });
        Schema::create('leave_balance_transactions', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete(); $table->foreignId('balance_id')->constrained('leave_balances')->restrictOnDelete(); $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete(); $table->decimal('delta_days', 8, 2); $table->string('type', 30); $table->json('metadata')->nullable(); $table->timestamp('created_at'); });

        Schema::create('compensations', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete(); $table->foreignId('membership_id')->constrained('organization_memberships')->restrictOnDelete(); $table->char('currency', 3); $table->decimal('base_amount', 18, 2); $table->date('effective_from'); $table->date('effective_to')->nullable(); $table->timestamps(); $table->index(['organization_id', 'membership_id', 'effective_from']); });
        Schema::create('payroll_periods', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete(); $table->string('period', 7); $table->date('starts_on'); $table->date('ends_on'); $table->string('status', 20)->default('draft')->index(); $table->timestamps(); $table->unique(['organization_id', 'period']); });
        Schema::create('payroll_lines', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete(); $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete(); $table->foreignId('membership_id')->constrained('organization_memberships')->restrictOnDelete(); $table->char('currency', 3); $table->decimal('gross_amount', 18, 2); $table->decimal('adjustment_amount', 18, 2)->default(0); $table->decimal('net_amount', 18, 2); $table->json('snapshot'); $table->timestamps(); $table->unique(['payroll_period_id', 'membership_id']); });
        Schema::create('payroll_reviews', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete(); $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete(); $table->foreignId('reviewer_membership_id')->constrained('organization_memberships')->restrictOnDelete(); $table->string('decision', 20); $table->text('comment')->nullable(); $table->timestamp('created_at'); });
        Schema::create('payroll_payments', function (Blueprint $table) { $table->id(); $table->foreignId('organization_id')->constrained()->restrictOnDelete(); $table->foreignId('payroll_line_id')->constrained()->restrictOnDelete(); $table->decimal('amount', 18, 2); $table->date('paid_on'); $table->string('reference', 120)->nullable(); $table->text('proof_path')->nullable(); $table->timestamps(); });
    }

    public function down(): void
    {
        foreach (['payroll_payments', 'payroll_reviews', 'payroll_lines', 'payroll_periods', 'compensations', 'leave_balance_transactions', 'leave_approvals', 'leave_requests', 'leave_balances', 'leave_policies', 'leave_types', 'attendance_corrections', 'attendance_correction_requests', 'attendance_sessions', 'organization_holidays', 'work_calendars'] as $table) Schema::dropIfExists($table);
    }
};
