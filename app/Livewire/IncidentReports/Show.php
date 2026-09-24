<?php

namespace App\Livewire\IncidentReports;

use App\Concerns\IncidentReportValidationRules;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\IncidentReport;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Incident report')]
class Show extends Component
{
    use IncidentReportValidationRules, InteractsWithCurrentUser;

    public Community $community;

    public IncidentReport $incidentReport;

    public string $resolution_notes = '';

    public function mount(): void
    {
        $this->authorize('view', $this->incidentReport);
    }

    public function resolve(): void
    {
        $this->authorize('update', $this->incidentReport);

        $validated = $this->validate($this->incidentReportResolutionRules());

        $this->incidentReport->forceFill([
            'resolved_at' => now(),
            'resolution_notes' => $validated['resolution_notes'],
        ])->save();

        Flux::toast(variant: 'success', text: __('Incident marked resolved.'));
    }

    public function render(): View
    {
        return view('livewire.incident-reports.show');
    }
}
