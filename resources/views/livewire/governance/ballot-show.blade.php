@php($status = $this->status)
<section class="w-full max-w-4xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $ballot->title }}</flux:heading>
            <flux:subheading>
                {{ $community->name }}
                @if ($ballot->meeting)
                    · <flux:link :href="route('communities.meetings.show', [$community, $ballot->meeting])" wire:navigate>{{ $ballot->meeting->title }}</flux:link>
                @endif
            </flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge :color="$status->color()">{{ $status->label() }}</flux:badge>
            @if ($this->canManage())
                @can('update', $ballot)
                    <flux:button size="sm" icon="pencil" :href="route('communities.ballots.edit', [$community, $ballot])" wire:navigate>{{ __('Edit') }}</flux:button>
                    <flux:button size="sm" variant="primary" wire:click="publish" wire:confirm="{{ __('Publish to owners? The wording can\'t be changed afterwards.') }}">{{ __('Publish') }}</flux:button>
                @endcan
                @if ($status === App\Enums\BallotStatus::Ended)
                    <flux:button size="sm" variant="primary" wire:click="close">{{ __('Close and count') }}</flux:button>
                @endif
            @endif
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm">{{ __('Voting') }}</flux:text>
            <flux:text variant="strong">{{ App\Support\LocalTime::display($ballot->opens_at, $community) }}</flux:text>
            <flux:text variant="strong">{{ __('to :time', ['time' => App\Support\LocalTime::display($ballot->closes_at, $community)]) }}</flux:text>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm">{{ __('Counting') }}</flux:text>
            <flux:text variant="strong">{{ $ballot->weighting->label() }}</flux:text>
            <flux:text size="sm">{{ __('Quorum: :percent% of votes', ['percent' => $ballot->quorum_percent]) }}</flux:text>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm">{{ __('Units voted') }}</flux:text>
            <flux:heading size="lg" data-test="turnout">{{ $this->turnout['voted'] }} / {{ $this->turnout['eligible'] }}</flux:heading>
            <flux:text size="sm">{{ __('Owner-occupied or owned units only') }}</flux:text>
        </div>
    </div>

    @if ($ballot->description)
        <flux:text class="whitespace-pre-line">{{ $ballot->description }}</flux:text>
    @endif

    @if ($ballot->results !== null)
        @can('viewResults', $ballot)
            <div class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" data-test="results">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:heading size="lg">{{ __('Results') }}</flux:heading>
                    <flux:badge :color="$ballot->results['quorum_met'] ? 'green' : 'red'">
                        {{ $ballot->results['quorum_met'] ? __('Quorum met') : __('Quorum not met') }} · {{ __(':percent% turnout', ['percent' => $ballot->results['turnout_percent']]) }}
                    </flux:badge>
                </div>
                @foreach ($ballot->results['questions'] as $question)
                    <div class="space-y-2">
                        <flux:text variant="strong">{{ $question['title'] }}</flux:text>
                        @foreach ($question['options'] as $option)
                            <div>
                                <div class="flex justify-between text-sm">
                                    <span>{{ $option['label'] }}</span>
                                    <span>{{ $option['percent'] }}% · {{ trans_choice(':count unit|:count units', $option['votes']) }}</span>
                                </div>
                                <div class="h-2 rounded bg-zinc-200 dark:bg-zinc-700"><div class="h-2 rounded bg-emerald-500" style="width: {{ $option['percent'] }}%"></div></div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endcan
    @elseif ($this->canManage())
        <flux:text size="sm">{{ __('Results are counted and shown when voting closes — nobody sees how a vote is going while it is open.') }}</flux:text>
    @endif

    @if ($this->myUnits !== [])
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Your vote') }}</flux:heading>
            @foreach ($this->myUnits as $row)
                @php($unit = $row['unit'])
                <div class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" wire:key="unit-{{ $unit->id }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <flux:heading>
                            {{ __('Unit :unit', ['unit' => $unit->label()]) }}
                            @if ($row['via_proxy'])
                                <flux:badge size="sm" color="blue">{{ __('as proxy') }}</flux:badge>
                            @endif
                        </flux:heading>
                        @if ($row['vote'])
                            <flux:badge color="green" icon="check">{{ __('Voted :date', ['date' => App\Support\LocalTime::display($row['vote']->cast_at, $community)]) }}</flux:badge>
                        @endif
                    </div>

                    @if (! $row['vote'] && $row['proxy_out'])
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <flux:text>{{ __(':name is your proxy for this ballot.', ['name' => $row['proxy_out']->holder->name]) }}</flux:text>
                            <flux:button size="sm" variant="ghost" wire:click="revokeProxy({{ $row['proxy_out']->id }})">{{ __('Revoke') }}</flux:button>
                        </div>
                    @elseif (! $row['vote'] && $status === App\Enums\BallotStatus::Open)
                        <form wire:submit="vote({{ $unit->id }})" class="space-y-4">
                            @foreach ($this->questions as $question)
                                <flux:radio.group wire:model="choices.{{ $unit->id }}.{{ $question->id }}" :label="$question->title">
                                    @foreach ($question->options as $option)
                                        <flux:radio :value="$option->id" :label="$option->label" />
                                    @endforeach
                                </flux:radio.group>
                            @endforeach
                            <flux:error name="vote.{{ $unit->id }}" />
                            <div class="flex justify-end">
                                <flux:button type="submit" variant="primary" wire:confirm="{{ __('Cast this vote? Votes are final.') }}">{{ __('Cast vote') }}</flux:button>
                            </div>
                        </form>
                    @elseif (! $row['vote'] && in_array($status, [App\Enums\BallotStatus::Upcoming], true))
                        <flux:text>{{ __('Voting opens :time.', ['time' => App\Support\LocalTime::display($ballot->opens_at, $community)]) }}</flux:text>
                    @elseif (! $row['vote'])
                        <flux:text>{{ __('This unit did not vote.') }}</flux:text>
                    @endif

                    @if (! $row['vote'] && ! $row['via_proxy'] && ! $row['proxy_out'] && in_array($status, [App\Enums\BallotStatus::Upcoming, App\Enums\BallotStatus::Open], true))
                        <div class="flex flex-wrap items-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <div class="min-w-64 flex-1">
                                <flux:select wire:model="proxyHolder.{{ $unit->id }}" :label="__('Or appoint a proxy to vote for you')">
                                    <flux:select.option value="">{{ __('Choose someone') }}</flux:select.option>
                                    @foreach ($this->proxyCandidates as $candidate)
                                        <flux:select.option :value="$candidate->id">{{ $candidate->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <flux:button wire:click="appointProxy({{ $unit->id }})">{{ __('Appoint proxy') }}</flux:button>
                        </div>
                    @endif
                    <flux:error name="proxy.{{ $unit->id }}" />
                </div>
            @endforeach
        </div>
    @endif

    <div class="space-y-3">
        <flux:heading size="lg">{{ __('Questions') }}</flux:heading>
        @foreach ($this->questions as $question)
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text variant="strong">{{ $loop->iteration }}. {{ $question->title }}</flux:text>
                <flux:text size="sm">{{ $question->options->pluck('label')->implode(' · ') }}</flux:text>
            </div>
        @endforeach
    </div>
</section>
