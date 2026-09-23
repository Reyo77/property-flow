<div class="grid gap-6 sm:grid-cols-2">
    <flux:input wire:model="form.name" :label="__('Name')" required class="sm:col-span-2" />

    <flux:select wire:model="form.type" :label="__('Type')">
        @foreach (App\Enums\CommunityType::cases() as $type)
            <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:select wire:model="form.area_unit" :label="__('Area unit')">
        @foreach (App\Enums\AreaUnit::cases() as $unit)
            <flux:select.option :value="$unit->value">{{ $unit->label() }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:input wire:model="form.address_line_1" :label="__('Address line 1')" class="sm:col-span-2" />
    <flux:input wire:model="form.address_line_2" :label="__('Address line 2')" class="sm:col-span-2" />
    <flux:input wire:model="form.city" :label="__('City')" />
    <flux:input wire:model="form.region" :label="__('State / province')" />
    <flux:input wire:model="form.postal_code" :label="__('Postal code')" />
    <flux:input wire:model="form.country" :label="__('Country code')" :description="__('Two letters, e.g. CA or US')" maxlength="2" required />

    <flux:select wire:model="form.timezone" :label="__('Timezone')" searchable>
        @foreach (DateTimeZone::listIdentifiers() as $timezone)
            <flux:select.option :value="$timezone">{{ $timezone }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:input wire:model="form.currency" :label="__('Currency code')" :description="__('Three letters, e.g. CAD or USD')" maxlength="3" required />
</div>
