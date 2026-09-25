<x-pdf.layout :community="$community" :title="__('Renovation request decision')">
    <x-slot:subtitle>
        {{ $architecturalRequest->decided_at?->toFormattedDateString() }}<br>
        <span class="muted">{{ __('Request #:id', ['id' => $architecturalRequest->id]) }}</span>
    </x-slot:subtitle>

    <p>{{ __('To :name, owner of unit :unit', ['name' => $architecturalRequest->submittedBy?->name, 'unit' => $architecturalRequest->unit->label()]) }}</p>

    <table class="lines">
        <tbody>
            <tr><td>{{ __('Request') }}</td><td><strong>{{ $architecturalRequest->title }}</strong></td></tr>
            <tr><td>{{ __('Submitted') }}</td><td>{{ $architecturalRequest->created_at?->toFormattedDateString() }}</td></tr>
            @if ($architecturalRequest->contractor)
                <tr><td>{{ __('Contractor') }}</td><td>{{ $architecturalRequest->contractor }}</td></tr>
            @endif
            <tr><td>{{ __('Decision') }}</td><td><strong>{{ $architecturalRequest->status->label() }}</strong></td></tr>
        </tbody>
    </table>

    @if ($architecturalRequest->conditions)
        <h2>{{ __('Conditions of approval') }}</h2>
        <div class="box" style="white-space: pre-line">{{ $architecturalRequest->conditions }}</div>
    @endif

    @if ($architecturalRequest->decision_notes)
        <h2>{{ $architecturalRequest->status === App\Enums\ArchitecturalRequestStatus::Denied ? __('Reasons') : __('Notes') }}</h2>
        <p style="white-space: pre-line">{{ $architecturalRequest->decision_notes }}</p>
    @endif

    <p class="muted" style="margin-top: 24px">
        @if ($architecturalRequest->status !== App\Enums\ArchitecturalRequestStatus::Denied)
            {{ __('Work must follow the plans as submitted and any conditions above. Keep this letter for your records.') }}
        @endif
        {{ __('On behalf of the board of directors, :community', ['community' => $community->name]) }}
    </p>
</x-pdf.layout>
