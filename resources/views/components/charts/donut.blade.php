@props([
    'segments' => [], // list<{label: string, value: int|float, percent: float, color?: string}>
    'size' => 160,
])

@php
    $hex = fn (string $color): string => match ($color) {
        'emerald-500' => '#10b981',
        'red-500' => '#ef4444',
        'amber-500' => '#f59e0b',
        'violet-500' => '#8b5cf6',
        'blue-500' => '#3b82f6',
        'zinc-400' => '#a1a1aa',
        default => '#a1a1aa',
    };

    $total = array_sum(array_column($segments, 'value'));

    $chartConfig = [
        'type' => 'doughnut',
        'data' => [
            'labels' => array_column($segments, 'label'),
            'datasets' => [[
                'data' => array_column($segments, 'value'),
                'backgroundColor' => array_map(fn (array $s) => $hex($s['color'] ?? 'zinc-400'), $segments),
                'borderWidth' => 2,
                'borderColor' => '#ffffff',
            ]],
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'cutout' => '65%',
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'right'],
            ],
        ],
    ];
@endphp

<div {{ $attributes }}>
    @if ($total <= 0)
        <flux:text size="sm">{{ __('No data yet') }}</flux:text>
    @else
        <div style="height: {{ $size }}px; max-width: 360px;" x-data="{ init() { new Chart($refs.canvas, {{ \Illuminate\Support\Js::from($chartConfig) }}); } }" wire:ignore>
            <canvas x-ref="canvas" role="img" aria-label="{{ __('Breakdown chart') }}"></canvas>
        </div>
    @endif
</div>
