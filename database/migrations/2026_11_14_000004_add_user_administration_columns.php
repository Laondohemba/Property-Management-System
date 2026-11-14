<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User and role administration (SYS-01, SYS-02).
     *
     * - roles.organization_id: null for the built-in roles shared by every organisation,
     *   set for an organisation's custom roles.
     * - users.invited_at / invited_by: staff join by invitation.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->string('label', 100)->nullable()->after('name');
            $table->string('description', 500)->nullable()->after('label');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('invited_at')->nullable()->after('last_login_at');
            $table->foreignId('invited_by')->nullable()->after('invited_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn('invited_at');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['label', 'description']);
        });
    }
};
