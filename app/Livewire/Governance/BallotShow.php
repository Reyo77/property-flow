<?php

namespace App\Livewire\Governance;

use App\Actions\Governance\CastVote;
use App\Actions\Governance\CloseBallot;
use App\Actions\Governance\GrantProxy;
use App\Actions\Governance\PublishBallot;
use App\Actions\Governance\RevokeProxy;
use App\Enums\BallotStatus;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Ballot;
use App\Models\BallotProxy;
use App\Models\BallotQuestion;
use App\Models\BallotVote;
use App\Models\Community;
use App\Models\Unit;
use App\Models\User;
use App\Support\Governance\BallotParticipation;
use App\Support\Governance\VotingRoll;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use LogicException;

#[Title('Ballot')]
class BallotShow extends Component
{
    use InteractsWithCurrentUser;

    public Community $community;

    public Ballot $ballot;

    /**
     * Answers being filled in, as [unit id => [question id => option id]].
     *
     * @var array<int, array<int, string>>
     */
    public array $choices = [];

    /**
     * Proxy holder being chosen, per unit.
     *
     * @var array<int, string>
     */
    public array $proxyHolder = [];

    public function mount(): void
    {
        $this->authorize('view', $this->ballot);
    }

    #[Computed]
    public function status(): BallotStatus
    {
        return $this->ballot->status();
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->currentUser()->can('manage', $this->ballot);
    }

    /**
     * @return Collection<int, BallotQuestion>
     */
    #[Computed]
    public function questions(): Collection
    {
        return $this->ballot->questions()->with('options')->get();
    }

    /**
     * The units the signed-in person can vote for: their own, and those they hold a proxy for.
     *
     * @return list<array{unit: Unit, via_proxy: BallotProxy|null, vote: BallotVote|null, proxy_out: BallotProxy|null}>
     */
    #[Computed]
    public function myUnits(): array
    {
        return app(BallotParticipation::class)->units($this->ballot, $this->currentUser());
    }

    /**
     * People an owner may appoint as proxy: other residents of the community with a login, and
     * the team members who work in it.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function proxyCandidates(): Collection
    {
        return app(BallotParticipation::class)->proxyCandidates($this->community, $this->currentUser());
    }

    /**
     * @return array{eligible: int, voted: int}
     */
    #[Computed]
    public function turnout(): array
    {
        return [
            'eligible' => app(VotingRoll::class)->eligibleUnits($this->community)->count(),
            'voted' => BallotVote::query()->where('ballot_id', $this->ballot->id)->count(),
        ];
    }

    public function vote(int $unitId, CastVote $castVote): void
    {
        $unit = $this->community->units()->findOrFail($unitId);
        $choices = array_map('intval', array_filter($this->choices[$unitId] ?? [], fn ($value) => $value !== ''));

        try {
            $castVote->handle($this->ballot, $unit, $this->currentUser(), $choices);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError(str_starts_with($field, 'choices.') ? "choices.{$unitId}.".substr($field, 8) : "vote.{$unitId}", $messages[0]);
            }

            return;
        } catch (AuthorizationException $exception) {
            $this->addError("vote.{$unitId}", $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Your vote for unit :unit has been cast.', ['unit' => $unit->label()]));
        unset($this->myUnits, $this->turnout);
    }

    public function appointProxy(int $unitId, GrantProxy $grantProxy): void
    {
        $unit = $this->community->units()->findOrFail($unitId);
        $holder = $this->proxyCandidates()->firstWhere('id', (int) ($this->proxyHolder[$unitId] ?? 0));

        if ($holder === null) {
            $this->addError("proxy.{$unitId}", __('Choose who will vote for you.'));

            return;
        }

        try {
            $grantProxy->handle($this->ballot, $unit, $this->currentUser(), $holder);
        } catch (ValidationException|AuthorizationException $exception) {
            $this->addError("proxy.{$unitId}", $exception instanceof ValidationException ? $exception->validator->errors()->first() : $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __(':name will vote for unit :unit.', ['name' => $holder->name, 'unit' => $unit->label()]));
        unset($this->myUnits);
    }

    public function revokeProxy(int $proxyId, RevokeProxy $revokeProxy): void
    {
        $proxy = BallotProxy::query()->where('ballot_id', $this->ballot->id)->where('granted_by_id', $this->currentUser()->id)->findOrFail($proxyId);

        try {
            $revokeProxy->handle($proxy, $this->currentUser());
        } catch (ValidationException $exception) {
            $this->addError("proxy.{$proxy->unit_id}", $exception->validator->errors()->first());

            return;
        }

        unset($this->myUnits);
    }

    public function publish(PublishBallot $publishBallot): void
    {
        $this->authorize('manage', $this->ballot);

        try {
            $publishBallot->handle($this->ballot, $this->currentUser());
        } catch (LogicException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->ballot->refresh();
        Flux::toast(variant: 'success', text: __('Ballot published to owners.'));
        unset($this->status);
    }

    public function close(CloseBallot $closeBallot): void
    {
        $this->authorize('manage', $this->ballot);

        try {
            $this->ballot = $closeBallot->handle($this->ballot, $this->currentUser());
        } catch (LogicException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Voting closed and results locked.'));
        unset($this->status);
    }

    public function render(): View
    {
        return view('livewire.governance.ballot-show');
    }
}
