<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('audience_type', 20);
            $table->string('residency_type', 20)->nullable();
            $table->boolean('pinned')->default(false);
            $table->dateTime('publish_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['community_id', 'published_at']);
            $table->index(['community_id', 'publish_at']);
        });

        Schema::create('announcement_building', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();

            $table->unique(['announcement_id', 'building_id']);
        });

        Schema::create('announcement_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();

            $table->unique(['announcement_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_unit');
        Schema::dropIfExists('announcement_building');
        Schema::dropIfExists('announcements');
    }
};
