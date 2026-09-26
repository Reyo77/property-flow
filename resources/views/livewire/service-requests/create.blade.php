<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('New service request') }}</flux:heading>
        <flux:subheading>{{ $community->name }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="title" :label="__('Title')" :placeholder="__('e.g. Leaking kitchen faucet')" required />
        <flux:textarea wire:model="description" :label="__('Description')" rows="4" required />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="category" :label="__('Category')">
                <flux:select.option value="">{{ __('Choose a category') }}</flux:select.option>
                @foreach ($this->categories() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="priority" :label="__('Priority')">
                @foreach ($this->priorities() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($this->canPickAnyUnit())
            <flux:select wire:model="unit_id" :label="__('Unit')">
                <flux:select.option value="">{{ __('Common area (no specific unit)') }}</flux:select.option>
                @foreach ($this->communityUnits as $unit)
                    <flux:select.option :value="$unit->id">{{ ($unit->building ? $unit->building->name.' · ' : '').$unit->number }}</flux:select.option>
                @endforeach
            </flux:select>
        @elseif ($this->myUnits->count() > 1)
            <flux:select wire:model="unit_id" :label="__('Unit')">
                @foreach ($this->myUnits as $unit)
                    <flux:select.option :value="$unit->id">{{ ($unit->building ? $unit->building->name.' · ' : '').$unit->number }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <flux:checkbox wire:model="entry_permission" :label="__('Staff or the vendor may enter the unit if nobody is home')" />

        <flux:input type="file" wire:model="photos" multiple :label="__('Photos (optional)')" :description="__('Up to 6 images, 8 MB each.')" accept="image/*" />
        <flux:error name="photos.*" />

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="photos,save">{{ __('Submit request') }}</flux:button>
            <flux:button :href="route('communities.service-requests.index', $community)" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
