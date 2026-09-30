@props([
    'bars' => [], // list<{label: string, value: string, percent: float, color?: string}>
])

@php
    $fillClass = fn (string $color): string => match ($color) {
        'emerald-200' => 'bg-emerald-200',
        'emerald-500' => 'bg-emerald-500',
        'amber-500' => 'bg-amber-500',
        'red-500' => 'bg-red-500',
        'red-700' => 'bg-red-700',
        'blue-500' => 'bg-blue-500',
        'zinc-400' => 'bg-zinc-400',
        default => 'bg-zinc-400',
    };
@endphp

<div {{ $attributes->class('space-y-3') }}>
    @foreach ($bars as $bar)
        <div>
            <div class="flex justify-between text-sm">
                <span>{{ $bar['label'] }}</span>
                <span>{{ $bar['value'] }}</span>
            </div>
            <div class="h-2 rounded bg-zinc-200 dark:bg-zinc-700">
                <div class="h-2 rounded {{ $fillClass($bar['color'] ?? 'zinc-400') }}" style="width: {{ $bar['percent'] }}%"></div>
            </div>
        </div>
    @endforeach
</div>
