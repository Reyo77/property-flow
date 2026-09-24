<?php

namespace App\Livewire\PatrolRoutes;

use App\Concerns\PatrolValidationRules;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\PatrolRoute;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Patrols')]
class Index extends Component
{
    use InteractsWithCurrentUser, PatrolValidationRules;

    public Community $community;

    public string $name = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [PatrolRoute::class, $this->community]);
    }

    /**
     * @return Collection<int, PatrolRoute>
     */
    #[Computed]
    public function routes(): Collection
    {
        return $this->community->patrolRoutes()->withCount('checkpoints')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('create', [PatrolRoute::class, $this->community]);

        $this->reset('name');
        $this->resetValidation();

        Flux::modal('patrol-route-form')->show();
    }

    public function save(): void
    {
        $this->authorize('create', [PatrolRoute::class, $this->community]);

        $validated = $this->validate($this->patrolRouteRules());

        $this->community->patrolRoutes()->create($validated);

        Flux::modal('patrol-route-form')->close();
        Flux::toast(variant: 'success', text: __('Route created.'));

        unset($this->routes);
    }

    public function render(): View
    {
        return view('livewire.patrol-routes.index');
    }
}
