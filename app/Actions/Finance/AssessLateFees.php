<?php

namespace App\Actions\Finance;

use App\Enums\SystemAccount;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\LateFeeRule;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\InvoiceLineData;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Charges the community's late fee on each invoice that is past its grace period with money still
 * owing. Each invoice is charged at most once (its late-fee invoice has a billing key), late fees
 * are never charged on late fees, and invoices that fell due before the rule existed are left
 * alone so switching a rule on doesn't retroactively fine old balances.
 */
class AssessLateFees
{
    public function __construct(
        private readonly IssueInvoice $issueInvoice,
        private readonly ChartOfAccounts $chartOfAccounts,
    ) {}

    public static function billingKey(Invoice $invoice): string
    {
        return "late-fee:{$invoice->id}";
    }

    public function handle(Community $community, CarbonImmutable $today): int
    {
        $rule = LateFeeRule::query()->withoutGlobalScopes()->where('community_id', $community->id)->where('is_active', true)->first();

        if ($rule === null) {
            return 0;
        }

        $today = $today->startOfDay();
        $ruleStartedOn = CarbonImmutable::parse($rule->created_at ?? $today)->setTimezone($community->timezone)->toDateString();

        $candidates = Invoice::query()->withoutGlobalScopes()
            ->where('community_id', $community->id)
            ->whereNull('voided_at')
            ->whereDate('due_on', '<', $today->subDays($rule->grace_days)->toDateString())
            ->whereDate('due_on', '>=', $ruleStartedOn)
            ->where(fn (Builder $query) => $query->whereNull('billing_key')->orWhere('billing_key', 'not like', 'late-fee:%'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('invoices as fees')
                ->whereColumn('fees.community_id', 'invoices.community_id')
                ->whereRaw("fees.billing_key = concat('late-fee:', invoices.id)"))
            ->withPaid()
            ->with('unit.community')
            ->orderBy('id')
            ->get();

        $account = $this->chartOfAccounts->account($community, SystemAccount::LateFees);
        $charged = 0;

        foreach ($candidates as $invoice) {
            $fee = $rule->feeFor(Money::of($invoice->balanceCents(), $community->currency));

            if (! $fee->isPositive()) {
                continue;
            }

            $this->issueInvoice->handle(
                $invoice->unit,
                $today,
                $today,
                [new InvoiceLineData(__('Late fee on :number', ['number' => $invoice->displayNumber()]), $fee, $account)],
                source: $invoice,
                billingKey: self::billingKey($invoice),
            );

            $charged++;
        }

        return $charged;
    }
}
