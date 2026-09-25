<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Statement :from – :to', ['from' => $bankStatement->starts_on->toFormattedDateString(), 'to' => $bankStatement->ends_on->toFormattedDateString()]) }}</flux:heading>
            <flux:subheading>{{ $community->name }} · {{ $bankStatement->filename }}</flux:subheading>
        </div>

        <div class="flex gap-2">
            @if ($bankStatement->isReconciled())
                <flux:badge color="green" icon="check-circle">{{ __('Reconciled :date', ['date' => $bankStatement->reconciled_at?->toFormattedDateString()]) }}</flux:badge>
            @elseif ($this->canReconcile())
                <flux:button icon="sparkles" wire:click="autoMatch">{{ __('Auto-match') }}</flux:button>
                <flux:button variant="primary" icon="check" wire:click="complete" :disabled="! $this->summary->canComplete()">{{ __('Complete reconciliation') }}</flux:button>
            @endif
        </div>
    </div>

    @include('livewire.finance.partials.nav')

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm">{{ __('Balance per books') }}</flux:text>
            <flux:heading size="lg">{{ $this->summary->bookBalance->format() }}</flux:heading>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm">{{ __('Outstanding in books') }}</flux:text>
            <flux:heading size="lg">{{ $this->summary->outstandingTotal->format() }}</flux:heading>
            <flux:text size="sm">{{ trans_choice(':count item|:count items', $this->summary->outstanding->count()) }}</flux:text>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm">{{ __('Balance per statement') }}</flux:text>
            <flux:heading size="lg">{{ $this->summary->statementBalance->format() }}</flux:heading>
        </div>
        <div @class(['rounded-xl border p-4', 'border-green-300 dark:border-green-700' => $this->summary->difference->isZero(), 'border-red-300 dark:border-red-700' => ! $this->summary->difference->isZero()])>
            <flux:text size="sm">{{ __('Difference') }}</flux:text>
            <flux:heading size="lg" data-test="difference">{{ $this->summary->difference->format() }}</flux:heading>
            @if ($this->summary->unmatchedLines->isNotEmpty())
                <flux:text size="sm">{{ trans_choice(':count bank line not in the books|:count bank lines not in the books', $this->summary->unmatchedLines->count()) }}</flux:text>
            @endif
        </div>
    </div>

    <div class="space-y-3">
        <flux:heading size="lg">{{ __('Bank lines') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Matched to') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->lines as $line)
                    <flux:table.row :key="$line->id">
                        <flux:table.cell>{{ $line->posted_on->toFormattedDateString() }}</flux:table.cell>
                        <flux:table.cell>{{ $line->description }} <flux:text size="sm">{{ $line->reference }}</flux:text></flux:table.cell>
                        <flux:table.cell align="end">{{ App\Support\Finance\Money::of($line->amount_cents)->format() }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($line->ledgerEntry)
                                <flux:badge size="sm" color="green">{{ $line->ledgerEntry->posted_on->toFormattedDateString() }}</flux:badge>
                                <flux:text size="sm">{{ $line->ledgerEntry->journalEntry->memo }}</flux:text>
                            @else
                                <flux:badge size="sm" color="amber">{{ __('Unmatched') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($this->canReconcile())
                                @if ($line->isMatched())
                                    <flux:button size="sm" variant="ghost" wire:click="unmatch({{ $line->id }})">{{ __('Unmatch') }}</flux:button>
                                @else
                                    <flux:button size="sm" variant="ghost" wire:click="selectLine({{ $line->id }})">{{ __('Resolve') }}</flux:button>
                                @endif
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    @if ($this->summary->outstanding->isNotEmpty())
        <div class="space-y-3">
            <flux:heading size="lg">{{ __('In the books, not yet on a statement') }}</flux:heading>
            <flux:text>{{ __('Cheques and deposits that haven\'t cleared the bank. They should appear on a later statement.') }}</flux:text>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Entry') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->summary->outstanding as $entry)
                        <flux:table.row :key="'outstanding-'.$entry->id">
                            <flux:table.cell>{{ $entry->posted_on->toFormattedDateString() }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->journalEntry->memo }}</flux:table.cell>
                            <flux:table.cell align="end">{{ App\Support\Finance\Money::of($entry->netCents())->format() }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    <flux:modal name="resolve-line" class="w-full max-w-xl">
        @if ($this->selectedLine)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ $this->selectedLine->description }}</flux:heading>
                    <flux:text>{{ $this->selectedLine->posted_on->toFormattedDateString() }} · {{ App\Support\Finance\Money::of($this->selectedLine->amount_cents)->format() }}</flux:text>
                </div>

                <div class="space-y-2">
                    <flux:heading>{{ __('Match to a book entry') }}</flux:heading>
                    @forelse ($this->candidates as $candidate)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="candidate-{{ $candidate->id }}">
                            <div>
                                <flux:text variant="strong">{{ $candidate->posted_on->toFormattedDateString() }}</flux:text>
                                <flux:text size="sm">{{ $candidate->journalEntry->memo }}</flux:text>
                            </div>
                            <flux:button size="sm" wire:click="match({{ $candidate->id }})">{{ __('Match') }}</flux:button>
                        </div>
                    @empty
                        <flux:text>{{ __('No unmatched book entry for this exact amount.') }}</flux:text>
                    @endforelse
                    <flux:error name="match" />
                </div>

                <flux:separator :text="__('or')" />

                <form wire:submit="record" class="space-y-3">
                    <flux:heading>{{ __('Record it in the books') }}</flux:heading>
                    <flux:text size="sm">{{ __('For bank charges, interest and anything else the books don\'t have yet.') }}</flux:text>
                    <flux:select wire:model="counter_account_id" :label="__('Account')">
                        <flux:select.option value="">{{ __('Choose an account') }}</flux:select.option>
                        @foreach ($this->counterAccounts as $account)
                            <flux:select.option :value="$account->id">{{ $account->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary">{{ __('Record and match') }}</flux:button>
                    </div>
                </form>
            </div>
        @endif
    </flux:modal>
</section>
