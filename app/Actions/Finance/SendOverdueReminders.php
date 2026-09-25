<?php

namespace App\Actions\Finance;

use App\Enums\NotificationCategory;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\NotificationPreference;
use App\Models\Residency;
use App\Notifications\InvoiceOverdue;
use Carbon\CarbonImmutable;

/**
 * Reminds a unit's current residents about each invoice that is past due with money owing,
 * then again every REMINDER_INTERVAL_DAYS until it is paid or voided.
 */
class SendOverdueReminders
{
    private const int REMINDER_INTERVAL_DAYS = 7;

    public function handle(Community $community, CarbonImmutable $now): int
    {
        $today = $now->setTimezone($community->timezone)->toDateString();

        $invoices = Invoice::query()->withoutGlobalScopes()
            ->where('community_id', $community->id)
            ->whereNull('voided_at')
            ->whereDate('due_on', '<', $today)
            ->where(fn ($query) => $query->whereNull('overdue_notified_at')->orWhere('overdue_notified_at', '<=', $now->subDays(self::REMINDER_INTERVAL_DAYS)))
            ->withPaid()
            ->orderBy('id')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->balanceCents() > 0);

        $residencies = Residency::query()->withoutGlobalScopes()
            ->whereIn('unit_id', $invoices->pluck('unit_id')->unique()->all())
            ->active()
            ->with('resident.user')
            ->get()
            ->groupBy('unit_id');

        $reminded = 0;

        foreach ($invoices as $invoice) {
            foreach ($residencies->get($invoice->unit_id, collect()) as $residency) {
                $user = $residency->resident->user;

                if ($user !== null && NotificationPreference::inAppEnabled($user, NotificationCategory::Billing)) {
                    $user->notify(new InvoiceOverdue($invoice));
                }
            }

            $invoice->forceFill(['overdue_notified_at' => $now])->save();
            $reminded++;
        }

        return $reminded;
    }
}
