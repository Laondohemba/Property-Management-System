<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail (spec §5.12, §7.2): who / what / when / where with before and after values.
     *
     * Append-only and tamper-evident (AUD-02): every entry carries a SHA-256 hash chained to the
     * previous entry of its organisation, and triggers reject UPDATE and DELETE. Production should
     * additionally deny the application's database role those privileges (spec §13).
     */
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name', 50)->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event', 50)->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->char('prev_hash', 64)->nullable();
            $table->char('hash', 64)->unique();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['property_id', 'created_at']);
            $table->index(['log_name', 'created_at']);
        });

        foreach (['UPDATE', 'DELETE'] as $operation) {
            $name = 'activity_log_no_'.strtolower($operation);

            DB::unprepared(match (DB::getDriverName()) {
                'sqlite' => "CREATE TRIGGER {$name} BEFORE {$operation} ON activity_log BEGIN SELECT RAISE(ABORT, 'activity_log is append-only'); END;",
                default => "CREATE TRIGGER {$name} BEFORE {$operation} ON activity_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'activity_log is append-only';",
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS activity_log_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS activity_log_no_delete');
        Schema::dropIfExists('activity_log');
    }
};
