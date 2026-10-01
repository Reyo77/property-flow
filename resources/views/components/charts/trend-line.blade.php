@props([
    'series' => [], // list<{label: string, color: string, values: list<int|float>, display?: list<string>}>
    'labels' => [], // list<string>, x-axis labels, same length as each series' values
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

    $datasets = collect($series)->map(fn (array $s) => [
        'label' => $s['label'],
        'data' => array_values($s['values']),
        'borderColor' => $hex($s['color']),
        'backgroundColor' => $hex($s['color']).'22',
        'fill' => count($series) === 1,
        'tension' => 0.3,
        'pointRadius' => 3,
        'pointHoverRadius' => 5,
    ])->all();

    $chartConfig = [
        'type' => 'line',
        'data' => ['labels' => $labels, 'datasets' => $datasets],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => [
                'legend' => ['display' => count($series) > 1, 'position' => 'bottom'],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ],
    ];
@endphp

<div {{ $attributes->class('h-40 w-full') }} x-data="{ init() { new Chart($refs.canvas, {{ \Illuminate\Support\Js::from($chartConfig) }}); } }" wire:ignore>
    <canvas x-ref="canvas" role="img" aria-label="{{ __('Trend chart') }}"></canvas>
</div>
