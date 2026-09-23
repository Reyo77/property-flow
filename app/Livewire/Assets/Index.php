<?php

namespace App\Livewire\Assets;

use App\Concerns\AssetValidationRules;
use App\Enums\AssetCategory;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Asset;
use App\Models\Community;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Assets')]
class Index extends Component
{
    use AssetValidationRules, InteractsWithCurrentUser;

    public Community $community;

    #[Locked]
    public ?int $editingAssetId = null;

    public string $name = '';

    public string $category = '';

    public string $location = '';

    public string $notes = '';

    public string $install_date = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [Asset::class, $this->community]);
    }

    /**
     * @return Collection<int, Asset>
     */
    #[Computed]
    public function assets(): Collection
    {
        return $this->community->assets()->withCount('maintenanceSchedules')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('create', [Asset::class, $this->community]);

        $this->resetForm();

        Flux::modal('asset-form')->show();
    }

    public function edit(int $assetId): void
    {
        $asset = $this->findAsset($assetId);

        $this->authorize('update', $asset);

        $this->resetValidation();
        $this->editingAssetId = $asset->id;
        $this->name = $asset->name;
        $this->category = $asset->category->value;
        $this->location = (string) $asset->location;
        $this->notes = (string) $asset->notes;
        $this->install_date = $asset->install_date?->toDateString() ?? '';

        Flux::modal('asset-form')->show();
    }

    public function save(): void
    {
        $asset = $this->editingAssetId === null ? null : $this->findAsset($this->editingAssetId);

        $asset === null
            ? $this->authorize('create', [Asset::class, $this->community])
            : $this->authorize('update', $asset);

        $validated = $this->validate($this->assetRules());
        $validated = array_map(fn (mixed $value) => $value === '' ? null : $value, $validated);

        $asset === null
            ? $this->community->assets()->create($validated)
            : $asset->update($validated);

        Flux::modal('asset-form')->close();
        Flux::toast(variant: 'success', text: $asset === null ? __('Asset added.') : __('Asset updated.'));

        $this->resetForm();
        unset($this->assets);
    }

    public function delete(int $assetId): void
    {
        $asset = $this->findAsset($assetId);

        $this->authorize('delete', $asset);

        $asset->delete();

        Flux::toast(variant: 'success', text: __('Asset deleted.'));
        unset($this->assets);
    }

    /**
     * @return list<AssetCategory>
     */
    public function categories(): array
    {
        return AssetCategory::cases();
    }

    public function render(): View
    {
        return view('livewire.assets.index');
    }

    private function findAsset(int $assetId): Asset
    {
        return $this->community->assets()->findOrFail($assetId);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingAssetId', 'name', 'category', 'location', 'notes', 'install_date');
    }
}
