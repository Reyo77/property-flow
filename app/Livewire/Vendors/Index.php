<?php

namespace App\Livewire\Vendors;

use App\Actions\Invitations\InviteVendor;
use App\Concerns\VendorValidationRules;
use App\Enums\Permission;
use App\Enums\VendorBillStatus;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Support\Finance\Money;
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

    /**
     * Top vendors by paid or approved bill amount, for whoever can see finances. Null for
     * everyone else, when there's nothing to show, or when the company's communities don't
     * share one currency (summing across currencies would be wrong).
     *
     * @return array<int, array{label: string, value: string, percent: float, color: string}>|null
     */
    #[Computed]
    public function spendByVendor(): ?array
    {
        if (! $this->currentUser()->hasCompanyPermission(Permission::ViewFinance)) {
            return null;
        }

        $currencies = Community::query()->pluck('currency')->unique();

        if ($currencies->count() !== 1) {
            return null;
        }

        $totals = VendorBill::query()
            ->whereIn('status', [VendorBillStatus::Approved, VendorBillStatus::Paid])
            ->selectRaw('vendor_id, sum(amount_cents) as total')
            ->groupBy('vendor_id')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'vendor_id');

        if ($totals->isEmpty()) {
            return null;
        }

        $vendors = Vendor::query()->whereIn('id', $totals->keys())->get()->keyBy('id');
        $currency = $currencies->first();
        $max = (int) $totals->max();

        return $vendors
            ->sortByDesc(fn (Vendor $vendor) => (int) $totals[$vendor->id])
            ->map(fn (Vendor $vendor) => [
                'label' => $vendor->name,
                'value' => Money::of((int) $totals[$vendor->id], $currency)->format(),
                'percent' => round((int) $totals[$vendor->id] / $max * 100, 1),
                'color' => 'emerald-500',
            ])->values()->all();
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
