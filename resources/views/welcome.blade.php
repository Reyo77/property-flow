<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen flex-col bg-white text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">
        <header class="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-6">
            <x-app-logo href="{{ route('home') }}" />

            <nav class="flex items-center gap-2">
                @auth
                    <flux:button :href="route('dashboard')" variant="primary" size="sm">{{ __('Dashboard') }}</flux:button>
                @else
                    <flux:button :href="route('login')" variant="ghost" size="sm">{{ __('Log in') }}</flux:button>
                    @if (Route::has('register'))
                        <flux:button :href="route('register')" variant="primary" size="sm">{{ __('Get started') }}</flux:button>
                    @endif
                @endauth
            </nav>
        </header>

        <main class="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center gap-10 px-4 py-16">
            <div class="max-w-2xl space-y-4">
                <flux:heading size="xl" level="1">{{ __('Run every community from one place.') }}</flux:heading>
                <flux:text class="text-lg">
                    {{ __('PropertyFlow brings residents, maintenance, amenities, front desk, finance and governance together for condos, HOAs and rental communities.') }}
                </flux:text>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ([
                    ['icon' => 'users', 'title' => __('Residents'), 'text' => __('Units, owners, tenants and self-service portals.')],
                    ['icon' => 'wrench-screwdriver', 'title' => __('Operations'), 'text' => __('Requests, bookings, packages and visitors.')],
                    ['icon' => 'scale', 'title' => __('Governance'), 'text' => __('Finance, violations, voting and board approvals.')],
                ] as $feature)
                    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                        <flux:icon :name="$feature['icon']" class="mb-3 size-6" />
                        <flux:heading>{{ $feature['title'] }}</flux:heading>
                        <flux:text class="mt-1">{{ $feature['text'] }}</flux:text>
                    </div>
                @endforeach
            </div>
        </main>

        @fluxScripts
    </body>
</html>
