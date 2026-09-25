<?php

namespace App\Livewire\Violations;

use App\Actions\Violations\CloseViolation;
use App\Actions\Violations\EscalateViolation;
use App\Enums\ViolationStatus;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Violation;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use LogicException;

#[Title('Violation')]
class Show extends Component
{
    use InteractsWithCurrentUser;

    public Community $community;

    public Violation $violation;

    public string $resolution_notes = '';

    public function mount(): void
    {
        $this->authorize('view', $this->violation);
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->currentUser()->can('manage', $this->violation);
    }

    public function escalate(EscalateViolation $escalateViolation): void
    {
        $this->authorize('manage', $this->violation);

        $notice = $escalateViolation->handle($this->violation, CarbonImmutable::now($this->community->timezone)->startOfDay(), $this->currentUser(), force: true);

        $this->violation->refresh();
        Flux::toast(
            variant: $notice === null ? 'warning' : 'success',
            text: $notice === null ? __('There is no further automatic step; decide what happens next.') : __(':stage issued.', ['stage' => $notice->stage->label()]),
        );
    }

    public function close(string $outcome, CloseViolation $closeViolation): void
    {
        $this->authorize('manage', $this->violation);

        $status = match ($outcome) {
            'resolved' => ViolationStatus::Resolved,
            'dismissed' => ViolationStatus::Dismissed,
            default => abort(422),
        };

        try {
            $closeViolation->handle($this->violation, $status, $this->resolution_notes ?: null, $this->currentUser());
        } catch (LogicException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->violation->refresh();
        Flux::modal('close-violation')->close();
        Flux::toast(variant: 'success', text: __('Violation closed.'));
    }

    public function render(): View
    {
        return view('livewire.violations.show', [
            'notices' => $this->violation->notices()->with('invoice')->get(),
            'photos' => $this->violation->attachments()->get(),
        ]);
    }
}
