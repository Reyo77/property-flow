<?php

namespace App\Livewire;

use App\Enums\CompanyRole;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Guide')]
class Guide extends Component
{
    use InteractsWithCurrentUser;

    /**
     * A short label for the kind of account the signed-in person is using, so the guide can
     * point out which sections apply to them.
     */
    #[Computed]
    public function accountLabel(): string
    {
        $user = $this->currentUser();

        if ($user->isSuperAdmin()) {
            return __('Platform admin');
        }

        if ($user->vendor !== null) {
            return __('Vendor');
        }

        if ($user->resident !== null) {
            return __('Resident');
        }

        $roleName = $user->companyRoleName();

        return $roleName === null ? __('Team member') : CompanyRole::labelFor($roleName);
    }

    public function render(): View
    {
        return view('livewire.guide');
    }
}
