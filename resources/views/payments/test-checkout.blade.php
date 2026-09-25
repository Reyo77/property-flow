<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen items-center justify-center bg-zinc-100 dark:bg-zinc-900">
        <section class="mx-auto w-full max-w-md space-y-6 rounded-xl bg-white p-8 shadow dark:bg-zinc-800">
            <flux:badge color="amber">{{ __('Test mode — no money will move') }}</flux:badge>

            <div>
                <flux:text>{{ $unit->community->name }} · {{ __('Unit :unit', ['unit' => $unit->label()]) }}</flux:text>
                <flux:heading size="xl" level="1">{{ $checkout->amount->format() }}</flux:heading>
            </div>

            @if ($checkout->status === App\Enums\CheckoutStatus::Open)
                <form method="POST" action="{{ route('payments.test-checkout.complete', $checkout->reference) }}" class="flex gap-3">
                    @csrf
                    <flux:button type="submit" name="outcome" value="cancel" class="flex-1">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" name="outcome" value="pay" variant="primary" class="flex-1">{{ __('Pay :amount', ['amount' => $checkout->amount->format()]) }}</flux:button>
                </form>
            @else
                <flux:text>{{ __('This checkout is closed.') }}</flux:text>
            @endif
        </section>
    </body>
</html>
