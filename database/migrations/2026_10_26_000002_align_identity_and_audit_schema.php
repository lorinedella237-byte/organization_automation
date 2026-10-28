<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Preserve legacy employee values until membership migration; new identities need no employer.
            $table->string('employee_number')->nullable()->change();
            $table->string('phone')->nullable()->change();
            $table->unique('uuid');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            // Historical dates are unknown and must not be fabricated.
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::rename('permission', 'permissions');
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('employee_number')->orWhereNull('phone')->exists()) {
            throw new RuntimeException('Cannot restore required legacy employee/phone fields while null values exist. Restore a verified backup or explicitly reconcile those identities first.');
        }

        Schema::rename('permissions', 'permission');
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropColumn('created_at');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->string('employee_number')->nullable(false)->change();
            $table->string('phone')->nullable(false)->change();
        });
    }
};
