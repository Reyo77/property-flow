<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('label');
            $table->string('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('community_id');
        });

        Schema::create('access_key_signouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('access_key_id')->constrained('access_keys')->cascadeOnDelete();
            $table->string('signed_out_to');
            $table->string('signed_out_to_phone')->nullable();
            $table->foreignId('signed_out_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('signed_out_at');
            $table->dateTime('due_back_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->foreignId('returned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['access_key_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_key_signouts');
        Schema::dropIfExists('access_keys');
    }
};
