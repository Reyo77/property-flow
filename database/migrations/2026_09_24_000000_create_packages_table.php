<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->string('carrier');
            $table->string('tracking_number')->nullable();
            $table->string('shelf_location')->nullable();
            $table->string('status', 20);
            $table->foreignId('logged_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->foreignId('released_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('released_to_name')->nullable();
            $table->string('signature_disk_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['community_id', 'status']);
            $table->index('unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
