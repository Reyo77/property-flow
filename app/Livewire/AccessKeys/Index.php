<?php

namespace App\Livewire\AccessKeys;

use App\Concerns\AccessKeyValidationRules;
use App\Events\FrontDeskActivity;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\AccessKey;
use App\Models\Community;
use App\Models\Unit;
use App\Support\LocalTime;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Keys')]
class Index extends Component
{
    use AccessKeyValidationRules, InteractsWithCurrentUser;

    public Community $community;

    public string $unit_id = '';

    public string $label = '';

    public string $notes = '';

    #[Locked]
    public ?int $signingOutKeyId = null;

    public string $signed_out_to = '';

    public string $signed_out_to_phone = '';

    public string $due_back_at = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [AccessKey::class, $this->community]);
    }

    /**
     * @return Collection<int, AccessKey>
     */
    #[Computed]
    public function keys(): Collection
    {
        return $this->community->accessKeys()->with(['unit.building', 'signouts' => fn ($query) => $query->whereNull('returned_at')])->orderBy('label')->get();
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
        $this->authorize('create', [AccessKey::class, $this->community]);

        $this->resetValidation();
        $this->reset('unit_id', 'label', 'notes');

        Flux::modal('key-form')->show();
    }

    public function save(): void
    {
        $this->authorize('create', [AccessKey::class, $this->community]);

        $validated = $this->validate($this->accessKeyRules($this->community));
        $validated = array_map(fn (mixed $value) => $value === '' ? null : $value, $validated);

        $this->community->accessKeys()->create($validated);

        Flux::modal('key-form')->close();
        Flux::toast(variant: 'success', text: __('Key added.'));

        unset($this->keys);
    }

    public function openSignOut(int $keyId): void
    {
        $key = $this->findKey($keyId);

        $this->authorize('update', $key);

        $this->resetValidation();
        $this->signingOutKeyId = $key->id;
        $this->reset('signed_out_to', 'signed_out_to_phone', 'due_back_at');

        Flux::modal('sign-out-form')->show();
    }

    public function signOut(): void
    {
        if ($this->signingOutKeyId === null) {
            return;
        }

        $key = $this->findKey($this->signingOutKeyId);

        $this->authorize('update', $key);

        $validated = $this->validate($this->accessKeySignoutRules());
        $validated = array_map(fn (mixed $value) => $value === '' ? null : $value, $validated);

        if (is_string($validated['due_back_at'] ?? null)) {
            $validated['due_back_at'] = LocalTime::toUtc($validated['due_back_at'], $this->community)->toDateTimeString();
        }

        $signout = $key->signouts()->make($validated);
        $signout->forceFill(['signed_out_by_id' => $this->currentUser()->id])->save();

        FrontDeskActivity::dispatch($this->community->id, 'key', __(':label signed out to :name.', ['label' => $key->label, 'name' => $signout->signed_out_to]));

        Flux::modal('sign-out-form')->close();
        Flux::toast(variant: 'success', text: __('Key signed out.'));

        $this->signingOutKeyId = null;
        unset($this->keys);
    }

    public function returnKey(int $keyId): void
    {
        $key = $this->findKey($keyId);

        $this->authorize('update', $key);

        $signout = $key->currentSignout();

        if ($signout === null) {
            return;
        }

        $signout->forceFill(['returned_at' => now(), 'returned_to_id' => $this->currentUser()->id])->save();

        FrontDeskActivity::dispatch($this->community->id, 'key', __(':label returned.', ['label' => $key->label]));

        Flux::toast(variant: 'success', text: __('Key returned.'));
        unset($this->keys);
    }

    public function render(): View
    {
        return view('livewire.access-keys.index');
    }

    private function findKey(int $keyId): AccessKey
    {
        return $this->community->accessKeys()->findOrFail($keyId);
    }
}
