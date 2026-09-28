<x-public-site.layout :community="$community">
    <flux:heading size="xl" level="1">{{ __('Contact :name', ['name' => $community->name]) }}</flux:heading>

    @if ($submitted)
        <div class="mt-6 rounded-xl border border-green-200 bg-green-50 p-6 dark:border-green-900 dark:bg-green-950">
            <flux:heading>{{ __('Message sent') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Thanks for reaching out. The team will get back to you.') }}</flux:text>
        </div>
    @else
        <form wire:submit="submit" class="mt-6 max-w-lg space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus />
            <flux:input wire:model="email" :label="__('Email')" type="email" required />
            <flux:textarea wire:model="message" :label="__('Message')" rows="5" required />

            <flux:button variant="primary" type="submit">{{ __('Send') }}</flux:button>
        </form>
    @endif
</x-public-site.layout>
