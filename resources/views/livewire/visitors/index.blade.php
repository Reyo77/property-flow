<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Visitors') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Log visitor') }}</flux:button>
    </div>

    <form wire:submit="redeem" class="flex flex-wrap items-end gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:input wire:model="redeemCode" :label="__('Redeem guest pass code')" placeholder="ABC123" class="uppercase" />
        <flux:button type="submit">{{ __('Redeem') }}</flux:button>
    </form>

    @if ($this->visitors->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No visitors logged yet') }}</flux:heading>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Visitor') }}</flux:table.column>
                <flux:table.column>{{ __('Unit') }}</flux:table.column>
                <flux:table.column>{{ __('Purpose') }}</flux:table.column>
                <flux:table.column>{{ __('Checked in') }}</flux:table.column>
                <flux:table.column>{{ __('Checked out') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->visitors as $visitor)
                    <flux:table.row :key="$visitor->id">
                        <flux:table.cell variant="strong">{{ $visitor->visitor_name }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($visitor->unit)
                                {{ ($visitor->unit->building?->name.' · ') ?: '' }}{{ $visitor->unit->number }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $visitor->purpose }}</flux:table.cell>
                        <flux:table.cell>{{ $visitor->checked_in_at->format('M j, g:ia') }}</flux:table.cell>
                        <flux:table.cell>{{ $visitor->checked_out_at?->format('M j, g:ia') }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($visitor->checked_out_at === null)
                                <flux:button size="sm" wire:click="checkOut({{ $visitor->id }})">{{ __('Check out') }}</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="visitor-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ __('Log visitor') }}</flux:heading>

            <flux:input wire:model="visitor_name" :label="__('Visitor name')" required />

            <flux:select wire:model="unit_id" :label="__('Unit (optional)')">
                <flux:select.option value="">{{ __('Common area / unknown') }}</flux:select.option>
                @foreach ($this->units as $unit)
                    <flux:select.option :value="$unit->id">{{ ($unit->building?->name.' · ') ?: '' }}{{ $unit->number }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="purpose" :label="__('Purpose (optional)')" />
            <flux:textarea wire:model="notes" :label="__('Notes (optional)')" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Log visitor') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
