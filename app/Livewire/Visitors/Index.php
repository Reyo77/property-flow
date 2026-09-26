<?php

namespace App\Livewire\Visitors;

use App\Actions\FrontDesk\LogVisitor;
use App\Actions\FrontDesk\RedeemGuestPass;
use App\Concerns\VisitorValidationRules;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Unit;
use App\Models\Visitor;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Visitors')]
class Index extends Component
{
    use InteractsWithCurrentUser, VisitorValidationRules;

    public Community $community;

    public string $unit_id = '';

    public string $visitor_name = '';

    public string $purpose = '';

    public string $notes = '';

    public string $redeemCode = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [Visitor::class, $this->community]);
    }

    /**
     * @return Collection<int, Visitor>
     */
    #[Computed]
    public function visitors(): Collection
    {
        return $this->community->visitors()->with('unit.building')->latest('checked_in_at')->limit(100)->get();
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
        $this->authorize('create', [Visitor::class, $this->community]);

        $this->resetValidation();
        $this->reset('unit_id', 'visitor_name', 'purpose', 'notes');

        Flux::modal('visitor-form')->show();
    }

    public function save(LogVisitor $logVisitor): void
    {
        $this->authorize('create', [Visitor::class, $this->community]);

        $logVisitor->handle($this->community, $this->currentUser(), $this->validate($this->visitorRules($this->community)));

        Flux::modal('visitor-form')->close();
        Flux::toast(variant: 'success', text: __('Visitor logged.'));

        unset($this->visitors);
    }

    public function checkOut(int $visitorId): void
    {
        $visitor = $this->community->visitors()->findOrFail($visitorId);

        $this->authorize('update', $visitor);

        $visitor->update(['checked_out_at' => now()]);

        unset($this->visitors);
    }

    public function redeem(RedeemGuestPass $redeemGuestPass): void
    {
        $this->authorize('create', [Visitor::class, $this->community]);

        $this->resetValidation();

        $guestPass = $this->community->guestPasses()->where('code', strtoupper(trim($this->redeemCode)))->first();

        if ($guestPass === null) {
            $this->addError('redeemCode', __('No pass found with that code.'));

            return;
        }

        $this->authorize('redeem', $guestPass);

        try {
            $redeemGuestPass->handle($guestPass, $this->currentUser());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError('redeemCode', $messages[0]);
            }

            return;
        }

        $this->redeemCode = '';
        Flux::toast(variant: 'success', text: __('Guest pass redeemed and visitor logged.'));

        unset($this->visitors);
    }

    public function render(): View
    {
        return view('livewire.visitors.index');
    }
}
