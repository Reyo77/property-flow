<?php

namespace App\Livewire\Finance;

use App\Actions\Finance\ImportBankStatement;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\BankStatement;
use App\Models\Community;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Bank reconciliation')]
class Reconciliation extends Component
{
    use InteractsWithCurrentUser, WithFileUploads;

    public Community $community;

    public ?TemporaryUploadedFile $file = null;

    public string $starts_on = '';

    public string $ends_on = '';

    public string $closing_balance = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [BankStatement::class, $this->community]);
    }

    #[Computed]
    public function canImport(): bool
    {
        return $this->currentUser()->can('create', [BankStatement::class, $this->community]);
    }

    /**
     * @return Collection<int, BankStatement>
     */
    #[Computed]
    public function statements(): Collection
    {
        return $this->community->bankStatements()
            ->withCount(['lines', 'lines as unmatched_count' => fn ($query) => $query->whereNull('ledger_entry_id')])
            ->latest('ends_on')->latest('id')->get();
    }

    public function create(): void
    {
        $this->authorize('create', [BankStatement::class, $this->community]);

        $this->resetValidation();
        $this->reset('file', 'closing_balance');
        $lastMonth = CarbonImmutable::now($this->community->timezone)->subMonthNoOverflow();
        $this->starts_on = $lastMonth->startOfMonth()->toDateString();
        $this->ends_on = $lastMonth->endOfMonth()->toDateString();

        Flux::modal('statement-import')->show();
    }

    public function import(ImportBankStatement $importBankStatement): void
    {
        $this->authorize('create', [BankStatement::class, $this->community]);

        $validated = $this->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'closing_balance' => ['required', 'string', 'regex:/^-?[\d,]{1,12}(\.\d{1,2})?$/'],
        ]);

        if (! $this->file instanceof TemporaryUploadedFile) {
            return;
        }

        try {
            $statement = $importBankStatement->handle(
                $this->community,
                $this->file,
                CarbonImmutable::parse($validated['starts_on']),
                CarbonImmutable::parse($validated['ends_on']),
                Money::parse($validated['closing_balance'], $this->community->currency),
                $this->currentUser(),
            );
        } catch (ValidationException $exception) {
            $this->addError('file', $exception->validator->errors()->first());

            return;
        }

        Flux::modal('statement-import')->close();
        $this->redirectRoute('communities.finance.reconciliation.show', [$this->community, $statement], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.finance.reconciliation');
    }
}
