@php($pending = $this->billsToApprove->count() + $this->renovationRequests->count() + $this->violationsForReview->count() + $this->ballotsToClose->count())
<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Board portal') }}</flux:heading>
        <flux:subheading>{{ $community->name }}</flux:subheading>
    </div>

    <div class="space-y-3">
        <flux:heading size="lg">{{ trans_choice('{0} Nothing waiting on the board|{1} 1 item waiting on the board|[2,*] :count items waiting on the board', $pending) }}</flux:heading>
        <div class="grid gap-2" data-test="approvals">
            @foreach ($this->billsToApprove as $bill)
                <a href="{{ route('communities.finance.bills', [$community, 'status' => 'pending']) }}" wire:navigate class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                    <span><flux:badge size="sm" color="amber">{{ __('Bill') }}</flux:badge> {{ $bill->vendor->name }} · {{ $bill->description }}</span>
                    <span class="font-medium">{{ App\Support\Finance\Money::of($bill->amount_cents)->format() }}</span>
                </a>
            @endforeach
            @foreach ($this->renovationRequests as $request)
                <a href="{{ route('communities.architectural-requests.show', [$community, $request]) }}" wire:navigate class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                    <span><flux:badge size="sm" color="blue">{{ __('Renovation') }}</flux:badge> {{ $request->title }} · {{ $request->unit->label() }}</span>
                    <span class="text-sm">{{ $request->status->label() }}</span>
                </a>
            @endforeach
            @foreach ($this->violationsForReview as $violation)
                <a href="{{ route('communities.violations.show', [$community, $violation]) }}" wire:navigate class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                    <span><flux:badge size="sm" color="red">{{ __('Violation') }}</flux:badge> {{ $violation->rule->title }} · {{ $violation->unit->label() }}</span>
                    <span class="text-sm">{{ trans_choice(':count fine|:count fines', $violation->fines_issued) }}</span>
                </a>
            @endforeach
            @foreach ($this->ballotsToClose as $ballot)
                <a href="{{ route('communities.ballots.show', [$community, $ballot]) }}" wire:navigate class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                    <span><flux:badge size="sm" color="green">{{ __('Ballot') }}</flux:badge> {{ $ballot->title }}</span>
                    <span class="text-sm">{{ __('Voting ended — close and count') }}</span>
                </a>
            @endforeach
        </div>
    </div>

    @if ($this->financials)
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Finances · fiscal :year to date', ['year' => $this->financials['year']]) }}</flux:heading>
                <flux:link :href="route('communities.finance.reports', $community)" wire:navigate>{{ __('Reports') }}</flux:link>
            </div>
            <div class="grid gap-3 sm:grid-cols-4">
                @foreach ([__('Cash in bank') => 'cash', __('Owed by owners') => 'receivables', __('Overdue') => 'overdue', __('Owed to vendors') => 'payables', __('Income') => 'income', __('Expenses') => 'expenses', __('Surplus (deficit)') => 'net'] as $label => $key)
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text size="sm">{{ $label }}</flux:text>
                        <flux:heading size="lg">{{ $this->financials[$key]->format() }}</flux:heading>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid gap-6 sm:grid-cols-2">
        <div class="space-y-2">
            <flux:heading size="lg">{{ __('Upcoming meetings') }}</flux:heading>
            @forelse ($this->upcomingMeetings as $meeting)
                <div><flux:link :href="route('communities.meetings.show', [$community, $meeting])" wire:navigate>{{ $meeting->title }}</flux:link> <flux:text size="sm">{{ App\Support\Governance\LocalTime::display($meeting->starts_at, $community) }}</flux:text></div>
            @empty
                <flux:text size="sm">{{ __('None scheduled.') }}</flux:text>
            @endforelse
        </div>
        <div class="space-y-2">
            <flux:heading size="lg">{{ __('Board documents') }}</flux:heading>
            @forelse ($this->boardDocuments as $document)
                <div><flux:link :href="route('communities.documents.download', [$community, $document])">{{ $document->title }}</flux:link></div>
            @empty
                <flux:text size="sm">{{ __('No board-only documents.') }}</flux:text>
            @endforelse
        </div>
    </div>
</section>
