<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Forms to sign') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>
        @can('create', [App\Models\ConsentForm::class, $community])
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New form') }}</flux:button>
        @endcan
    </div>

    @if ($this->forms->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No forms') }}</flux:heading>
        </div>
    @else
        <div class="grid gap-3">
            @foreach ($this->forms as $form)
                <a href="{{ route('communities.consent-forms.show', [$community, $form]) }}" wire:navigate wire:key="cf-{{ $form->id }}"
                   class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                    <div>
                        <flux:heading>{{ $form->title }}</flux:heading>
                        <flux:text size="sm">{{ $form->audience->label() }}</flux:text>
                    </div>
                    <div class="flex items-center gap-2">
                        @if (in_array($form->id, $this->signed, true))
                            <flux:badge size="sm" color="green">{{ __('Signed') }}</flux:badge>
                        @elseif ($form->published_at === null)
                            <flux:badge size="sm">{{ __('Draft') }}</flux:badge>
                        @elseif ($form->isOpen())
                            <flux:badge size="sm" color="amber">{{ __('Awaiting signatures') }}</flux:badge>
                        @endif
                        <flux:text size="sm">{{ trans_choice(':count signature|:count signatures', $form->signatures_count) }}</flux:text>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @can('create', [App\Models\ConsentForm::class, $community])
        <flux:modal name="consent-form" class="w-full max-w-2xl">
            <form wire:submit="save" class="space-y-5">
                <flux:heading size="lg">{{ __('New form') }}</flux:heading>
                <flux:input wire:model="title" :label="__('Title')" :placeholder="__('e.g. Consent to electronic notices')" required />
                <flux:select wire:model="audience" :label="__('Who signs')">
                    @foreach (App\Enums\Audience::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:textarea wire:model="body" :label="__('Text of the form')" rows="10" required />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Save draft') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</section>
