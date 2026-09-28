<section class="w-full space-y-6">
    <flux:heading size="xl" level="1">{{ __('Data deletion requests') }}</flux:heading>
    <flux:subheading>{{ __('Residents ask here for their personal data to be erased. Approving anonymizes their record; their history stays on record.') }}</flux:subheading>

    @if ($this->requests->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No requests yet') }}</flux:heading>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Resident') }}</flux:table.column>
                <flux:table.column>{{ __('Requested by') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->requests as $request)
                    <flux:table.row :key="$request->id">
                        <flux:table.cell>{{ $request->resident->name }}</flux:table.cell>
                        <flux:table.cell>{{ $request->requestedBy->name }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$request->status->color()">{{ $request->status->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($request->status->isOpen())
                                <flux:button size="sm" wire:click="openDecision({{ $request->id }})">{{ __('Review') }}</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="decide-request" class="w-full max-w-lg">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Review deletion request') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Approving erases the resident\'s name, email and phone immediately and cannot be undone. Their residency, invoice and vote history stays intact.') }}</flux:text>
            </div>

            <flux:textarea wire:model="decisionNotes" :label="__('Notes (optional)')" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button wire:click="deny" variant="filled">{{ __('Deny') }}</flux:button>
                <flux:button wire:click="approve" variant="danger" wire:confirm="{{ __('Erase this resident\'s personal data? This cannot be undone.') }}">{{ __('Approve & erase') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
