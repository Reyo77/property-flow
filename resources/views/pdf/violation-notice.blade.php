@php($owners = $violation->unit->residencies->filter(fn ($r) => $r->type === App\Enums\ResidencyType::Owner && $r->isActive())->map(fn ($r) => $r->resident->name))
<x-pdf.layout :community="$community" :title="$notice->stage->label()">
    <x-slot:subtitle>
        {{ $notice->issued_on->toFormattedDateString() }}<br>
        <span class="muted">{{ __('Violation #:id', ['id' => $violation->id]) }}</span>
    </x-slot:subtitle>

    <p>{{ __('To the owner(s) of unit :unit', ['unit' => $violation->unit->label()]) }}: <strong>{{ $owners->implode(', ') }}</strong></p>

    <p>
        @switch($notice->stage)
            @case(App\Enums\ViolationStage::Courtesy)
                {{ __('This is a friendly reminder that the following was observed at or from your unit, contrary to the community\'s rules.') }}
                @break
            @case(App\Enums\ViolationStage::Warning)
                {{ __('Our earlier notice about the matter below has not been resolved. This is a formal warning.') }}
                @break
            @default
                {{ __('The matter below remains unresolved after previous notices. In accordance with the rules, a fine has been charged to your unit\'s account.') }}
        @endswitch
    </p>

    <table class="lines">
        <tbody>
            <tr><td>{{ __('Rule') }}</td><td><strong>{{ $violation->rule->title }}</strong>{{ $violation->rule->reference ? ' ('.$violation->rule->reference.')' : '' }}</td></tr>
            <tr><td>{{ __('Observed') }}</td><td>{{ App\Support\Governance\LocalTime::display($violation->observed_at, $community) }}{{ $violation->location ? ' · '.$violation->location : '' }}</td></tr>
            <tr><td>{{ __('Details') }}</td><td>{{ $violation->description }}</td></tr>
            @if ($notice->invoice)
                <tr><td>{{ __('Fine') }}</td><td><strong>{{ App\Support\Finance\Money::of($notice->invoice->total_cents)->format() }}</strong> · {{ $notice->invoice->displayNumber() }}</td></tr>
            @endif
        </tbody>
    </table>

    <div class="box">
        {{ __('Please correct this by :date.', ['date' => $notice->cure_by?->toFormattedDateString()]) }}
        @if ($violation->rule->fine_cents && $notice->stage !== App\Enums\ViolationStage::Courtesy)
            {{ __('If it is not corrected, further fines of :amount may be charged.', ['amount' => App\Support\Finance\Money::of($violation->rule->fine_cents)->format()]) }}
        @endif
        {{ __('If you believe this notice was sent in error, please contact management.') }}
    </div>

    <p class="muted" style="margin-top: 24px">{{ __('On behalf of the board of directors, :community', ['community' => $community->name]) }}</p>
</x-pdf.layout>
