<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Renovation requests') }}</flux:heading>
            <flux:subheading>{{ $community->name }} · {{ __('Changes to units or exteriors need the board\'s approval first.') }}</flux:subheading>
        </div>
        @can('create', [App\Models\ArchitecturalRequest::class, $community])
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New request') }}</flux:button>
        @endcan
    </div>

    @if ($this->requests->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No requests') }}</flux:heading>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Request') }}</flux:table.column>
                <flux:table.column>{{ __('Unit') }}</flux:table.column>
                <flux:table.column>{{ __('Submitted') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->requests as $request)
                    <flux:table.row :key="$request->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('communities.architectural-requests.show', [$community, $request])" wire:navigate>{{ $request->title }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $request->unit->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $request->created_at?->toFormattedDateString() }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$request->status->color()">{{ $request->status->label() }}</flux:badge></flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="request-form" class="w-full max-w-lg">
        <form wire:submit="submit" class="space-y-5">
            <flux:heading size="lg">{{ __('New renovation request') }}</flux:heading>
            @if ($this->ownedUnits->count() > 1)
                <flux:select wire:model="unit_id" :label="__('Unit')">
                    @foreach ($this->ownedUnits as $unit)
                        <flux:select.option :value="$unit->id">{{ $unit->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            <flux:input wire:model="title" :label="__('What you want to do')" :placeholder="__('e.g. Replace carpet with hardwood')" required />
            <flux:textarea wire:model="description" :label="__('Details')" rows="4" required />
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="contractor" :label="__('Contractor (optional)')" />
                <flux:input wire:model="planned_start_on" type="date" :label="__('Planned start (optional)')" />
            </div>
            <flux:input type="file" wire:model="plans" :label="__('Plans, drawings or photos')" accept=".pdf,image/*" multiple />
            <flux:error name="plans.*" />
            <flux:error name="unit_id" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Submit to the board') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
