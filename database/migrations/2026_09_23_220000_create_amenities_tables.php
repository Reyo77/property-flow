<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('opens_at_minutes');
            $table->unsignedSmallInteger('closes_at_minutes');
            $table->json('closed_weekdays')->nullable();
            $table->unsignedSmallInteger('slot_minutes')->default(60);
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->unsignedSmallInteger('max_bookings_per_unit')->nullable();
            $table->unsignedSmallInteger('max_bookings_period_days')->nullable();
            $table->unsignedSmallInteger('advance_booking_days')->nullable();
            $table->unsignedSmallInteger('min_notice_hours')->nullable();
            $table->unsignedSmallInteger('cancellation_notice_hours')->nullable();
            $table->boolean('needs_approval')->default(false);
            $table->unsignedInteger('fee_cents')->nullable();
            $table->unsignedInteger('deposit_cents')->nullable();
            $table->text('terms')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('community_id');
        });

        Schema::create('amenity_blackouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['amenity_id', 'starts_on', 'ends_on']);
        });

        Schema::create('amenity_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->foreignId('booked_by_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20);
            $table->unsignedInteger('fee_cents')->nullable();
            $table->unsignedInteger('deposit_cents')->nullable();
            $table->dateTime('terms_accepted_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['amenity_id', 'starts_at']);
            $table->index('unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenity_bookings');
        Schema::dropIfExists('amenity_blackouts');
        Schema::dropIfExists('amenities');
    }
};
