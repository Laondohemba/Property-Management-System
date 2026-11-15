<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates taxes and fees (spec §2.8), cancellation and deposit policies (RTE-07) and rate plans
 * with base prices per room type and occupancy supplements (RTE-01).
 *
 * Money is BIGINT minor units in the property's currency; percentages are DECIMAL(7,4) (§7.1).
 *
 * @migration expand
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->string('calculation', 30);
            $table->decimal('rate', 7, 4)->nullable();
            $table->bigInteger('amount_minor')->nullable();
            $table->boolean('is_inclusive')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['property_id', 'code']);
        });

        Schema::create('cancellation_policies', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            // Free cancellation until this many hours before the arrival date's check-in time;
            // null means there is no free window (non-refundable).
            $table->unsignedSmallInteger('free_cancellation_hours')->nullable();
            $table->string('penalty_type', 20);
            $table->unsignedSmallInteger('penalty_nights')->nullable();
            $table->decimal('penalty_percent', 7, 4)->nullable();
            $table->bigInteger('penalty_amount_minor')->nullable();
            $table->string('no_show_type', 20);
            $table->unsignedSmallInteger('no_show_nights')->nullable();
            $table->decimal('no_show_percent', 7, 4)->nullable();
            $table->bigInteger('no_show_amount_minor')->nullable();
            $table->text('guest_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('deposit_policies', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('deposit_type', 20);
            $table->decimal('deposit_percent', 7, 4)->nullable();
            $table->unsignedSmallInteger('deposit_nights')->nullable();
            $table->bigInteger('deposit_amount_minor')->nullable();
            $table->string('deposit_due', 20);
            $table->unsignedSmallInteger('due_days_before_arrival')->nullable();
            $table->boolean('requires_card_guarantee')->default(false);
            $table->string('balance_due', 20);
            $table->text('guest_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rate_plans', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 20);
            $table->text('description')->nullable();
            $table->string('meal_plan', 20);
            $table->foreignId('cancellation_policy_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('deposit_policy_id')->nullable()->constrained()->restrictOnDelete();
            // Resolved nightly prices are clamped to this range (spec §6.3).
            $table->bigInteger('min_price_minor')->nullable();
            $table->bigInteger('max_price_minor')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'code']);
        });

        Schema::create('rate_plan_room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            // Per night for the room type's base occupancy.
            $table->bigInteger('base_price_minor');
            $table->bigInteger('extra_adult_minor')->default(0);
            $table->bigInteger('extra_child_minor')->default(0);
            $table->timestamps();

            $table->unique(['rate_plan_id', 'room_type_id']);
        });

        Schema::create('rate_plan_tax', function (Blueprint $table) {
            $table->foreignId('rate_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_id')->constrained()->restrictOnDelete();

            $table->primary(['rate_plan_id', 'tax_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_plan_tax');
        Schema::dropIfExists('rate_plan_room_types');
        Schema::dropIfExists('rate_plans');
        Schema::dropIfExists('deposit_policies');
        Schema::dropIfExists('cancellation_policies');
        Schema::dropIfExists('taxes');
    }
};
