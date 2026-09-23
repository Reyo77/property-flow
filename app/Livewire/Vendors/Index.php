<?php

namespace App\Livewire\Vendors;

use App\Actions\Invitations\InviteVendor;
use App\Concerns\VendorValidationRules;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Vendor;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Vendors')]
class Index extends Component
{
    use InteractsWithCurrentUser, VendorValidationRules;

    public string $search = '';

    #[Locked]
    public ?int $editingVendorId = null;

    public string $name = '';

    public string $trade = '';

    public string $phone = '';

    public string $email = '';

    public string $notes = '';

    #[Locked]
    public ?string $issuedLink = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Vendor::class);
    }

    /**
     * @return Collection<int, Vendor>
     */
    #[Computed]
    public function vendors(): Collection
    {
        return Vendor::query()
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';

                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('trade', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->orderBy('name')
            ->get();
    }

    public function create(): void
    {
        $this->authorize('create', Vendor::class);

        $this->resetForm();

        Flux::modal('vendor-form')->show();
    }

    public function edit(int $vendorId): void
    {
        $vendor = $this->findVendor($vendorId);

        $this->authorize('update', $vendor);

        $this->resetValidation();
        $this->editingVendorId = $vendor->id;
        $this->name = $vendor->name;
        $this->trade = (string) $vendor->trade;
        $this->phone = (string) $vendor->phone;
        $this->email = (string) $vendor->email;
        $this->notes = (string) $vendor->notes;

        Flux::modal('vendor-form')->show();
    }

    public function save(): void
    {
        $vendor = $this->editingVendorId === null ? null : $this->findVendor($this->editingVendorId);

        $vendor === null
            ? $this->authorize('create', Vendor::class)
            : $this->authorize('update', $vendor);

        $validated = $this->validate($this->vendorRules());
        $validated = array_map(fn (mixed $value) => $value === '' ? null : $value, $validated);

        $vendor === null
            ? Vendor::create($validated)
            : $vendor->update($validated);

        Flux::modal('vendor-form')->close();
        Flux::toast(variant: 'success', text: $vendor === null ? __('Vendor added.') : __('Vendor updated.'));

        $this->resetForm();
        unset($this->vendors);
    }

    public function delete(int $vendorId): void
    {
        $vendor = $this->findVendor($vendorId);

        $this->authorize('delete', $vendor);

        $vendor->delete();

        Flux::toast(variant: 'success', text: __('Vendor deleted.'));

        unset($this->vendors);
    }

    public function invite(int $vendorId, InviteVendor $inviteVendor): void
    {
        $vendor = $this->findVendor($vendorId);

        $this->authorize('invite', $vendor);

        $this->issuedLink = $inviteVendor->handle($this->currentUser(), $vendor)->url;

        Flux::modal('invitation-link')->show();
    }

    public function render(): View
    {
        return view('livewire.vendors.index');
    }

    private function findVendor(int $vendorId): Vendor
    {
        return Vendor::query()->findOrFail($vendorId);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingVendorId', 'name', 'trade', 'phone', 'email', 'notes');
    }
}
