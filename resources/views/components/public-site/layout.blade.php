@props(['community'])

<div class="min-h-screen">
    <header class="border-b border-zinc-200 dark:border-zinc-700">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
            <a href="{{ route('public.home', $community->slug) }}" class="flex items-center gap-3" wire:navigate>
                @if ($community->company->logo_disk_path !== null)
                    <img src="{{ route('companies.logo', $community->company) }}" alt="" class="h-8 w-8 rounded object-contain" />
                @endif
                <flux:heading size="lg">{{ $community->name }}</flux:heading>
            </a>

            <nav class="flex gap-5 text-sm">
                <a href="{{ route('public.home', $community->slug) }}" wire:navigate>{{ __('Home') }}</a>
                <a href="{{ route('public.news', $community->slug) }}" wire:navigate>{{ __('News') }}</a>
                <a href="{{ route('public.documents', $community->slug) }}" wire:navigate>{{ __('Documents') }}</a>
                <a href="{{ route('public.contact', $community->slug) }}" wire:navigate>{{ __('Contact') }}</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-10">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-5xl px-4 py-10 text-sm text-zinc-500">
        {{ __(':name · powered by :app', ['name' => $community->company->name, 'app' => config('app.name')]) }}
    </footer>
</div>
