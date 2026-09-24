<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen items-center justify-center bg-white dark:bg-zinc-900">
        <section class="mx-auto w-full max-w-lg space-y-4 px-4 text-center">
            <flux:icon.check-circle class="mx-auto size-12 text-green-500" />
            <flux:heading size="xl" level="1">{{ __('Checkpoint scanned') }}</flux:heading>
            <flux:text>
                {{ $checkpoint->name }} &middot; {{ $checkpoint->patrolRoute->name }}
            </flux:text>
            <flux:text class="text-sm">{{ $scan->scanned_at->format('M j, Y g:ia') }}</flux:text>
        </section>
    </body>
</html>
