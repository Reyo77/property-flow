<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // The payment gateway's id for an online payment; unique so a retried webhook or a
            // double-clicked return link can never record the same payment twice.
            $table->string('gateway_reference', 100)->nullable()->unique()->after('reference');
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            // The whole year's budget for the account; monthly figures are an even split of it.
            $table->unsignedBigInteger('annual_cents');
            $table->timestamps();

            $table->unique(['fiscal_year_id', 'account_id']);
        });

        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->bigInteger('closing_balance_cents');
            $table->string('filename')->nullable();
            $table->foreignId('imported_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reconciled_at')->nullable();
            $table->foreignId('reconciled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['community_id', 'ends_on']);
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_statement_id')->constrained()->cascadeOnDelete();
            $table->date('posted_on');
            $table->string('description');
            $table->string('reference')->nullable();
            // Signed: deposits positive, withdrawals negative.
            $table->bigInteger('amount_cents');
            // A ledger line on the bank account can be matched to at most one statement line.
            $table->foreignId('ledger_entry_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');
        Schema::dropIfExists('budget_lines');
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('gateway_reference'));
    }
};
