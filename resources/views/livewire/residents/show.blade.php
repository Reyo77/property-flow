<section class="w-full space-y-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $resident->name }}</flux:heading>
            <flux:subheading>{{ collect([$resident->email, $resident->phone])->filter()->implode(' · ') ?: __('No contact details') }}</flux:subheading>
        </div>

        @can('update', [$resident, $community])
            <flux:button icon="pencil-square" wire:click="editDetails">{{ __('Edit details') }}</flux:button>
        @endcan
    </div>

    @if ($resident->notes)
        <flux:callout icon="information-circle">
            <flux:callout.text>{{ $resident->notes }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="space-y-3">
        <flux:heading size="lg">{{ __('Homes in :community', ['community' => $community->name]) }}</flux:heading>
        <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->residencies as $residency)
                <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2" wire:key="residency-{{ $residency->id }}">
                    <div>
                        <flux:link :href="route('communities.units.show', [$community, $residency->unit])" wire:navigate>
                            {{ $residency->unit->building ? $residency->unit->building->name.' · ' : '' }}{{ __('Unit :number', ['number' => $residency->unit->number]) }}
                        </flux:link>
                        <flux:badge size="sm" class="ms-1">{{ $residency->type->label() }}</flux:badge>
                        @if ($residency->is_primary && $residency->isActive())
                            <flux:badge size="sm" color="blue">{{ __('Primary contact') }}</flux:badge>
                        @endif
                    </div>
                    <flux:text>
                        {{ $residency->moved_in_on?->toFormattedDateString() ?? __('Unknown') }}
                        → {{ $residency->moved_out_on?->toFormattedDateString() ?? __('present') }}
                    </flux:text>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="space-y-3">
        <flux:heading size="lg">{{ __('Resident portal') }}</flux:heading>
        @if ($resident->hasPortalAccess())
            <flux:text>{{ __('Has a login and can use the resident portal.') }}</flux:text>
        @else
            <flux:text>
                {{ $this->pendingInvitation
                    ? __('Invited :when. The link expires :expires.', ['when' => $this->pendingInvitation->updated_at?->diffForHumans(), 'expires' => $this->pendingInvitation->expires_at->diffForHumans()])
                    : __('No login yet.') }}
            </flux:text>
            @can('invite', [$resident, $community])
                <flux:button icon="link" wire:click="invite">{{ $this->pendingInvitation ? __('Create a new invitation link') : __('Invite to the portal') }}</flux:button>
            @elsecan('update', [$resident, $community])
                @if (! $resident->email)
                    <flux:text class="text-sm">{{ __('Add an email address to invite them.') }}</flux:text>
                @endif
            @endcan
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        @foreach ($this->recordKinds() as $kind)
            <livewire:residents.records :$resident :$community :$kind :key="$kind->value" />
        @endforeach
    </div>

    <flux:modal name="resident-details" class="w-full max-w-lg">
        <form wire:submit="saveDetails" class="space-y-5">
            <flux:heading size="lg">{{ __('Edit details') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="email" :label="__('Email')" type="email" />
            <flux:input wire:model="phone" :label="__('Phone')" />
            <flux:textarea wire:model="notes" :label="__('Notes')" :description="__('Visible to staff only.')" rows="3" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    @include('livewire.partials.invitation-link-modal')
</section>
