<x-pdf.layout :community="$community" :title="__('Receipt')">
    <x-slot:subtitle>
        {{ $payment->displayNumber() }}<br>
        <span class="muted">{{ $payment->received_on->toFormattedDateString() }}</span>
    </x-slot:subtitle>

    @if ($payment->isReversed())
        <div class="box"><strong>{{ __('This payment was reversed: :reason.', ['reason' => $payment->reversal_reason?->label()]) }}</strong></div>
    @endif

    <table class="lines">
        <tbody>
            <tr><td>{{ __('Received from') }}</td><td>{{ __('Unit :unit', ['unit' => $payment->unit->label()]) }}</td></tr>
            <tr><td>{{ __('Method') }}</td><td>{{ $payment->method->label() }}{{ $payment->reference ? ' · '.$payment->reference : '' }}</td></tr>
            <tr><td>{{ __('Amount') }}</td><td><strong>{{ App\Support\Finance\Money::of($payment->amount_cents)->format() }}</strong></td></tr>
            @if ($payment->memo)
                <tr><td>{{ __('Memo') }}</td><td>{{ $payment->memo }}</td></tr>
            @endif
        </tbody>
    </table>

    <h2>{{ __('Applied to') }}</h2>
    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('Invoice') }}</th>
                <th>{{ __('Due') }}</th>
                <th class="right">{{ __('Amount applied') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payment->allocations as $allocation)
                <tr>
                    <td>{{ $allocation->invoice->displayNumber() }}</td>
                    <td>{{ $allocation->invoice->due_on->toFormattedDateString() }}</td>
                    <td class="right">{{ App\Support\Finance\Money::of($allocation->amount_cents)->format() }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">{{ __('Not yet applied to an invoice.') }}</td></tr>
            @endforelse
            @if (! $payment->isReversed() && $payment->unallocatedCents() > 0)
                <tr>
                    <td colspan="2">{{ __('Held as credit on account') }}</td>
                    <td class="right">{{ App\Support\Finance\Money::of($payment->unallocatedCents())->format() }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</x-pdf.layout>
