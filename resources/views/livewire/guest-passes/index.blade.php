<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Guest passes') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New pass') }}</flux:button>
    </div>

    @if ($this->guestPasses->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No guest passes yet') }}</flux:heading>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->guestPasses as $pass)
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="pass-{{ $pass->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <flux:heading>{{ $pass->guest_name }}</flux:heading>
                        @if ($pass->used_at)
                            <flux:badge size="sm" color="zinc">{{ __('Used') }}</flux:badge>
                        @elseif ($pass->isValidToday())
                            <flux:badge size="sm" color="green">{{ __('Valid today') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="amber">{{ __('Not active') }}</flux:badge>
                        @endif
                    </div>
                    <flux:text class="mt-1 text-sm">
                        {{ ($pass->unit->building?->name.' · ') ?: '' }}{{ __('Unit :number', ['number' => $pass->unit->number]) }}
                    </flux:text>
                    <flux:text class="text-sm">
                        {{ $pass->valid_from->toFormattedDateString() }} &ndash; {{ $pass->valid_until->toFormattedDateString() }}
                    </flux:text>
                    <flux:heading size="lg" class="mt-3 font-mono tracking-widest">{{ $pass->code }}</flux:heading>
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal name="guest-pass-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ __('New guest pass') }}</flux:heading>

            <flux:input wire:model="guest_name" :label="__('Guest name')" required />

            @if ($this->canManage())
                <flux:select wire:model="unit_id" :label="__('Unit')">
                    <flux:select.option value="">{{ __('Choose a unit') }}</flux:select.option>
                    @foreach ($this->communityUnits as $unit)
                        <flux:select.option :value="$unit->id">{{ ($unit->building?->name.' · ') ?: '' }}{{ $unit->number }}</flux:select.option>
                    @endforeach
                </flux:select>
            @elseif ($this->myUnits->count() > 1)
                <flux:select wire:model="unit_id" :label="__('Unit')">
                    <flux:select.option value="">{{ __('Choose a unit') }}</flux:select.option>
                    @foreach ($this->myUnits as $unit)
                        <flux:select.option :value="$unit->id">{{ ($unit->building?->name.' · ') ?: '' }}{{ $unit->number }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="valid_from" :label="__('Valid from')" type="date" required />
                <flux:input wire:model="valid_until" :label="__('Valid until')" type="date" required />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create pass') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
