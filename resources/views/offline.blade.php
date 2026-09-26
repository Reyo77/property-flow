<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#18181b">
    <title>{{ __('You\'re offline') }} - {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    {{-- Styles are inline: this page is shown when nothing else can be loaded. --}}
    <style>
        :root { color-scheme: light dark; --bg: #fafafa; --fg: #18181b; --muted: #71717a; --card: #ffffff; --border: #e4e4e7; }
        @media (prefers-color-scheme: dark) { :root { --bg: #18181b; --fg: #fafafa; --muted: #a1a1aa; --card: #27272a; --border: #3f3f46; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; background: var(--bg); color: var(--fg); font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { max-width: 360px; width: 100%; text-align: center; background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 32px 24px; }
        img { width: 56px; height: 56px; }
        h1 { font-size: 1.25rem; margin: 16px 0 8px; }
        p { color: var(--muted); line-height: 1.5; margin: 0 0 24px; }
        button { font: inherit; font-weight: 600; padding: 10px 20px; border-radius: 10px; border: 0; background: var(--fg); color: var(--bg); cursor: pointer; }
    </style>
</head>
<body>
    <main>
        <img src="/icons/icon-192.png" alt="">
        <h1>{{ __('You\'re offline') }}</h1>
        <p>{{ __('PropertyFlow needs a connection to show your building\'s latest information. Check your Wi-Fi or mobile data and try again.') }}</p>
        <button type="button" onclick="window.location.reload()">{{ __('Try again') }}</button>
    </main>
</body>
</html>
