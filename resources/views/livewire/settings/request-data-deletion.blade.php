<div class="mt-6 space-y-3 rounded-lg border border-red-200 p-4 dark:border-red-900">
    <flux:heading>{{ __('Delete my data') }}</flux:heading>
    <flux:subheading>{{ __('Ask the community to erase your personal information. Your history (invoices, votes, past residencies) stays on record, but your name, email and phone are removed once approved.') }}</flux:subheading>

    @if ($this->pendingRequest?->status->isOpen())
        <flux:badge color="amber">{{ __('Awaiting review') }}</flux:badge>
    @elseif ($this->pendingRequest?->status === \App\Enums\DataDeletionStatus::Denied)
        <flux:badge color="red">{{ __('Your last request was denied') }}</flux:badge>
        <form wire:submit="request" class="space-y-3">
            <flux:textarea wire:model="notes" :label="__('Notes (optional)')" rows="2" />
            <flux:button variant="danger" type="submit">{{ __('Request deletion') }}</flux:button>
        </form>
    @else
        <form wire:submit="request" class="space-y-3">
            <flux:textarea wire:model="notes" :label="__('Notes (optional)')" rows="2" />
            <flux:button variant="danger" type="submit" wire:confirm="{{ __('Request that your personal data be erased?') }}">{{ __('Request deletion') }}</flux:button>
        </form>
    @endif
</div>
