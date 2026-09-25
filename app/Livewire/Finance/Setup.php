<?php

namespace App\Livewire\Finance;

use App\Concerns\FinanceValidationRules;
use App\Enums\AccountType;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Account;
use App\Models\ChargeType;
use App\Models\Community;
use App\Support\Finance\AccountBalances;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\Money;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The community's chart of accounts (with balances) and the charge types invoices are built from.
 */
#[Title('Accounts & charges')]
class Setup extends Component
{
    use FinanceValidationRules, InteractsWithCurrentUser;

    public Community $community;

    public string $code = '';

    public string $name = '';

    public string $type = '';

    public ?int $editingChargeTypeId = null;

    public string $charge_name = '';

    public string $account_id = '';

    public string $default_amount = '';

    public function mount(ChartOfAccounts $chartOfAccounts): void
    {
        $this->authorize('viewAny', [Account::class, $this->community]);

        $chartOfAccounts->ensureFor($this->community);
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->currentUser()->can('create', [Account::class, $this->community]);
    }

    /**
     * @return Collection<int, Account>
     */
    #[Computed]
    public function accounts(): Collection
    {
        return $this->community->accounts()->orderBy('code')->get();
    }

    /**
     * @return SupportCollection<int, Money>
     */
    #[Computed]
    public function balances(): SupportCollection
    {
        return app(AccountBalances::class)->forCommunity($this->community);
    }

    /**
     * @return Collection<int, ChargeType>
     */
    #[Computed]
    public function chargeTypes(): Collection
    {
        return $this->community->chargeTypes()->with('account')->orderByDesc('is_active')->orderBy('name')->get();
    }

    public function createAccount(): void
    {
        $this->authorize('create', [Account::class, $this->community]);

        $this->resetValidation();
        $this->reset('code', 'name');
        $this->type = AccountType::Expense->value;

        Flux::modal('account-form')->show();
    }

    public function saveAccount(): void
    {
        $this->authorize('create', [Account::class, $this->community]);

        $validated = $this->validate($this->accountRules($this->community));

        $account = new Account(['code' => $validated['code'], 'name' => $validated['name'], 'type' => AccountType::from($validated['type']), 'is_active' => true]);
        $account->forceFill(['company_id' => $this->community->company_id, 'community_id' => $this->community->id])->save();

        Flux::modal('account-form')->close();
        Flux::toast(variant: 'success', text: __('Account added.'));

        unset($this->accounts);
    }

    /**
     * System accounts are wired into posting logic and always stay active; others can be retired.
     * A retired account keeps its history but accepts no new postings.
     */
    public function toggleAccount(int $accountId): void
    {
        $this->authorize('create', [Account::class, $this->community]);

        $account = $this->community->accounts()->findOrFail($accountId);

        if ($account->isSystem()) {
            return;
        }

        $account->update(['is_active' => ! $account->is_active]);

        unset($this->accounts);
    }

    public function createChargeType(): void
    {
        $this->authorize('create', [ChargeType::class, $this->community]);

        $this->resetValidation();
        $this->reset('editingChargeTypeId', 'charge_name', 'account_id', 'default_amount');

        Flux::modal('charge-type-form')->show();
    }

    public function editChargeType(int $chargeTypeId): void
    {
        $chargeType = $this->community->chargeTypes()->findOrFail($chargeTypeId);

        $this->authorize('update', $chargeType);

        $this->resetValidation();
        $this->editingChargeTypeId = $chargeType->id;
        $this->charge_name = $chargeType->name;
        $this->account_id = (string) $chargeType->account_id;
        $this->default_amount = $chargeType->default_amount_cents === null ? '' : Money::of($chargeType->default_amount_cents)->toDecimal();

        Flux::modal('charge-type-form')->show();
    }

    public function saveChargeType(): void
    {
        $chargeType = $this->editingChargeTypeId === null ? null : $this->community->chargeTypes()->findOrFail($this->editingChargeTypeId);

        $chargeType === null
            ? $this->authorize('create', [ChargeType::class, $this->community])
            : $this->authorize('update', $chargeType);

        $validated = $this->validate($this->chargeTypeRules($this->community));

        $attributes = [
            'name' => $validated['charge_name'],
            'account_id' => (int) $validated['account_id'],
            'default_amount_cents' => $validated['default_amount'] ? Money::parse($validated['default_amount'])->cents : null,
        ];

        if ($chargeType === null) {
            $chargeType = new ChargeType($attributes);
            $chargeType->forceFill(['company_id' => $this->community->company_id, 'community_id' => $this->community->id])->save();
        } else {
            $chargeType->update($attributes);
        }

        Flux::modal('charge-type-form')->close();
        Flux::toast(variant: 'success', text: __('Charge type saved.'));

        unset($this->chargeTypes);
    }

    public function toggleChargeType(int $chargeTypeId): void
    {
        $chargeType = $this->community->chargeTypes()->findOrFail($chargeTypeId);

        $this->authorize('update', $chargeType);

        $chargeType->update(['is_active' => ! $chargeType->is_active]);

        unset($this->chargeTypes);
    }

    public function render(): View
    {
        return view('livewire.finance.setup');
    }
}
