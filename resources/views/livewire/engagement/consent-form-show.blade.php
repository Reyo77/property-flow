<section class="w-full max-w-3xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $consentForm->title }}</flux:heading>
            <flux:subheading>{{ $community->name }} · {{ $consentForm->audience->label() }}</flux:subheading>
        </div>
        @can('manage', $consentForm)
            <div class="flex gap-2">
                @if ($consentForm->published_at === null)
                    <flux:button size="sm" variant="primary" wire:click="publish" wire:confirm="{{ __('Publish? Residents can sign it from now on.') }}">{{ __('Publish') }}</flux:button>
                @elseif ($consentForm->isOpen())
                    <flux:button size="sm" wire:click="close">{{ __('Stop collecting') }}</flux:button>
                @endif
            </div>
        @endcan
    </div>

    <div class="rounded-xl border border-zinc-200 p-5 whitespace-pre-line dark:border-zinc-700">{{ $consentForm->body }}</div>

    @if ($this->mySignature)
        <flux:callout icon="check-circle" variant="success" :heading="__('You signed this on :date as :name.', ['date' => App\Support\Governance\LocalTime::display($this->mySignature->signed_at, $community), 'name' => $this->mySignature->signed_name])" />
    @elseif ($this->canSign)
        <form wire:submit="sign" class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading>{{ __('Sign') }}</flux:heading>
            <flux:input wire:model="signed_name" :label="__('Your full name')" required />
            <x-signature-pad model="signature" :label="__('Draw your signature')" />
            <flux:error name="signature" />
            <flux:checkbox wire:model="agreed" :label="__('I have read this form and agree to it.')" />
            <flux:error name="agreed" />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Sign form') }}</flux:button>
            </div>
        </form>
    @endif

    @can('manage', $consentForm)
        <div class="space-y-3">
            <flux:heading size="lg">{{ __('Signed by :count of :total', ['count' => $this->signatures->count(), 'total' => $this->audienceSize]) }}</flux:heading>
            @if ($this->signatures->isNotEmpty())
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name signed') }}</flux:table.column>
                        <flux:table.column>{{ __('Account') }}</flux:table.column>
                        <flux:table.column>{{ __('Signed') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->signatures as $signature)
                            <flux:table.row :key="$signature->id">
                                <flux:table.cell variant="strong">{{ $signature->signed_name }}</flux:table.cell>
                                <flux:table.cell>{{ $signature->user->email }}</flux:table.cell>
                                <flux:table.cell>{{ App\Support\Governance\LocalTime::display($signature->signed_at, $community) }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:link :href="route('communities.consent-forms.signature', [$community, $consentForm, $signature])" target="_blank">{{ __('Signature') }}</flux:link>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    @endcan
</section>
