<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Ballots') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>
        @if ($this->canCreate())
            <flux:button variant="primary" icon="plus" :href="route('communities.ballots.create', $community)" wire:navigate>{{ __('New ballot') }}</flux:button>
        @endif
    </div>

    @if ($this->ballots->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No ballots yet') }}</flux:heading>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Ballot') }}</flux:table.column>
                <flux:table.column>{{ __('Voting closes') }}</flux:table.column>
                <flux:table.column>{{ __('Units voted') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->ballots as $ballot)
                    @php($status = $ballot->status())
                    <flux:table.row :key="$ballot->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('communities.ballots.show', [$community, $ballot])" wire:navigate>{{ $ballot->title }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ App\Support\LocalTime::display($ballot->closes_at, $community) }}</flux:table.cell>
                        <flux:table.cell>{{ $ballot->votes_count }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$status->color()">{{ $status->label() }}</flux:badge></flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
