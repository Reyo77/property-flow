<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('kind', 20);
            $table->dateTime('starts_at');
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            // How attendance counts toward quorum: each unit equally, or by unit factor.
            $table->string('weighting', 20);
            $table->unsignedTinyInteger('quorum_percent');
            $table->longText('minutes')->nullable();
            $table->dateTime('minutes_published_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['community_id', 'starts_at']);
        });

        Schema::create('meeting_agenda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('meeting_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('represented_by', 20);
            $table->string('attendee_name')->nullable();
            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A unit is represented at a meeting at most once, however many owners turn up.
            $table->unique(['meeting_id', 'unit_id']);
        });

        Schema::create('ballots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('weighting', 20);
            $table->unsignedTinyInteger('quorum_percent');
            $table->dateTime('opens_at');
            $table->dateTime('closes_at');
            $table->dateTime('published_at')->nullable();
            // Set once, when the ballot closes: the frozen tally. Nothing changes it afterwards.
            $table->dateTime('closed_at')->nullable();
            $table->json('results')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['community_id', 'closes_at']);
        });

        Schema::create('ballot_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ballot_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('ballot_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ballot_question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('ballot_proxies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ballot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('holder_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['ballot_id', 'unit_id']);
        });

        Schema::create('ballot_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ballot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cast_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ballot_proxy_id')->nullable()->constrained()->nullOnDelete();
            // The unit's voting weight when it voted (its unit factor, or 1), kept with the vote.
            $table->decimal('weight', 12, 6);
            $table->dateTime('cast_at');
            $table->timestamps();

            // One vote per unit per ballot — whoever casts it, owner or proxy. The guarantee.
            $table->unique(['ballot_id', 'unit_id']);
        });

        Schema::create('ballot_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ballot_vote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ballot_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ballot_option_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ballot_vote_id', 'ballot_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ballot_answers');
        Schema::dropIfExists('ballot_votes');
        Schema::dropIfExists('ballot_proxies');
        Schema::dropIfExists('ballot_options');
        Schema::dropIfExists('ballot_questions');
        Schema::dropIfExists('ballots');
        Schema::dropIfExists('meeting_attendances');
        Schema::dropIfExists('meeting_agenda_items');
        Schema::dropIfExists('meetings');
    }
};
