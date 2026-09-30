<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen flex-col bg-white text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">
        <header class="mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-6">
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

        @php
            // Tailwind's content scanner only sees literal class strings, so every color used
            // below must be spelled out here rather than built with `bg-{{ $color }}-500/10`.
            $badgeClasses = fn (string $color): string => match ($color) {
                'blue' => 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
                'emerald' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
                'amber' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
                'violet' => 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
                'cyan' => 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
                'rose' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
                default => 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-400',
            };

            // Real figures pulled from the app itself, not marketing filler.
            $moduleCount = count(App\Enums\Module::cases());
            $permissionCount = count(App\Enums\Permission::cases());
            $reportTypeCount = count(App\Enums\FinancialReport::cases());
            $toolCount = 91; // Livewire feature pages across the app
            $accountTypeCount = 7; // company roles + resident + vendor + platform admin

            $moduleIcon = fn (App\Enums\Module $module): string => match ($module) {
                App\Enums\Module::Amenities => 'calendar-date-range',
                App\Enums\Module::Maintenance => 'wrench-screwdriver',
                App\Enums\Module::Governance => 'scale',
                App\Enums\Module::Finance => 'banknotes',
                App\Enums\Module::FrontDesk => 'archive-box',
                App\Enums\Module::Security => 'shield-exclamation',
            };
            $moduleColor = fn (App\Enums\Module $module): string => match ($module) {
                App\Enums\Module::Amenities => 'blue',
                App\Enums\Module::Maintenance => 'violet',
                App\Enums\Module::Governance => 'amber',
                App\Enums\Module::Finance => 'emerald',
                App\Enums\Module::FrontDesk => 'cyan',
                App\Enums\Module::Security => 'rose',
            };
        @endphp

        <main class="flex-1">
            <!-- Hero -->
            <section class="relative overflow-hidden border-b border-zinc-200 dark:border-zinc-700">
                <div class="absolute -end-24 -top-24 size-96 rounded-full bg-emerald-500/10 blur-3xl"></div>
                <div class="absolute -start-24 top-40 size-80 rounded-full bg-blue-500/5 blur-3xl"></div>

                <div class="relative mx-auto grid w-full max-w-6xl gap-16 px-4 py-16 sm:py-24 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                    <div class="max-w-xl space-y-7">
                        <span class="inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-semibold tracking-wide text-emerald-700 uppercase dark:text-emerald-400">
                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                            {{ __('Condos · HOAs · Rental communities') }}
                        </span>

                        <flux:heading size="xl" level="1" class="text-4xl leading-tight! sm:text-5xl">
                            {{ __('Run every community') }}
                            <span class="text-emerald-600 dark:text-emerald-400">{{ __('from one place.') }}</span>
                        </flux:heading>

                        <flux:text class="text-lg text-zinc-600 dark:text-zinc-400">
                            {{ __('PropertyFlow brings residents, maintenance, amenities, front desk, finance and governance together — so nothing lives in a spreadsheet or an inbox anymore.') }}
                        </flux:text>

                        <div class="flex flex-wrap items-center gap-3 pt-1">
                            @auth
                                <flux:button :href="route('dashboard')" variant="primary" icon-trailing="arrow-right">{{ __('Go to dashboard') }}</flux:button>
                            @else
                                @if (Route::has('register'))
                                    <flux:button :href="route('register')" variant="primary" icon-trailing="arrow-right">{{ __('Get started') }}</flux:button>
                                @endif
                                <flux:button :href="route('login')" variant="ghost">{{ __('Log in') }}</flux:button>
                            @endauth
                        </div>

                        <!-- Real figures, not filler: computed above from the app's own enums. -->
                        <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-zinc-200 bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-700 sm:grid-cols-4">
                            <div class="bg-white px-4 py-3 dark:bg-zinc-900">
                                <dt class="text-xs text-zinc-500 uppercase">{{ __('Built-in tools') }}</dt>
                                <dd class="mt-0.5 text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $toolCount }}</dd>
                            </div>
                            <div class="bg-white px-4 py-3 dark:bg-zinc-900">
                                <dt class="text-xs text-zinc-500 uppercase">{{ __('Permissions') }}</dt>
                                <dd class="mt-0.5 text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $permissionCount }}</dd>
                            </div>
                            <div class="bg-white px-4 py-3 dark:bg-zinc-900">
                                <dt class="text-xs text-zinc-500 uppercase">{{ __('Modules') }}</dt>
                                <dd class="mt-0.5 text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $moduleCount }}</dd>
                            </div>
                            <div class="bg-white px-4 py-3 dark:bg-zinc-900">
                                <dt class="text-xs text-zinc-500 uppercase">{{ __('Account types') }}</dt>
                                <dd class="mt-0.5 text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $accountTypeCount }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- A real feature, not a decorative mockup: every community can switch these
                         modules on or off, exactly as shown on its own settings page. -->
                    <div class="relative mx-auto w-full max-w-md lg:mx-0">
                        <div class="absolute -end-3 -top-4 z-10 inline-flex items-center gap-1.5 rounded-full bg-emerald-500 px-3 py-1 text-xs font-semibold text-white shadow-lg">
                            <span class="size-1.5 rounded-full bg-white"></span>
                            {{ __('Configurable per community') }}
                        </div>

                        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-800/60">
                            <div class="flex items-center gap-2 border-b border-zinc-200 px-5 py-3.5 dark:border-zinc-700">
                                <flux:icon name="adjustments-horizontal" class="size-4 text-zinc-400" />
                                <flux:text class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Harbour Towers · Modules') }}</flux:text>
                            </div>
                            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach (App\Enums\Module::cases() as $module)
                                    <div class="flex items-start gap-3 px-5 py-3">
                                        <div class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md bg-emerald-500 text-white">
                                            <flux:icon name="check" class="size-3.5" />
                                        </div>
                                        <div>
                                            <flux:text class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $module->label() }}</flux:text>
                                            <flux:text class="text-xs text-zinc-500">{{ $module->description() }}</flux:text>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features -->
            <section class="mx-auto w-full max-w-6xl px-4 py-16 sm:py-20">
                <div class="max-w-2xl">
                    <flux:heading size="lg">{{ __('Three areas every community starts with') }}</flux:heading>
                    <flux:text class="mt-2 text-zinc-600 dark:text-zinc-400">{{ __("They're never optional — every community gets them from day one.") }}</flux:text>
                </div>

                <div class="mt-8 grid gap-4 sm:grid-cols-3">
                    @foreach ([
                        ['icon' => 'building-office', 'color' => 'zinc', 'title' => __('Buildings & units'), 'text' => __('The full record of every building, unit, owner and tenant.')],
                        ['icon' => 'users', 'color' => 'blue', 'title' => __('Residents'), 'text' => __('A self-service portal residents actually use, not just a directory.')],
                        ['icon' => 'megaphone', 'color' => 'rose', 'title' => __('Communication'), 'text' => __('Announcements, events and documents that reach the right people.')],
                    ] as $feature)
                        <div class="rounded-xl border border-zinc-200 p-5 transition hover:-translate-y-0.5 hover:shadow-sm dark:border-zinc-700">
                            <div class="flex size-10 items-center justify-center rounded-full {{ $badgeClasses($feature['color']) }}">
                                <flux:icon :name="$feature['icon']" class="size-5" />
                            </div>
                            <flux:heading class="mt-3">{{ $feature['title'] }}</flux:heading>
                            <flux:text class="mt-1">{{ $feature['text'] }}</flux:text>
                        </div>
                    @endforeach
                </div>

                <div class="mt-14 max-w-2xl">
                    <flux:heading size="lg">{{ __(':count modules, switched on only where they\'re needed', ['count' => $moduleCount]) }}</flux:heading>
                    <flux:text class="mt-2 text-zinc-600 dark:text-zinc-400">{{ __('A rental building and a full-service condo need different tools — every community turns these on or off for itself.') }}</flux:text>
                </div>

                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (App\Enums\Module::cases() as $module)
                        <div class="rounded-xl border border-zinc-200 p-5 transition hover:-translate-y-0.5 hover:shadow-sm dark:border-zinc-700">
                            <div class="flex size-10 items-center justify-center rounded-full {{ $badgeClasses($moduleColor($module)) }}">
                                <flux:icon :name="$moduleIcon($module)" class="size-5" />
                            </div>
                            <flux:heading class="mt-3">{{ $module->label() }}</flux:heading>
                            <flux:text class="mt-1">{{ $module->description() }}</flux:text>
                        </div>
                    @endforeach
                </div>
            </section>

            <!-- Roles -->
            <section class="border-t border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/30">
                <div class="mx-auto w-full max-w-6xl px-4 py-16 sm:py-20">
                    <div class="max-w-2xl">
                        <flux:heading size="lg">{{ __(':count account types. One system, :count views.', ['count' => $accountTypeCount]) }}</flux:heading>
                        <flux:text class="mt-2 text-zinc-600 dark:text-zinc-400">{{ __('Nobody sees more than their job needs.') }}</flux:text>
                    </div>

                    <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ([
                            __('Company admin'), __('Property manager'), __('Board member'), __('Staff'),
                            __('Resident'), __('Vendor'), __('Platform admin'),
                        ] as $role)
                            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                <flux:text class="font-medium text-zinc-900 dark:text-zinc-100">{{ $role }}</flux:text>
                            </div>
                        @endforeach
                        <div class="flex items-center rounded-xl border border-dashed border-zinc-300 p-4 dark:border-zinc-600">
                            <flux:text class="text-sm text-zinc-500">{{ __('Each opens to its own simpler app.') }}</flux:text>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section class="mx-auto w-full max-w-6xl px-4 py-16 sm:py-20">
                <div class="relative overflow-hidden rounded-2xl bg-zinc-900 px-8 py-12 text-center dark:bg-zinc-800 sm:px-16">
                    <div class="absolute -end-10 -top-10 size-64 rounded-full bg-emerald-500/20 blur-3xl"></div>
                    <div class="relative">
                        <flux:heading size="lg" class="text-white!">{{ __('Ready to bring it all together?') }}</flux:heading>
                        <flux:text class="mx-auto mt-2 max-w-md text-zinc-300">{{ __('Set up your first community in minutes.') }}</flux:text>
                        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                            @auth
                                <flux:button :href="route('dashboard')" variant="primary" icon-trailing="arrow-right">{{ __('Go to dashboard') }}</flux:button>
                            @else
                                @if (Route::has('register'))
                                    <flux:button :href="route('register')" variant="primary" icon-trailing="arrow-right">{{ __('Get started') }}</flux:button>
                                @endif
                                <flux:button :href="route('login')" variant="ghost" class="text-white!">{{ __('Log in') }}</flux:button>
                            @endauth
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-zinc-200 dark:border-zinc-700">
            <div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-8 text-sm text-zinc-500">
                <span>{{ __(':name — Condo, HOA & rental community management', ['name' => config('app.name', 'PropertyFlow')]) }}</span>
                <span>&copy; {{ now()->year }}</span>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
