<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Service requests') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        @can('create', [App\Models\ServiceRequest::class, $community])
            <flux:button variant="primary" icon="plus" :href="route('communities.service-requests.create', $community)" wire:navigate>
                {{ __('New request') }}
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:select wire:model.live="statusFilter" class="max-w-40">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach ($this->statuses() as $status)
                <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="categoryFilter" class="max-w-44">
            <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
            @foreach ($this->categories() as $category)
                <flux:select.option :value="$category->value">{{ $category->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="priorityFilter" class="max-w-40">
            <flux:select.option value="">{{ __('All priorities') }}</flux:select.option>
            @foreach ($this->priorities() as $priority)
                <flux:select.option :value="$priority->value">{{ $priority->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->serviceRequests">
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Unit') }}</flux:table.column>
            <flux:table.column>{{ __('Category') }}</flux:table.column>
            <flux:table.column>{{ __('Priority') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Reported') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->serviceRequests as $serviceRequest)
                <flux:table.row :key="$serviceRequest->id">
                    <flux:table.cell variant="strong">
                        <flux:link :href="route('communities.service-requests.show', [$community, $serviceRequest])" wire:navigate>
                            {{ $serviceRequest->title }}
                        </flux:link>
                        @if ($serviceRequest->isOverdue())
                            <flux:badge size="sm" color="red" class="ms-1">{{ __('Overdue') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $serviceRequest->unit ? (($serviceRequest->unit->building?->name.' · ') ?: '').$serviceRequest->unit->number : __('Common area') }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $serviceRequest->category->label() }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm">{{ $serviceRequest->priority->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$serviceRequest->status === App\Enums\ServiceRequestStatus::Closed ? 'zinc' : 'blue'">
                            {{ $serviceRequest->status->label() }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $serviceRequest->created_at?->diffForHumans() }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center">{{ __('No service requests match.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
