<x-pdf.layout :community="$community" :title="__('Statement of account')">
    <x-slot:subtitle>
        {{ __('Unit :unit', ['unit' => $unit->label()]) }}<br>
        <span class="muted">
            @if ($from)
                {{ $from->toFormattedDateString() }} &ndash; {{ $asOf->toFormattedDateString() }}
            @else
                {{ __('As of :date', ['date' => $asOf->toFormattedDateString()]) }}
            @endif
        </span>
    </x-slot:subtitle>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Description') }}</th>
                <th class="right">{{ __('Charges') }}</th>
                <th class="right">{{ __('Payments & credits') }}</th>
                <th class="right">{{ __('Balance') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td></td>
                <td><strong>{{ __('Opening balance') }}</strong></td>
                <td></td>
                <td></td>
                <td class="right">{{ $opening->format() }}</td>
            </tr>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['entry']->posted_on->toFormattedDateString() }}</td>
                    <td>{{ $row['entry']->journalEntry->memo }}</td>
                    <td class="right">{{ $row['entry']->debit_cents > 0 ? App\Support\Finance\Money::of($row['entry']->debit_cents)->format() : '' }}</td>
                    <td class="right">{{ $row['entry']->credit_cents > 0 ? App\Support\Finance\Money::of($row['entry']->credit_cents)->format() : '' }}</td>
                    <td class="right">{{ $row['balance']->format() }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td></td>
                <td>{{ $closing->isNegative() ? __('Credit on account') : __('Balance owing') }}</td>
                <td></td>
                <td></td>
                <td class="right">{{ $closing->isNegative() ? $closing->negate()->format() : $closing->format() }}</td>
            </tr>
        </tbody>
    </table>

    @if ($openInvoices->isNotEmpty())
        <h2>{{ __('Open invoices') }}</h2>
        <table class="lines">
            <thead>
                <tr>
                    <th>{{ __('Number') }}</th>
                    <th>{{ __('Issued') }}</th>
                    <th>{{ __('Due') }}</th>
                    <th class="right">{{ __('Total') }}</th>
                    <th class="right">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($openInvoices as $invoice)
                    <tr>
                        <td>{{ $invoice->displayNumber() }}{{ $invoice->isOverdue() ? ' · '.__('Overdue') : '' }}</td>
                        <td>{{ $invoice->issued_on->toFormattedDateString() }}</td>
                        <td>{{ $invoice->due_on->toFormattedDateString() }}</td>
                        <td class="right">{{ App\Support\Finance\Money::of($invoice->total_cents)->format() }}</td>
                        <td class="right">{{ App\Support\Finance\Money::of($invoice->balanceCents())->format() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-pdf.layout>
