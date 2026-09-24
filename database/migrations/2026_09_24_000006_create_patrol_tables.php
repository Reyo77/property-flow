<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrol_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('community_id');
        });

        Schema::create('patrol_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patrol_route_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position');
            $table->string('qr_token', 40)->unique();
            $table->timestamps();

            $table->index(['patrol_route_id', 'position']);
        });

        Schema::create('patrol_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patrol_checkpoint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scanned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('scanned_at');
            $table->timestamps();

            $table->index(['patrol_checkpoint_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrol_scans');
        Schema::dropIfExists('patrol_checkpoints');
        Schema::dropIfExists('patrol_routes');
    }
};
