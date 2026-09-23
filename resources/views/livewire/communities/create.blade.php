<section class="w-full max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('New community') }}</flux:heading>
        <flux:subheading>{{ __('Add a condo, HOA, co-op or rental property.') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        @include('livewire.communities.partials.form-fields')

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">{{ __('Create community') }}</flux:button>
            <flux:button :href="route('communities.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
