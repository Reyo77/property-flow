<div class="flex flex-col gap-6">
    @if ($invitation === null)
        <x-auth-header :title="__('Invitation not valid')" :description="__('This invitation link has expired, was already used, or was replaced by a newer one. Ask whoever invited you for a new link.')" />

        <flux:button :href="route('login')" variant="primary" class="w-full" wire:navigate>{{ __('Go to log in') }}</flux:button>
    @else
        <x-auth-header
            :title="__('Join :company', ['company' => $companyName])"
            :description="$invitation->isForTeam() ? __('Set up your team login.') : __('Set up your resident portal login.')"
        />

        <form wire:submit="accept" class="flex flex-col gap-6">
            <flux:input :label="__('Email address')" :value="$invitation->email" readonly />
            <flux:input wire:model="name" :label="__('Your name')" required autocomplete="name" />
            <flux:input wire:model="password" :label="__('Password')" type="password" required autocomplete="new-password" viewable />
            <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" viewable />
            <flux:error name="invitation" />
            <flux:error name="email" />

            <flux:button type="submit" variant="primary" class="w-full" data-test="accept-invitation-button">{{ __('Create my login') }}</flux:button>
        </form>
    @endif
</div>
