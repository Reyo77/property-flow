<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            // Day of the month recurring charges fall due (capped at 28 so every month has it).
            $table->unsignedTinyInteger('billing_due_day')->default(1)->after('fiscal_year_start_month');
            // Vendor bills above this need a board member's (ApproveLargeBills) approval.
            $table->unsignedBigInteger('bill_approval_limit_cents')->default(500000)->after('billing_due_day');
        });

        Schema::create('recurring_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_type_id')->constrained()->cascadeOnDelete();
            // Set to bill a single unit; null bills every unit in the community.
            $table->foreignId('unit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('description');
            // fixed: amount_cents per unit. unit_factor: amount_cents is the community total, split by unit factor.
            $table->string('method', 20);
            $table->unsignedBigInteger('amount_cents');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['community_id', 'is_active']);
        });

        Schema::create('late_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('grace_days')->default(10);
            // Exactly one of these is set: a flat fee, or a percentage (in basis points) of the overdue balance.
            $table->unsignedBigInteger('flat_cents')->nullable();
            $table->unsignedInteger('percent_basis_points')->nullable();
            // Balances below this are not charged a late fee.
            $table->unsignedBigInteger('minimum_balance_cents')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dateTime('overdue_notified_at')->nullable()->after('voided_at');
        });

        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('vendor_reference')->nullable();
            $table->string('description');
            $table->unsignedBigInteger('amount_cents');
            $table->date('billed_on');
            $table->date('due_on');
            $table->string('status', 20);
            $table->foreignId('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->string('decision_notes')->nullable();
            $table->foreignId('approval_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->date('paid_on')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('payment_reference')->nullable();
            $table->foreignId('paid_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->unique(['community_id', 'number']);
            $table->index(['community_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bills');
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('overdue_notified_at'));
        Schema::dropIfExists('late_fee_rules');
        Schema::dropIfExists('recurring_charges');
        Schema::table('communities', fn (Blueprint $table) => $table->dropColumn(['billing_due_day', 'bill_approval_limit_cents']));
    }
};
