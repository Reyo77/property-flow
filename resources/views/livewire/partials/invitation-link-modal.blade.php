{{-- Shows a freshly issued invitation link once, with a copy button. Expects $issuedLink. --}}
<flux:modal name="invitation-link" class="w-full max-w-lg">
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Share this invitation link') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Send it by message or chat. It works once and expires in :days days. For security it will not be shown again, but you can create a new one.', ['days' => App\Models\Invitation::EXPIRES_AFTER_DAYS]) }}
            </flux:text>
        </div>

        @if ($issuedLink)
            <flux:input :value="$issuedLink" readonly copyable data-test="invitation-link" />
        @endif

        <div class="flex justify-end">
            <flux:modal.close>
                <flux:button variant="primary">{{ __('Done') }}</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
