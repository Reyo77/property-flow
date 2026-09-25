<?php

namespace App\Livewire\Finance;

use App\Actions\Finance\CompleteReconciliation;
use App\Actions\Finance\RecordBankLine;
use App\Enums\AccountType;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Account;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Community;
use App\Models\LedgerEntry;
use App\Support\Finance\BankReconciliation;
use App\Support\Finance\ReconciliationSummary;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use LogicException;

#[Title('Bank statement')]
class BankStatementShow extends Component
{
    use InteractsWithCurrentUser;

    /**
     * How far either side of a bank line to look for book entries when matching by hand.
     */
    private const int MANUAL_MATCH_WINDOW_DAYS = 45;

    public Community $community;

    public BankStatement $bankStatement;

    public ?int $selectedLineId = null;

    public string $counter_account_id = '';

    public function mount(): void
    {
        $this->authorize('view', $this->bankStatement);
    }

    #[Computed]
    public function canReconcile(): bool
    {
        return $this->currentUser()->can('reconcile', $this->bankStatement);
    }

    #[Computed]
    public function summary(): ReconciliationSummary
    {
        return app(BankReconciliation::class)->summary($this->bankStatement);
    }

    /**
     * @return Collection<int, BankStatementLine>
     */
    #[Computed]
    public function lines(): Collection
    {
        return $this->bankStatement->lines()->with('ledgerEntry.journalEntry')->orderBy('posted_on')->orderBy('id')->get();
    }

    #[Computed]
    public function selectedLine(): ?BankStatementLine
    {
        return $this->selectedLineId === null ? null : $this->bankStatement->lines()->find($this->selectedLineId);
    }

    /**
     * @return SupportCollection<int, LedgerEntry>
     */
    #[Computed]
    public function candidates(): SupportCollection
    {
        $line = $this->selectedLine();

        return $line === null ? collect() : app(BankReconciliation::class)->candidates($this->bankStatement, $line, self::MANUAL_MATCH_WINDOW_DAYS);
    }

    /**
     * @return Collection<int, Account>
     */
    #[Computed]
    public function counterAccounts(): Collection
    {
        return $this->community->accounts()->whereIn('type', [AccountType::Income, AccountType::Expense])->where('is_active', true)->orderBy('code')->get();
    }

    public function autoMatch(BankReconciliation $bankReconciliation): void
    {
        $this->authorize('reconcile', $this->bankStatement);

        $matched = $bankReconciliation->autoMatch($this->bankStatement);

        Flux::toast(text: trans_choice('{0} No new matches found.|{1} Matched 1 line.|[2,*] Matched :count lines.', $matched));
        $this->refreshState();
    }

    public function selectLine(int $lineId): void
    {
        $this->authorize('reconcile', $this->bankStatement);

        $line = $this->bankStatement->lines()->findOrFail($lineId);

        $this->resetValidation();
        $this->selectedLineId = $line->id;
        $this->counter_account_id = '';
        unset($this->selectedLine, $this->candidates);

        Flux::modal('resolve-line')->show();
    }

    public function match(int $ledgerEntryId, BankReconciliation $bankReconciliation): void
    {
        $this->authorize('reconcile', $this->bankStatement);

        $line = $this->bankStatement->lines()->findOrFail($this->selectedLineId);
        $entry = LedgerEntry::query()->where('account_id', $this->bankStatement->account_id)->findOrFail($ledgerEntryId);

        try {
            $bankReconciliation->match($line, $entry);
        } catch (LogicException $exception) {
            $this->addError('match', $exception->getMessage());

            return;
        }

        Flux::modal('resolve-line')->close();
        $this->refreshState();
    }

    public function record(RecordBankLine $recordBankLine): void
    {
        $this->authorize('reconcile', $this->bankStatement);

        $line = $this->bankStatement->lines()->findOrFail($this->selectedLineId);
        $this->validate(['counter_account_id' => ['required', 'integer']], ['counter_account_id.required' => __('Choose an account.')]);
        $account = $this->counterAccounts()->firstWhere('id', (int) $this->counter_account_id);

        if ($account === null) {
            $this->addError('counter_account_id', __('Choose an active income or expense account.'));

            return;
        }

        try {
            $recordBankLine->handle($line, $account, $this->currentUser());
        } catch (LogicException $exception) {
            $this->addError('counter_account_id', $exception->getMessage());

            return;
        }

        Flux::modal('resolve-line')->close();
        Flux::toast(variant: 'success', text: __('Recorded in the books and matched.'));
        $this->refreshState();
    }

    public function unmatch(int $lineId, BankReconciliation $bankReconciliation): void
    {
        $this->authorize('reconcile', $this->bankStatement);

        $bankReconciliation->unmatch($this->bankStatement->lines()->findOrFail($lineId));
        $this->refreshState();
    }

    public function complete(CompleteReconciliation $completeReconciliation): void
    {
        $this->authorize('reconcile', $this->bankStatement);

        try {
            $completeReconciliation->handle($this->bankStatement, $this->currentUser());
        } catch (LogicException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->bankStatement->refresh();
        Flux::toast(variant: 'success', text: __('Statement reconciled.'));
        $this->refreshState();
    }

    public function render(): View
    {
        return view('livewire.finance.bank-statement-show');
    }

    private function refreshState(): void
    {
        $this->selectedLineId = null;
        unset($this->summary, $this->lines, $this->selectedLine, $this->candidates, $this->canReconcile);
    }
}
