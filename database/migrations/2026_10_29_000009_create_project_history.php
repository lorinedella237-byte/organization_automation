<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_membership_id')->constrained('organization_memberships')->restrictOnDelete();
            $table->string('event', 80);
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->timestamp('created_at');
            $table->index(['organization_id', 'project_id', 'created_at'], 'project_history_org_project_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_history');
    }
};
