<?php

namespace App\Livewire\FrontDesk;

use App\Enums\Permission;
use App\Events\FrontDeskActivity;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A large, fast-entry front-desk screen: quick links into each module plus a live activity
 * feed fed by {@see FrontDeskActivity} over the community's private channel. The
 * feed only shows activity that happens while this screen is open (nothing is persisted here
 * specifically for it); each module's own index page remains the source of truth for history.
 */
#[Title('Front desk')]
class Mode extends Component
{
    use InteractsWithCurrentUser;

    public Community $community;

    /** @var list<array{type: string, message: string, occurred_at: string}> */
    public array $activity = [];

    /**
     * A staff-only screen: unlike each module's own index page, this never admits a resident,
     * so it checks community access and a front-desk permission directly rather than delegating
     * to a policy whose viewAny() also admits residents for their own records.
     */
    public function mount(): void
    {
        $user = $this->currentUser();

        abort_unless($user->canAccessCommunity($this->community) && $user->hasCompanyPermission(Permission::ViewPackages), 403);
    }

    /**
     * @param  array{type: string, message: string, occurred_at: string}  $event
     */
    #[On('echo-private:community.{community.id},front-desk-activity')]
    public function recordActivity(array $event): void
    {
        array_unshift($this->activity, $event);
        $this->activity = array_slice($this->activity, 0, 50);
    }

    public function render(): View
    {
        return view('livewire.front-desk.mode');
    }
}
