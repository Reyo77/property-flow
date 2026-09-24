<?php

namespace App\Livewire\IncidentReports;

use App\Actions\FrontDesk\CreateIncidentReport;
use App\Concerns\IncidentReportValidationRules;
use App\Enums\IncidentSeverity;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\IncidentReport;
use App\Models\Unit;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Incident reports')]
class Index extends Component
{
    use IncidentReportValidationRules, InteractsWithCurrentUser, WithFileUploads;

    public Community $community;

    public string $unit_id = '';

    public string $title = '';

    public string $description = '';

    public string $location = '';

    public string $severity = '';

    public string $occurred_at = '';

    /** @var list<TemporaryUploadedFile> */
    public array $photos = [];

    public function mount(): void
    {
        $this->authorize('viewAny', [IncidentReport::class, $this->community]);
    }

    /**
     * @return Collection<int, IncidentReport>
     */
    #[Computed]
    public function incidentReports(): Collection
    {
        return $this->community->incidentReports()->with('unit.building')->latest('occurred_at')->limit(100)->get();
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function units(): Collection
    {
        return $this->community->units()->with('building')->orderBy('number')->get();
    }

    public function create(): void
    {
        $this->authorize('create', [IncidentReport::class, $this->community]);

        $this->resetValidation();
        $this->reset('unit_id', 'title', 'description', 'location', 'severity', 'photos');
        $this->occurred_at = now()->format('Y-m-d\TH:i');

        Flux::modal('incident-form')->show();
    }

    public function save(CreateIncidentReport $createIncidentReport): void
    {
        $this->authorize('create', [IncidentReport::class, $this->community]);

        $validated = $this->validate($this->incidentReportRules($this->community));

        $createIncidentReport->handle(
            $this->community,
            $this->currentUser(),
            [
                'unit_id' => $validated['unit_id'] === '' || $validated['unit_id'] === null ? null : (int) $validated['unit_id'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'location' => $validated['location'] ?: null,
                'severity' => $validated['severity'],
                'occurred_at' => $validated['occurred_at'],
            ],
            $this->photos,
        );

        Flux::modal('incident-form')->close();
        Flux::toast(variant: 'success', text: __('Incident reported.'));

        unset($this->incidentReports);
    }

    /**
     * @return list<IncidentSeverity>
     */
    public function severities(): array
    {
        return IncidentSeverity::cases();
    }

    public function render(): View
    {
        return view('livewire.incident-reports.index');
    }
}
