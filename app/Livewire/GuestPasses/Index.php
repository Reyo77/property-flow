<?php

namespace App\Livewire\GuestPasses;

use App\Actions\FrontDesk\IssueGuestPass;
use App\Concerns\VisitorValidationRules;
use App\Enums\Permission;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\GuestPass;
use App\Models\Unit;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Guest passes')]
class Index extends Component
{
    use InteractsWithCurrentUser, VisitorValidationRules;

    public Community $community;

    public string $unit_id = '';

    public string $guest_name = '';

    public string $valid_from = '';

    public string $valid_until = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [GuestPass::class, $this->community]);

        $this->valid_from = now()->toDateString();
        $this->valid_until = now()->toDateString();

        $myUnits = $this->myUnits();

        if ($myUnits->count() === 1) {
            $this->unit_id = (string) $myUnits->first()?->id;
        }
    }

    #[Computed]
    public function canManage(): bool
    {
        $user = $this->currentUser();

        return $user->canAccessCommunity($this->community) && $user->hasCompanyPermission(Permission::ManageVisitors);
    }

    /**
     * @return Collection<int, GuestPass>
     */
    #[Computed]
    public function guestPasses(): Collection
    {
        $query = $this->community->guestPasses()->with(['unit.building', 'resident'])->latest('valid_until');

        $resident = $this->currentUser()->resident;

        if ($resident !== null && ! $this->canManage()) {
            $query->where('resident_id', $resident->id);
        }

        return $query->limit(100)->get();
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function myUnits(): Collection
    {
        $resident = $this->currentUser()->resident;

        if ($resident === null) {
            return new Collection;
        }

        $unitIds = $resident->residencies()->active()->where('community_id', $this->community->id)->pluck('unit_id');

        return Unit::query()->whereIn('id', $unitIds)->with('building')->get();
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function communityUnits(): Collection
    {
        return $this->canManage()
            ? $this->community->units()->with('building')->orderBy('number')->get()
            : new Collection;
    }

    public function create(): void
    {
        $this->authorize('create', [GuestPass::class, $this->community]);

        Flux::modal('guest-pass-form')->show();
    }

    public function save(IssueGuestPass $issueGuestPass): void
    {
        $this->authorize('create', [GuestPass::class, $this->community]);

        $validated = $this->validate($this->guestPassRules($this->community));

        $issueGuestPass->handle($this->community, $this->currentUser(), [
            'unit_id' => (int) $validated['unit_id'],
            'guest_name' => $validated['guest_name'],
            'valid_from' => $validated['valid_from'],
            'valid_until' => $validated['valid_until'],
        ]);

        Flux::modal('guest-pass-form')->close();
        Flux::toast(variant: 'success', text: __('Guest pass created.'));

        $this->reset('unit_id', 'guest_name');
        unset($this->guestPasses);
    }

    public function render(): View
    {
        return view('livewire.guest-passes.index');
    }
}
