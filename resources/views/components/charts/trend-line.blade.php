@props([
    'series' => [], // list<{label: string, color: string, values: list<int|float>, display?: list<string>}>
    'labels' => [], // list<string>, x-axis labels, same length as each series' values
])

@php
    $strokeClass = fn (string $color): string => match ($color) {
        'emerald-500' => 'stroke-emerald-500',
        'red-500' => 'stroke-red-500',
        'zinc-400' => 'stroke-zinc-400',
        'blue-500' => 'stroke-blue-500',
        default => 'stroke-zinc-400',
    };
    $fillClass = fn (string $color): string => match ($color) {
        'emerald-500' => 'fill-emerald-500/10',
        'red-500' => 'fill-red-500/10',
        'zinc-400' => 'fill-zinc-400/10',
        'blue-500' => 'fill-blue-500/10',
        default => 'fill-zinc-400/10',
    };
    $dotClass = fn (string $color): string => match ($color) {
        'emerald-500' => 'fill-emerald-500',
        'red-500' => 'fill-red-500',
        'zinc-400' => 'fill-zinc-400',
        'blue-500' => 'fill-blue-500',
        default => 'fill-zinc-400',
    };
    // Legend swatches are plain <span>s, not SVG — `fill-*` has no effect there, only `bg-*` does.
    $swatchClass = fn (string $color): string => match ($color) {
        'emerald-500' => 'bg-emerald-500',
        'red-500' => 'bg-red-500',
        'zinc-400' => 'bg-zinc-400',
        'blue-500' => 'bg-blue-500',
        default => 'bg-zinc-400',
    };

    $allValues = collect($series)->flatMap(fn (array $s) => $s['values'])->all();
    $min = min(0, $allValues === [] ? 0 : min($allValues));
    $max = max(0, $allValues === [] ? 0 : max($allValues));

    $charted = collect($series)->map(fn (array $s) => [
        ...$s,
        'chart' => App\Support\Charts\ChartMath::linePath($s['values'], forceMin: $min, forceMax: $max),
    ]);
@endphp

<div {{ $attributes->class('space-y-3') }}>
    <svg viewBox="0 0 400 120" preserveAspectRatio="none" class="h-28 w-full overflow-visible">
        @foreach ($charted as $s)
            @if ($s['chart']['areaPath'] !== '' && $charted->count() === 1)
                <path d="{{ $s['chart']['areaPath'] }}" stroke="none" class="{{ $fillClass($s['color']) }}" />
            @endif
            @if ($s['chart']['path'] !== '')
                <path d="{{ $s['chart']['path'] }}" fill="none" stroke-width="2" class="{{ $strokeClass($s['color']) }}" />
            @endif
            @foreach ($s['chart']['points'] as $i => $point)
                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="2.5" class="{{ $dotClass($s['color']) }}">
                    <title>{{ $s['label'] }}: {{ $s['display'][$i] ?? $s['values'][$i] }}</title>
                </circle>
            @endforeach
        @endforeach
    </svg>

    @if ($labels !== [])
        <div class="flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
            @foreach ($labels as $label)
                <span>{{ $label }}</span>
            @endforeach
        </div>
    @endif

    @if (count($series) > 1)
        <div class="flex flex-wrap gap-4 text-sm">
            @foreach ($series as $s)
                <div class="flex items-center gap-1.5">
                    <span class="size-2 rounded-full {{ $swatchClass($s['color']) }}"></span>
                    <span>{{ $s['label'] }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
