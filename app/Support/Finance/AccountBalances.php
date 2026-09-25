<?php

namespace App\Support\Finance;

use App\Enums\SystemAccount;
use App\Models\Account;
use App\Models\Community;
use App\Models\LedgerEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Account and per-unit balances read straight off the ledger, signed by each account's normal
 * side (so an asset or expense is positive when debited, a liability, equity or income account
 * when credited).
 */
class AccountBalances
{
    public function __construct(private readonly ChartOfAccounts $chartOfAccounts) {}

    /**
     * @return Collection<int, Money> keyed by account id
     */
    public function forCommunity(Community $community, ?CarbonInterface $asOf = null): Collection
    {
        $query = LedgerEntry::query()->withoutGlobalScopes()
            ->join('accounts', 'accounts.id', '=', 'ledger_entries.account_id')
            ->where('accounts.community_id', $community->id)
            ->groupBy('ledger_entries.account_id')
            ->selectRaw('ledger_entries.account_id, SUM(ledger_entries.debit_cents) as debits, SUM(ledger_entries.credit_cents) as credits');

        if ($asOf !== null) {
            $query->whereDate('ledger_entries.posted_on', '<=', $asOf->toDateString());
        }

        $totals = $query->toBase()->get()->keyBy('account_id');

        return Account::query()->withoutGlobalScopes()->where('community_id', $community->id)->get()
            ->mapWithKeys(function (Account $account) use ($totals, $community): array {
                $row = $totals->get($account->id);

                return [$account->id => Money::of(
                    $account->type->balanceFrom((int) ($row->debits ?? 0), (int) ($row->credits ?? 0)),
                    $community->currency,
                )];
            });
    }

    public function of(Community $community, SystemAccount $account): Money
    {
        $id = $this->chartOfAccounts->account($community, $account)->id;

        return $this->forCommunity($community)->get($id) ?? Money::zero($community->currency);
    }

    /**
     * What each unit owes (positive) or has in credit (negative), for units whose balance isn't zero.
     *
     * @return Collection<int, Money> keyed by unit id
     */
    public function unitBalances(Community $community): Collection
    {
        return LedgerEntry::query()->withoutGlobalScopes()
            ->where('account_id', $this->chartOfAccounts->account($community, SystemAccount::Receivables)->id)
            ->whereNotNull('unit_id')
            ->groupBy('unit_id')
            ->havingRaw('SUM(debit_cents) <> SUM(credit_cents)')
            ->selectRaw('unit_id, SUM(debit_cents) - SUM(credit_cents) as balance')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->unit_id => Money::of((int) $row->balance, $community->currency)]);
    }
}
