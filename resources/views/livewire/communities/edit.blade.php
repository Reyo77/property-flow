<section class="w-full max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Edit :name', ['name' => $community->name]) }}</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        @include('livewire.communities.partials.form-fields')

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            <flux:button :href="route('communities.show', $community)" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>

    @can('delete', $community)
        <flux:separator />

        <div class="space-y-3">
            <flux:heading>{{ __('Delete community') }}</flux:heading>
            <flux:text>{{ __('The community, its buildings and its units will be removed from all lists.') }}</flux:text>

            <flux:modal.trigger name="confirm-community-deletion">
                <flux:button variant="danger">{{ __('Delete community') }}</flux:button>
            </flux:modal.trigger>
        </div>

        <flux:modal name="confirm-community-deletion" class="max-w-lg">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete :name?', ['name' => $community->name]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('This removes the community for everyone in your company.') }}</flux:text>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endcan
</section>
