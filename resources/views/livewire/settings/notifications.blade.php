<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Notification settings') }}</flux:heading>

    <x-settings.layout :heading="__('Notifications')" :subheading="__('Choose what you want to be notified about.')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            @foreach ($this->categories() as $category)
                <div class="space-y-2">
                    <flux:heading size="sm">{{ $category->label() }}</flux:heading>
                    <flux:text class="text-sm">{{ $category->description() }}</flux:text>

                    <div class="flex flex-wrap gap-6 pt-1">
                        <flux:checkbox wire:model="inApp.{{ $category->value }}" :label="__('In-app')" />
                        <flux:checkbox disabled :label="__('Email (coming soon)')" />
                        <flux:checkbox disabled :label="__('SMS (coming soon)')" />
                    </div>
                </div>
            @endforeach

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </x-settings.layout>
</section>
