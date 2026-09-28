<section class="w-full space-y-6">
    <flux:heading size="xl" level="1">{{ __('Companies') }}</flux:heading>
    <flux:subheading>{{ __('Every company on the platform.') }}</flux:subheading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Company') }}</flux:table.column>
            <flux:table.column>{{ __('Plan') }}</flux:table.column>
            <flux:table.column>{{ __('Communities') }}</flux:table.column>
            <flux:table.column>{{ __('Units') }}</flux:table.column>
            <flux:table.column>{{ __('Team') }}</flux:table.column>
            <flux:table.column>{{ __('Residents') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->companies as $company)
                <flux:table.row :key="$company->id">
                    <flux:table.cell>{{ $company->name }}</flux:table.cell>
                    <flux:table.cell>{{ $company->plan?->name ?? __('Unlimited') }}</flux:table.cell>
                    <flux:table.cell>{{ $company->communities_count }}</flux:table.cell>
                    <flux:table.cell>{{ $company->units_count }}</flux:table.cell>
                    <flux:table.cell>{{ $this->teamMemberCount($company) }}</flux:table.cell>
                    <flux:table.cell>{{ $company->residents_count }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($company->isSuspended())
                            <flux:badge size="sm" color="red">{{ __('Suspended') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-2">
                            @unless ($company->isSuspended())
                                <form method="POST" action="{{ route('platform.companies.impersonate', $company) }}">
                                    @csrf
                                    <flux:button size="sm" type="submit">{{ __('Impersonate') }}</flux:button>
                                </form>
                            @endunless

                            @if ($company->isSuspended())
                                <flux:button size="sm" variant="primary" wire:click="reactivate({{ $company->id }})">{{ __('Reactivate') }}</flux:button>
                            @else
                                <flux:button size="sm" variant="danger" wire:click="suspend({{ $company->id }})" wire:confirm="{{ __('Suspend :name? Every team member will be signed out.', ['name' => $company->name]) }}">
                                    {{ __('Suspend') }}
                                </flux:button>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
