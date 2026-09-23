<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Residents') }}</flux:heading>
        <flux:subheading>{{ __(':community · add residents from a unit page', ['community' => $community->name]) }}</flux:subheading>
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name, email, phone or unit')" class="max-w-xs" />

        <flux:select wire:model.live="type" class="max-w-40">
            <flux:select.option value="">{{ __('All types') }}</flux:select.option>
            @foreach (App\Enums\ResidencyType::cases() as $residencyType)
                <flux:select.option :value="$residencyType->value">{{ $residencyType->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="status" class="max-w-40">
            <flux:select.option value="current">{{ __('Current') }}</flux:select.option>
            <flux:select.option value="past">{{ __('Moved out') }}</flux:select.option>
            <flux:select.option value="all">{{ __('Everyone') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$this->residents">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Unit') }}</flux:table.column>
            <flux:table.column>{{ __('Contact') }}</flux:table.column>
            <flux:table.column>{{ __('Portal') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->residents as $resident)
                <flux:table.row :key="$resident->id">
                    <flux:table.cell variant="strong">
                        <flux:link :href="route('communities.residents.show', [$community, $resident])" wire:navigate>{{ $resident->name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-normal">
                        @foreach ($resident->residencies as $residency)
                            <div>
                                <flux:link :href="route('communities.units.show', [$community, $residency->unit])" wire:navigate>{{ $this->unitLabel($residency) }}</flux:link>
                                <span class="text-xs">· {{ $residency->type->label() }}</span>
                            </div>
                        @endforeach
                    </flux:table.cell>
                    <flux:table.cell>
                        <div>{{ $resident->email }}</div>
                        <div class="text-xs">{{ $resident->phone }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($resident->hasPortalAccess())
                            <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                        @else
                            <flux:badge size="sm">{{ __('No login') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center">{{ __('No residents match.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
