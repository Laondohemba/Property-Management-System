<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the master inventory (spec §7.2, §7.3, CHN-01) and room-type blocks (CAL-06).
 *
 * room_type_inventory holds one row per room type per date: the single source of truth for
 * availability on every channel. Availability = total − blocked − sold; the check constraint
 * keeps sold + blocked within total + overbook_allowance even if application code is wrong.
 *
 * @migration expand
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_type_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('total');
            $table->unsignedSmallInteger('blocked')->default(0);
            $table->unsignedSmallInteger('sold')->default(0);
            $table->unsignedSmallInteger('overbook_allowance')->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->unique(['room_type_id', 'date'], 'uq_inv');
            $table->index(['property_id', 'date'], 'ix_inv_prop_date');
        });

        // Enforced by MySQL 8.0.16+ (CI and production). SQLite cannot add a constraint to an
        // existing table; there the application checks are the only guard.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE room_type_inventory ADD CONSTRAINT ck_inv CHECK (sold + blocked <= total + overbook_allowance)');
        }

        Schema::create('inventory_blocks', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->unsignedSmallInteger('quantity');
            // Nights covered, both inclusive.
            $table->date('from_date');
            $table->date('to_date');
            $table->string('reason', 500);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['property_id', 'released_at', 'to_date'], 'ix_blocks_active');
            $table->index(['released_at', 'expires_at'], 'ix_blocks_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_blocks');
        Schema::dropIfExists('room_type_inventory');
    }
};
