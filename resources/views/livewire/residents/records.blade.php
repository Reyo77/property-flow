<div class="space-y-3">
    @php($canEdit = auth()->user()->can('update', [$resident, $community]))

    <div class="flex items-center justify-between">
        <flux:heading>{{ $kind->title() }}</flux:heading>
        @if ($canEdit)
            <flux:button size="sm" variant="ghost" icon="plus" wire:click="create">{{ __('Add') }}</flux:button>
        @endif
    </div>

    @if ($this->records->isEmpty())
        <flux:text>{{ __('None recorded.') }}</flux:text>
    @else
        <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->records as $record)
                <li class="flex items-center justify-between gap-3 px-3 py-2" wire:key="{{ $kind->value }}-{{ $record->getKey() }}">
                    <flux:text class="text-zinc-800 dark:text-zinc-200">
                        {{ collect(array_keys($kind->fields()))->map(fn ($field) => $record->getAttribute($field))->filter()->implode(' · ') }}
                    </flux:text>
                    @if ($canEdit)
                        <div class="flex shrink-0 gap-1">
                            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit({{ $record->getKey() }})" :aria-label="__('Edit')" />
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="delete({{ $record->getKey() }})" wire:confirm="{{ __('Remove this :record?', ['record' => $kind->singular()]) }}" :aria-label="__('Remove')" />
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <flux:modal :name="$this->modalName()" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? __('Edit :record', ['record' => $kind->singular()]) : __('Add :record', ['record' => $kind->singular()]) }}</flux:heading>

            @foreach ($kind->fields() as $field => $label)
                <flux:input wire:model="values.{{ $field }}" :label="$label" />
            @endforeach

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
