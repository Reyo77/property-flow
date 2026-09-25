@props(['model' => 'signature', 'label' => __('Signature')])

{{-- A finger/mouse signature canvas that writes a PNG data URL into the given Livewire property. --}}
<div x-data="{
        drawing: false,
        start(e) { this.drawing = true; this.draw(e); },
        stop() {
            if (! this.drawing) return;
            this.drawing = false;
            $refs.pad.getContext('2d').beginPath();
            $wire.set('{{ $model }}', $refs.pad.toDataURL('image/png'));
        },
        draw(e) {
            if (! this.drawing) return;
            const canvas = $refs.pad;
            const rect = canvas.getBoundingClientRect();
            const ctx = canvas.getContext('2d');
            const point = e.touches ? e.touches[0] : e;
            const x = (point.clientX - rect.left) * (canvas.width / rect.width);
            const y = (point.clientY - rect.top) * (canvas.height / rect.height);
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#18181b';
            ctx.lineTo(x, y);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(x, y);
        },
        clear() {
            $refs.pad.getContext('2d').clearRect(0, 0, $refs.pad.width, $refs.pad.height);
            $wire.set('{{ $model }}', '');
        },
    }"
    {{ $attributes }}
>
    <flux:text class="mb-1 text-sm">{{ $label }}</flux:text>
    <canvas
        x-ref="pad" width="440" height="150"
        class="w-full touch-none rounded-lg border border-zinc-300 bg-white dark:border-zinc-600"
        @mousedown="start" @mousemove="draw" @mouseup="stop" @mouseleave="stop"
        @touchstart.prevent="start" @touchmove.prevent="draw" @touchend.prevent="stop"
    ></canvas>
    <flux:button type="button" size="sm" variant="ghost" class="mt-2" x-on:click="clear">{{ __('Clear') }}</flux:button>
</div>
