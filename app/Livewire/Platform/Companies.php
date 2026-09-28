<?php

namespace App\Livewire\Platform;

use App\Actions\Platform\SetCompanySuspended;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Company;
use App\Support\Tenancy\PermissionTeam;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Companies')]
class Companies extends Component
{
    use InteractsWithCurrentUser;

    public function mount(): void
    {
        abort_unless($this->currentUser()->isSuperAdmin(), 403);
    }

    /**
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        return Company::query()
            ->withCount(['communities', 'units', 'residents'])
            ->with('plan')
            ->latest()
            ->get();
    }

    public function teamMemberCount(Company $company): int
    {
        return PermissionTeam::run($company->id, fn () => $company->users()->whereHas('roles')->count());
    }

    public function suspend(int $companyId, SetCompanySuspended $setCompanySuspended): void
    {
        abort_unless($this->currentUser()->isSuperAdmin(), 403);

        $setCompanySuspended->handle($this->currentUser(), $this->findCompany($companyId), true);

        Flux::toast(variant: 'success', text: __('Company suspended.'));
        unset($this->companies);
    }

    public function reactivate(int $companyId, SetCompanySuspended $setCompanySuspended): void
    {
        abort_unless($this->currentUser()->isSuperAdmin(), 403);

        $setCompanySuspended->handle($this->currentUser(), $this->findCompany($companyId), false);

        Flux::toast(variant: 'success', text: __('Company reactivated.'));
        unset($this->companies);
    }

    public function render(): View
    {
        return view('livewire.platform.companies');
    }

    private function findCompany(int $companyId): Company
    {
        return Company::query()->findOrFail($companyId);
    }
}
