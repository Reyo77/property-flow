<section class="w-full max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ $ballot ? __('Edit ballot') : __('New ballot') }}</flux:heading>
        <flux:subheading>{{ $community->name }} · {{ __('Times are :timezone.', ['timezone' => $community->timezone]) }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="title" :label="__('Title')" :placeholder="__('e.g. Approve the 2027 budget')" required />
        <flux:textarea wire:model="description" :label="__('Background for owners (optional)')" rows="3" />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="opens_at" type="datetime-local" :label="__('Voting opens')" required />
            <flux:input wire:model="closes_at" type="datetime-local" :label="__('Voting closes')" required />
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <flux:select wire:model="weighting" :label="__('Counting')">
                @foreach (App\Enums\VotingWeighting::cases() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="quorum_percent" type="number" min="0" max="100" :label="__('Quorum (% of votes)')" required />
            <flux:select wire:model="meeting_id" :label="__('At meeting (optional)')">
                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                @foreach ($this->meetings as $meeting)
                    <flux:select.option :value="$meeting->id">{{ $meeting->title }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Questions') }}</flux:heading>
            @foreach ($questions as $q => $question)
                <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="question-{{ $q }}">
                    <div class="flex items-start gap-2">
                        <div class="flex-1">
                            <flux:input wire:model="questions.{{ $q }}.title" :label="__('Question :number', ['number' => $q + 1])" required />
                        </div>
                        @if (count($questions) > 1)
                            <flux:button size="sm" variant="ghost" icon="trash" class="mt-6" wire:click="removeQuestion({{ $q }})" :aria-label="__('Remove question')" />
                        @endif
                    </div>
                    <div class="space-y-2 ps-4">
                        @foreach ($question['options'] as $o => $option)
                            <div class="flex items-center gap-2" wire:key="question-{{ $q }}-option-{{ $o }}">
                                <flux:input wire:model="questions.{{ $q }}.options.{{ $o }}" size="sm" :placeholder="__('Option')" :aria-label="__('Option :number', ['number' => $o + 1])" />
                                @if (count($question['options']) > 2)
                                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeOption({{ $q }}, {{ $o }})" :aria-label="__('Remove option')" />
                                @endif
                            </div>
                            <flux:error name="questions.{{ $q }}.options.{{ $o }}" />
                        @endforeach
                        <flux:error name="questions.{{ $q }}.options" />
                        <flux:button size="sm" variant="ghost" icon="plus" wire:click="addOption({{ $q }})">{{ __('Add option') }}</flux:button>
                    </div>
                </div>
            @endforeach
            <flux:error name="questions" />
            <flux:button size="sm" icon="plus" wire:click="addQuestion">{{ __('Add question') }}</flux:button>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button :href="route('communities.ballots.index', $community)" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Save draft') }}</flux:button>
        </div>
    </form>
</section>
