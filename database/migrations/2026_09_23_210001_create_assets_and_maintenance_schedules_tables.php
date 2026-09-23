<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 20);
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->date('install_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'community_id']);
        });

        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('interval_days');
            $table->date('next_due_on');
            $table->date('last_generated_on')->nullable();
            $table->string('assignee_type', 20)->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'active', 'next_due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('assets');
    }
};
