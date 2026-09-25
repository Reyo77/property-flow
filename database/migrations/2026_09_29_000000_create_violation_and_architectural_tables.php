<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('violation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            // Where the rule comes from, quoted on notices, e.g. "Bylaw 4, s. 12".
            $table->string('reference')->nullable();
            // Days the owner has to fix it after each notice before the next step.
            $table->unsignedSmallInteger('cure_days')->default(14);
            // Null: this rule escalates to a warning but is never fined.
            $table->unsignedBigInteger('fine_cents')->nullable();
            // How many fines at most (the first included) before it goes to the board instead.
            $table->unsignedTinyInteger('max_fines')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('violation_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('observed_at');
            $table->string('location')->nullable();
            $table->text('description');
            $table->string('status', 20);
            // The last step taken (courtesy notice, warning, fine) and when the next one falls due.
            $table->string('stage', 20)->nullable();
            $table->unsignedTinyInteger('fines_issued')->default(0);
            $table->date('next_action_on')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->string('resolution_notes')->nullable();
            $table->foreignId('closed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['community_id', 'status']);
            $table->index(['status', 'next_action_on']);
        });

        Schema::create('violation_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('violation_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 20);
            $table->date('issued_on');
            $table->date('cure_by')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issued_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('architectural_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('contractor')->nullable();
            $table->date('planned_start_on')->nullable();
            $table->string('status', 30);
            $table->text('conditions')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();

            $table->index(['community_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('architectural_requests');
        Schema::dropIfExists('violation_notices');
        Schema::dropIfExists('violations');
        Schema::dropIfExists('violation_rules');
    }
};
