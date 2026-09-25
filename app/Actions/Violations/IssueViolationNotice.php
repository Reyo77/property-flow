<?php

namespace App\Actions\Violations;

use App\Actions\Finance\IssueInvoice;
use App\Enums\NotificationCategory;
use App\Enums\ResidencyType;
use App\Enums\SystemAccount;
use App\Enums\ViolationStage;
use App\Models\NotificationPreference;
use App\Models\Residency;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationNotice;
use App\Notifications\ViolationNoticeIssued;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\InvoiceLineData;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Records one rung of the ladder — the letter, and for a fine the invoice to the unit — then
 * sets when the next rung falls due and lets the unit's owners know.
 */
class IssueViolationNotice
{
    public function __construct(
        private readonly IssueInvoice $issueInvoice,
        private readonly ChartOfAccounts $chartOfAccounts,
    ) {}

    public function handle(Violation $violation, ViolationStage $stage, CarbonImmutable $today, ?User $issuedBy = null): ViolationNotice
    {
        $notice = DB::transaction(function () use ($violation, $stage, $today, $issuedBy): ViolationNotice {
            $violation->loadMissing(['rule', 'unit.community']);
            $rule = $violation->rule;
            $cureBy = $today->addDays($rule->cure_days);
            $invoiceId = null;

            if ($stage === ViolationStage::Fine) {
                $fineNumber = $violation->fines_issued + 1;
                $community = $violation->unit->community;

                $invoice = $this->issueInvoice->handle(
                    $violation->unit,
                    $today,
                    $cureBy,
                    [new InvoiceLineData(
                        __('Fine: :rule', ['rule' => $rule->title]),
                        Money::of((int) $rule->fine_cents, $community->currency),
                        $this->chartOfAccounts->account($community, SystemAccount::Fines),
                    )],
                    __('Bylaw fine :number for violation #:id', ['number' => $fineNumber, 'id' => $violation->id]),
                    $issuedBy,
                    $violation,
                    "violation-fine:{$violation->id}:{$fineNumber}",
                );
                $invoiceId = $invoice->id;
            }

            $notice = new ViolationNotice;
            $notice->forceFill([
                'company_id' => $violation->company_id,
                'violation_id' => $violation->id,
                'stage' => $stage,
                'issued_on' => $today->toDateString(),
                'cure_by' => $cureBy->toDateString(),
                'invoice_id' => $invoiceId,
                'issued_by_id' => $issuedBy?->id,
            ])->save();

            $finesIssued = $violation->fines_issued + ($stage === ViolationStage::Fine ? 1 : 0);

            $violation->forceFill([
                'stage' => $stage,
                'fines_issued' => $finesIssued,
                'next_action_on' => $this->hasNextStep($violation, $stage, $finesIssued) ? $cureBy->addDay()->toDateString() : null,
            ])->save();

            return $notice;
        });

        $this->notifyOwners($violation, $notice);

        return $notice;
    }

    /**
     * Whether anything follows this notice automatically. A rule with no fine stops at the
     * warning; fines stop at the rule's maximum. Past that, it's the board's call.
     */
    private function hasNextStep(Violation $violation, ViolationStage $stage, int $finesIssued): bool
    {
        $rule = $violation->rule;

        return match ($stage) {
            ViolationStage::Courtesy => true,
            ViolationStage::Warning => $rule->fine_cents !== null && $rule->fine_cents > 0,
            ViolationStage::Fine => $finesIssued < $rule->max_fines,
        };
    }

    private function notifyOwners(Violation $violation, ViolationNotice $notice): void
    {
        $residencies = Residency::query()->withoutGlobalScopes()
            ->where('unit_id', $violation->unit_id)
            ->where('type', ResidencyType::Owner)
            ->active()
            ->with('resident.user')
            ->get();

        foreach ($residencies as $residency) {
            $user = $residency->resident->user;

            if ($user !== null && NotificationPreference::inAppEnabled($user, NotificationCategory::Violations)) {
                $user->notify(new ViolationNoticeIssued($violation, $notice));
            }
        }
    }
}
