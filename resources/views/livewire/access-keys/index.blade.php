<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Keys') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        @can('create', [App\Models\AccessKey::class, $community])
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add key') }}</flux:button>
        @endcan
    </div>

    @if ($this->keys->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No keys tracked yet') }}</flux:heading>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->keys as $key)
                @php($currentSignout = $key->signouts->first())
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="key-{{ $key->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <flux:heading>{{ $key->label }}</flux:heading>
                        @if ($currentSignout)
                            <flux:badge size="sm" :color="$currentSignout->isOverdue() ? 'red' : 'amber'">
                                {{ $currentSignout->isOverdue() ? __('Overdue') : __('Signed out') }}
                            </flux:badge>
                        @else
                            <flux:badge size="sm" color="green">{{ __('Available') }}</flux:badge>
                        @endif
                    </div>

                    @if ($key->unit)
                        <flux:text class="text-sm">{{ ($key->unit->building?->name.' · ') ?: '' }}{{ $key->unit->number }}</flux:text>
                    @endif

                    @if ($currentSignout)
                        <flux:text class="mt-2 text-sm">
                            {{ __('To: :name', ['name' => $currentSignout->signed_out_to]) }}
                            @if ($currentSignout->due_back_at)
                                <br>{{ __('Due: :date', ['date' => App\Support\LocalTime::local($currentSignout->due_back_at, $community)->format('M j, g:ia')]) }}
                            @endif
                        </flux:text>
                        @can('update', $key)
                            <flux:button size="sm" class="mt-3" wire:click="returnKey({{ $key->id }})">{{ __('Mark returned') }}</flux:button>
                        @endcan
                    @else
                        @can('update', $key)
                            <flux:button size="sm" class="mt-3" wire:click="openSignOut({{ $key->id }})">{{ __('Sign out') }}</flux:button>
                        @endcan
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal name="key-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ __('Add key') }}</flux:heading>

            <flux:input wire:model="label" :label="__('Label')" required />

            <flux:select wire:model="unit_id" :label="__('Unit (optional)')">
                <flux:select.option value="">{{ __('Not unit-specific') }}</flux:select.option>
                @foreach ($this->units as $unit)
                    <flux:select.option :value="$unit->id">{{ ($unit->building?->name.' · ') ?: '' }}{{ $unit->number }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="notes" :label="__('Notes (optional)')" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Add key') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="sign-out-form" class="w-full max-w-lg">
        <form wire:submit="signOut" class="space-y-5">
            <flux:heading size="lg">{{ __('Sign out key') }}</flux:heading>

            <flux:input wire:model="signed_out_to" :label="__('Signed out to')" required />
            <flux:input wire:model="signed_out_to_phone" :label="__('Phone (optional)')" />
            <flux:input wire:model="due_back_at" :label="__('Due back (optional)')" type="datetime-local" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Sign out') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
