<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the amenity, room type and room tables.
 *
 * @migration expand
 */
return new class extends Migration
{
    /**
     * Physical and sellable inventory (spec §7.2, SYS-04): amenities, room types and rooms.
     *
     * Rooms and room types are deactivated rather than deleted (reservations will reference
     * them), so codes and room numbers stay unique per property for good.
     */
    public function up(): void
    {
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['property_id', 'name']);
        });

        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 20);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('base_occupancy');
            $table->unsignedTinyInteger('max_adults');
            $table->unsignedTinyInteger('max_children')->default(0);
            $table->unsignedTinyInteger('max_occupancy');
            // [{"type": "king", "count": 1}, ...]
            $table->json('bed_configuration');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'code']);
            $table->index(['property_id', 'is_active', 'sort_order']);
        });

        Schema::create('amenity_room_type', function (Blueprint $table) {
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();

            $table->primary(['room_type_id', 'amenity_id']);
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->string('number', 20);
            $table->string('floor', 20)->nullable();
            $table->text('notes')->nullable();
            // Housekeeping and occupancy states (spec §6.2); transitions arrive with housekeeping in Phase 1.
            $table->string('housekeeping_status', 20)->default('clean');
            $table->string('occupancy_status', 10)->default('vacant');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'number']);
            $table->index(['property_id', 'room_type_id']);
            $table->index(['property_id', 'housekeeping_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('amenity_room_type');
        Schema::dropIfExists('room_types');
        Schema::dropIfExists('amenities');
    }
};
