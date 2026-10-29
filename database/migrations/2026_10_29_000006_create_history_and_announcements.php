<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('task_history',function(Blueprint $t){$t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('task_id')->constrained()->cascadeOnDelete();$t->foreignId('actor_membership_id')->constrained('organization_memberships')->restrictOnDelete();$t->string('event',80);$t->json('before_values')->nullable();$t->json('after_values')->nullable();$t->timestamp('created_at');});
  Schema::create('activity_comments',function(Blueprint $t){$t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('activity_id')->constrained('daily_activities')->cascadeOnDelete();$t->foreignId('membership_id')->constrained('organization_memberships')->restrictOnDelete();$t->text('body');$t->timestamps();});
  Schema::create('report_categories',function(Blueprint $t){$t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->string('name',120);$t->string('code',60);$t->boolean('is_active')->default(true);$t->timestamps();$t->unique(['organization_id','code']);});
  Schema::create('announcements',function(Blueprint $t){$t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('author_membership_id')->constrained('organization_memberships')->restrictOnDelete();$t->string('title',180);$t->text('body');$t->timestamp('published_at')->nullable();$t->timestamp('expires_at')->nullable();$t->string('status',20)->default('draft');$t->timestamps();$t->index(['organization_id','status','published_at']);});
  Schema::create('announcement_audiences',function(Blueprint $t){$t->id();$t->foreignId('organization_id')->constrained()->restrictOnDelete();$t->foreignId('announcement_id')->constrained()->cascadeOnDelete();$t->foreignId('membership_id')->nullable()->constrained('organization_memberships')->cascadeOnDelete();$t->foreignId('department_id')->nullable()->constrained()->cascadeOnDelete();$t->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();$t->timestamps();});
 }
 public function down(): void { foreach(['announcement_audiences','announcements','report_categories','activity_comments','task_history'] as $t)Schema::dropIfExists($t); }
};
