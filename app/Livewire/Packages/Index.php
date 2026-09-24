<?php

namespace App\Livewire\Packages;

use App\Actions\FrontDesk\LogPackage;
use App\Actions\FrontDesk\ReleasePackage;
use App\Concerns\PackageValidationRules;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Package;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use LogicException;

#[Title('Packages')]
class Index extends Component
{
    use InteractsWithCurrentUser, PackageValidationRules;

    public Community $community;

    #[Url(as: 'status', except: '')]
    public string $statusFilter = 'awaiting_pickup';

    public string $unit_id = '';

    public string $resident_id = '';

    public string $carrier = '';

    public string $tracking_number = '';

    public string $shelf_location = '';

    public ?int $releasingPackageId = null;

    public string $released_to_name = '';

    public string $signature = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [Package::class, $this->community]);
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->currentUser()->can('create', [Package::class, $this->community]);
    }

    /**
     * @return Collection<int, Package>
     */
    #[Computed]
    public function packages(): Collection
    {
        $query = $this->community->packages()->with(['unit.building', 'resident'])->latest('created_at');
        $resident = $this->currentUser()->resident;

        if ($resident !== null && ! $this->canManage()) {
            $query->where('resident_id', $resident->id);
        } elseif ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function units(): Collection
    {
        return $this->community->units()->with('building')->orderBy('number')->get();
    }

    /**
     * @return Collection<int, Resident>
     */
    #[Computed]
    public function residentsForUnit(): Collection
    {
        if ($this->unit_id === '') {
            return new Collection;
        }

        $residentIds = Residency::where('unit_id', (int) $this->unit_id)->active()->pluck('resident_id');

        return Resident::query()->whereIn('id', $residentIds)->get();
    }

    public function create(): void
    {
        $this->authorize('create', [Package::class, $this->community]);

        $this->resetForm();

        Flux::modal('package-form')->show();
    }

    public function save(LogPackage $logPackage): void
    {
        $this->authorize('create', [Package::class, $this->community]);

        $validated = $this->validate($this->packageRules($this->community));
        $validated = array_map(fn (mixed $value) => $value === '' ? null : $value, $validated);

        $logPackage->handle($this->community, $this->currentUser(), [
            'unit_id' => $validated['unit_id'] === null ? null : (int) $validated['unit_id'],
            'resident_id' => $validated['resident_id'] === null ? null : (int) $validated['resident_id'],
            'carrier' => $validated['carrier'],
            'tracking_number' => $validated['tracking_number'],
            'shelf_location' => $validated['shelf_location'],
        ]);

        Flux::modal('package-form')->close();
        Flux::toast(variant: 'success', text: __('Package logged.'));

        $this->resetForm();
        unset($this->packages);
    }

    public function openRelease(int $packageId): void
    {
        $package = $this->findPackage($packageId);

        $this->authorize('release', $package);

        $this->resetValidation();
        $this->releasingPackageId = $package->id;
        $this->released_to_name = '';
        $this->signature = '';

        Flux::modal('release-form')->show();
    }

    public function release(ReleasePackage $releasePackage): void
    {
        if ($this->releasingPackageId === null) {
            return;
        }

        $package = $this->findPackage($this->releasingPackageId);

        $this->authorize('release', $package);

        $validated = $this->validate($this->packageReleaseRules());

        try {
            $releasePackage->handle($package, $this->currentUser(), $validated['released_to_name'], $this->signature ?: null);
        } catch (LogicException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::modal('release-form')->close();
        Flux::toast(variant: 'success', text: __('Package released.'));

        $this->releasingPackageId = null;
        unset($this->packages);
    }

    public function render(): View
    {
        return view('livewire.packages.index');
    }

    private function findPackage(int $packageId): Package
    {
        return $this->community->packages()->findOrFail($packageId);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('unit_id', 'resident_id', 'carrier', 'tracking_number', 'shelf_location');
    }
}
