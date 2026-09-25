<?php

namespace App\Support\Finance;

use App\Enums\AccountType;
use App\Enums\CommunityType;
use App\Enums\SystemAccount;
use App\Models\Account;
use App\Models\Community;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Provisions a community's chart of accounts from a template for its type, the first time
 * finance touches it. Idempotent: once a community has its system accounts, it is left alone,
 * so a company's renames and additions are never overwritten.
 */
class ChartOfAccounts
{
    public function ensureFor(Community $community): void
    {
        if ($this->isProvisioned($community)) {
            return;
        }

        DB::transaction(function () use ($community): void {
            // Serialize concurrent first-time provisioning for the same community.
            Community::query()->withoutGlobalScopes()->whereKey($community->id)->lockForUpdate()->first();

            if ($this->isProvisioned($community)) {
                return;
            }

            foreach ($this->template($community->type) as [$code, $name, $type, $systemKey]) {
                $account = new Account(['code' => $code, 'name' => $name, 'type' => $type, 'is_active' => true]);
                $account->forceFill([
                    'company_id' => $community->company_id,
                    'community_id' => $community->id,
                    'system_key' => $systemKey,
                ])->save();
            }
        });
    }

    private function isProvisioned(Community $community): bool
    {
        return Account::query()->withoutGlobalScopes()->where('community_id', $community->id)->whereNotNull('system_key')->exists();
    }

    public function account(Community $community, SystemAccount $key): Account
    {
        $this->ensureFor($community);

        $account = Account::query()->withoutGlobalScopes()
            ->where('community_id', $community->id)
            ->where('system_key', $key)
            ->first();

        if ($account === null) {
            throw new LogicException("Community {$community->id} has no {$key->value} account.");
        }

        return $account;
    }

    /**
     * @return list<array{0: string, 1: string, 2: AccountType, 3: SystemAccount|null}>
     */
    public function template(CommunityType $type): array
    {
        $assessmentsName = match ($type) {
            CommunityType::Hoa => __('Assessments'),
            CommunityType::Cooperative => __('Carrying charges'),
            CommunityType::Rental => __('Rent'),
            CommunityType::Condominium, CommunityType::MixedUse => __('Common expense fees'),
        };

        $accounts = [
            ['1000', __('Operating bank account'), AccountType::Asset, SystemAccount::Cash],
            ['1100', __('Accounts receivable'), AccountType::Asset, SystemAccount::Receivables],
            ['2000', __('Accounts payable'), AccountType::Liability, SystemAccount::Payables],
            ['2100', __('Security deposits held'), AccountType::Liability, SystemAccount::Deposits],
            ['3000', $type === CommunityType::Rental ? __('Owner\'s equity') : __('Operating fund balance'), AccountType::Equity, SystemAccount::RetainedEarnings],
            ['4000', $assessmentsName, AccountType::Income, SystemAccount::Assessments],
            ['4100', __('Late fees'), AccountType::Income, SystemAccount::LateFees],
            ['4200', __('Amenity fees'), AccountType::Income, SystemAccount::AmenityFees],
            ['4300', __('Fines'), AccountType::Income, SystemAccount::Fines],
            ['4900', __('Other income'), AccountType::Income, SystemAccount::OtherIncome],
            ['5000', __('Repairs & maintenance'), AccountType::Expense, null],
            ['5100', __('Utilities'), AccountType::Expense, null],
            ['5200', __('Insurance'), AccountType::Expense, null],
            ['5300', __('Management fees'), AccountType::Expense, null],
            ['5400', __('Landscaping & snow removal'), AccountType::Expense, null],
            ['5900', __('General & administrative'), AccountType::Expense, null],
        ];

        if ($type === CommunityType::Rental) {
            $accounts[] = ['5500', __('Property taxes'), AccountType::Expense, null];
        } else {
            $accounts[] = ['5600', __('Reserve fund contribution'), AccountType::Expense, null];
        }

        usort($accounts, fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        return $accounts;
    }
}
