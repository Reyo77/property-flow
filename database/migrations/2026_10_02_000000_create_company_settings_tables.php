<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Null means unlimited. Companies without a plan (the default) are unlimited too.
            $table->unsignedInteger('max_communities')->nullable();
            $table->unsignedInteger('max_units')->nullable();
            $table->unsignedInteger('max_team_members')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('name')->constrained()->nullOnDelete();
            $table->string('logo_disk_path')->nullable()->after('plan_id');
            // A hex colour (e.g. #1d4ed8), used for the public community site and later the admin theme.
            $table->string('brand_color', 7)->nullable()->after('logo_disk_path');
        });

        Schema::table('communities', function (Blueprint $table) {
            // Module values (App\Enums\Module) switched off for this community; empty/null means all on.
            $table->json('disabled_modules')->nullable()->after('bill_approval_limit_cents');
        });

        Schema::create('data_export_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('disk_path')->nullable();
            $table->string('failure_reason')->nullable();
            $table->dateTime('requested_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('resident_data_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();

            // One open request per resident at a time.
            $table->unique(['resident_id', 'status'], 'one_open_deletion_request_per_resident');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_data_deletion_requests');
        Schema::dropIfExists('data_export_requests');

        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn('disabled_modules');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['logo_disk_path', 'brand_color']);
        });

        Schema::dropIfExists('plans');
    }
};
