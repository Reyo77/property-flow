<?php

namespace App\Livewire\IncidentReports;

use App\Actions\FrontDesk\CreateIncidentReport;
use App\Concerns\IncidentReportValidationRules;
use App\Enums\IncidentSeverity;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\IncidentReport;
use App\Models\Unit;
use App\Support\LocalTime;
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

    /**
     * Incident counts by severity, or null when nothing has been reported yet.
     *
     * @return array<int, array{label: string, value: int, percent: float, color: string}>|null
     */
    #[Computed]
    public function severityBreakdown(): ?array
    {
        $colors = [
            IncidentSeverity::Low->value => 'zinc-400',
            IncidentSeverity::Medium->value => 'amber-500',
            IncidentSeverity::High->value => 'violet-500',
            IncidentSeverity::Critical->value => 'red-500',
        ];

        $counts = $this->community->incidentReports()
            ->selectRaw('severity, count(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $total = (int) $counts->sum();

        if ($total === 0) {
            return null;
        }

        return collect(IncidentSeverity::cases())->map(fn (IncidentSeverity $severity) => [
            'label' => $severity->label(),
            'value' => (int) ($counts[$severity->value] ?? 0),
            'percent' => round(($counts[$severity->value] ?? 0) / $total * 100, 1),
            'color' => $colors[$severity->value],
        ])->values()->all();
    }

    public function create(): void
    {
        $this->authorize('create', [IncidentReport::class, $this->community]);

        $this->resetValidation();
        $this->reset('unit_id', 'title', 'description', 'location', 'severity', 'photos');
        $this->occurred_at = LocalTime::forInput(now(), $this->community);

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
                'occurred_at' => LocalTime::toUtc($validated['occurred_at'], $this->community)->toDateTimeString(),
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
