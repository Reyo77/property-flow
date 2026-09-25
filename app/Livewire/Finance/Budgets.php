<?php

namespace App\Livewire\Finance;

use App\Enums\AccountType;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Account;
use App\Models\BudgetLine;
use App\Models\Community;
use App\Models\FiscalYear;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\FiscalYears;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The annual budget for each income and expense account, for this fiscal year or the next.
 */
#[Title('Budget')]
class Budgets extends Component
{
    use InteractsWithCurrentUser;

    private const string AMOUNT_PATTERN = '/^[\d,]{1,12}(\.\d{1,2})?$/';

    public Community $community;

    /** Which fiscal year: 'current' or 'next'. */
    public string $year = 'current';

    /**
     * Annual amounts being edited, keyed by account id.
     *
     * @var array<int, string>
     */
    public array $amounts = [];

    public function mount(ChartOfAccounts $chartOfAccounts): void
    {
        $this->authorize('viewAny', [BudgetLine::class, $this->community]);

        $chartOfAccounts->ensureFor($this->community);
        $this->loadAmounts();
    }

    public function updatedYear(): void
    {
        $this->year = $this->year === 'next' ? 'next' : 'current';
        unset($this->fiscalYear);
        $this->resetValidation();
        $this->loadAmounts();
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->currentUser()->can('create', [BudgetLine::class, $this->community]);
    }

    #[Computed]
    public function fiscalYear(): FiscalYear
    {
        $today = CarbonImmutable::now($this->community->timezone);

        return app(FiscalYears::class)->covering($this->community, $this->year === 'next' ? $today->addYear() : $today);
    }

    /**
     * @return Collection<int, Account>
     */
    #[Computed]
    public function accounts(): Collection
    {
        return $this->community->accounts()
            ->whereIn('type', [AccountType::Income, AccountType::Expense])
            ->orderBy('code')
            ->get();
    }

    /**
     * @return array{income: Money, expenses: Money, net: Money}
     */
    #[Computed]
    public function totals(): array
    {
        $sum = fn (AccountType $type) => Money::of($this->accounts()->where('type', $type)->sum(fn (Account $account) => $this->centsFor($account->id)), $this->community->currency);

        $income = $sum(AccountType::Income);
        $expenses = $sum(AccountType::Expense);

        return ['income' => $income, 'expenses' => $expenses, 'net' => $income->minus($expenses)];
    }

    /**
     * The first month's share of an account's annual budget (later months may be a cent less).
     */
    public function monthlyFor(int $accountId): ?Money
    {
        $cents = $this->centsFor($accountId);

        return $cents === 0 ? null : Money::of($cents, $this->community->currency)->allocate(array_fill(0, 12, 1))[0];
    }

    public function save(): void
    {
        $this->authorize('create', [BudgetLine::class, $this->community]);

        $accountIds = $this->accounts()->pluck('id')->all();

        $this->validate(
            ['amounts.*' => ['nullable', 'string', 'regex:'.self::AMOUNT_PATTERN]],
            ['amounts.*.regex' => __('Enter an amount like 12,500.00.')],
        );

        $year = $this->fiscalYear();

        DB::transaction(function () use ($year, $accountIds): void {
            foreach ($accountIds as $accountId) {
                $cents = $this->centsFor($accountId);
                $line = BudgetLine::query()->where('fiscal_year_id', $year->id)->where('account_id', $accountId)->first();

                if ($cents === 0) {
                    $line?->delete();

                    continue;
                }

                if ($line === null) {
                    $line = new BudgetLine(['annual_cents' => $cents]);
                    $line->forceFill([
                        'company_id' => $this->community->company_id,
                        'community_id' => $this->community->id,
                        'fiscal_year_id' => $year->id,
                        'account_id' => $accountId,
                    ])->save();
                } else {
                    $line->update(['annual_cents' => $cents]);
                }
            }
        });

        Flux::toast(variant: 'success', text: __('Budget for :year saved.', ['year' => $year->label()]));
    }

    public function render(): View
    {
        return view('livewire.finance.budgets');
    }

    private function loadAmounts(): void
    {
        $saved = BudgetLine::query()->where('fiscal_year_id', $this->fiscalYear()->id)->pluck('annual_cents', 'account_id');

        $this->amounts = [];

        foreach ($this->accounts() as $account) {
            $cents = (int) ($saved[$account->id] ?? 0);
            $this->amounts[$account->id] = $cents === 0 ? '' : Money::of($cents)->toDecimal();
        }
    }

    private function centsFor(int $accountId): int
    {
        $value = trim($this->amounts[$accountId] ?? '');

        if ($value === '' || preg_match(self::AMOUNT_PATTERN, $value) !== 1) {
            return 0;
        }

        return Money::parse($value)->cents;
    }
}
