<section class="w-full max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('New survey or poll') }}</flux:heading>
        <flux:subheading>{{ $community->name }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="title" :label="__('Title')" required />
        <flux:textarea wire:model="description" :label="__('Introduction (optional)')" rows="2" />
        <div class="grid gap-4 sm:grid-cols-3">
            <flux:select wire:model="audience" :label="__('Who can answer')">
                @foreach (App\Enums\Audience::cases() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="closes_at" type="datetime-local" :label="__('Closes (optional)')" />
            <div class="space-y-2 pt-6">
                <flux:checkbox wire:model.live="is_poll" :label="__('Quick poll (one question, results shown to voters)')" />
                <flux:checkbox wire:model="is_anonymous" :label="__('Anonymous')" />
            </div>
        </div>

        <div class="space-y-4">
            @foreach ($questions as $q => $question)
                <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="sq-{{ $q }}">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <flux:input wire:model="questions.{{ $q }}.title" :label="__('Question :number', ['number' => $q + 1])" required />
                        </div>
                        <flux:select wire:model.live="questions.{{ $q }}.kind" :label="__('Answer type')">
                            @foreach (App\Enums\SurveyQuestionKind::cases() as $kind)
                                <flux:select.option :value="$kind->value">{{ $kind->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    @if (App\Enums\SurveyQuestionKind::from($question['kind'])->hasOptions())
                        <div class="space-y-2 ps-4">
                            @foreach ($question['options'] as $o => $option)
                                <flux:input wire:model="questions.{{ $q }}.options.{{ $o }}" size="sm" :placeholder="__('Option :number', ['number' => $o + 1])" wire:key="sq-{{ $q }}-{{ $o }}" />
                            @endforeach
                            <flux:button size="sm" variant="ghost" icon="plus" wire:click="addOption({{ $q }})">{{ __('Add option') }}</flux:button>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <flux:checkbox wire:model="questions.{{ $q }}.is_required" :label="__('Required')" />
                        @if (count($questions) > 1)
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeQuestion({{ $q }})">{{ __('Remove') }}</flux:button>
                        @endif
                    </div>
                </div>
            @endforeach
            <flux:error name="questions" />
            @unless ($is_poll)
                <flux:button size="sm" icon="plus" wire:click="addQuestion">{{ __('Add question') }}</flux:button>
            @endunless
        </div>

        <div class="flex justify-end gap-2">
            <flux:button :href="route('communities.surveys.index', $community)" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Save draft') }}</flux:button>
        </div>
    </form>
</section>
