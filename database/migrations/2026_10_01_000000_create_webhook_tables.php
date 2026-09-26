<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('description')->nullable();
            // Encrypted at rest; used to sign every delivery so the receiver can verify it came from us.
            $table->text('secret');
            // Event names this endpoint receives, e.g. ["service_request.created", "package.logged"].
            $table->json('events');
            $table->boolean('is_active')->default(true);
            // Consecutive failed deliveries; the endpoint is switched off when this reaches the limit.
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->dateTime('disabled_at')->nullable();
            $table->dateTime('last_delivered_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('event', 100);
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_excerpt')->nullable();
            $table->dateTime('last_attempted_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_endpoint_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }
};
