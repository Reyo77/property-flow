@props([
    'segments' => [], // list<{label: string, value: int|float, percent: float, color?: string}>
    'size' => 120,
    'strokeWidth' => 16,
])

@php
    $strokeClass = fn (string $color): string => match ($color) {
        'emerald-500' => 'stroke-emerald-500',
        'red-500' => 'stroke-red-500',
        'zinc-400' => 'stroke-zinc-400',
        'blue-500' => 'stroke-blue-500',
        default => 'stroke-zinc-400',
    };
    $swatchClass = fn (string $color): string => match ($color) {
        'emerald-500' => 'bg-emerald-500',
        'red-500' => 'bg-red-500',
        'zinc-400' => 'bg-zinc-400',
        'blue-500' => 'bg-blue-500',
        default => 'bg-zinc-400',
    };

    $arcs = App\Support\Charts\ChartMath::donutSegments(array_column($segments, 'value'), size: $size, strokeWidth: $strokeWidth);
    $radius = ($size - $strokeWidth) / 2;
@endphp

<div {{ $attributes->class('flex flex-wrap items-center gap-4') }}>
    <svg viewBox="0 0 {{ $size }} {{ $size }}" width="{{ $size }}" height="{{ $size }}" class="-rotate-90 shrink-0">
        @if ($arcs === [])
            <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}" fill="none" stroke-width="{{ $strokeWidth }}" class="stroke-zinc-200 dark:stroke-zinc-700" />
        @else
            @foreach ($arcs as $i => $arc)
                <circle
                    cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}"
                    fill="none" stroke-width="{{ $strokeWidth }}"
                    stroke-dasharray="{{ $arc['dasharray'] }}" stroke-dashoffset="{{ $arc['dashoffset'] }}"
                    class="{{ $strokeClass($segments[$i]['color'] ?? 'zinc-400') }}"
                ><title>{{ $segments[$i]['label'] }}: {{ $arc['percent'] }}%</title></circle>
            @endforeach
        @endif
    </svg>

    @if ($arcs === [])
        <flux:text size="sm">{{ __('No data yet') }}</flux:text>
    @else
        <div class="space-y-1 text-sm">
            @foreach ($segments as $segment)
                <div class="flex items-center gap-1.5">
                    <span class="size-2 rounded-full {{ $swatchClass($segment['color'] ?? 'zinc-400') }}"></span>
                    <span>{{ $segment['label'] }} · {{ $segment['percent'] }}%</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
