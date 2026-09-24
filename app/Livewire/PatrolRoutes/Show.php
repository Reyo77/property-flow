<?php

namespace App\Livewire\PatrolRoutes;

use App\Concerns\PatrolValidationRules;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\PatrolCheckpoint;
use App\Models\PatrolRoute;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Patrol route')]
class Show extends Component
{
    use InteractsWithCurrentUser, PatrolValidationRules;

    public Community $community;

    public PatrolRoute $patrolRoute;

    public string $name = '';

    public string $reportDate = '';

    public function mount(): void
    {
        $this->authorize('view', $this->patrolRoute);

        $this->reportDate = now($this->community->timezone)->toDateString();
    }

    /**
     * @return Collection<int, PatrolCheckpoint>
     */
    #[Computed]
    public function checkpoints(): Collection
    {
        return $this->patrolRoute->checkpoints;
    }

    /**
     * @return list<array{checkpoint: PatrolCheckpoint, last_scan_at: Carbon|null}>
     */
    #[Computed]
    public function scanSummary(): array
    {
        return $this->patrolRoute->scanSummaryFor(CarbonImmutable::parse($this->reportDate, $this->community->timezone));
    }

    public function addCheckpoint(): void
    {
        $this->authorize('update', $this->patrolRoute);

        $validated = $this->validate($this->patrolCheckpointRules());
        $nextPosition = ((int) $this->patrolRoute->checkpoints()->max('position')) + 1;

        $this->patrolRoute->checkpoints()->create([...$validated, 'position' => $nextPosition]);

        $this->reset('name');
        Flux::toast(variant: 'success', text: __('Checkpoint added.'));

        unset($this->checkpoints, $this->scanSummary);
    }

    public function deleteCheckpoint(int $checkpointId): void
    {
        $this->authorize('update', $this->patrolRoute);

        $this->patrolRoute->checkpoints()->findOrFail($checkpointId)->delete();

        unset($this->checkpoints, $this->scanSummary);
    }

    public function render(): View
    {
        return view('livewire.patrol-routes.show');
    }
}
