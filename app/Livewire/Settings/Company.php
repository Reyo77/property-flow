<?php

namespace App\Livewire\Settings;

use App\Actions\Companies\RequestDataExport;
use App\Actions\Companies\UpdateCompanyBranding;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Company as CompanyModel;
use App\Models\DataExportRequest;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy\PermissionTeam;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * @property-read Collection<int, DataExportRequest> $exportRequests
 * @property-read array<int, array{label: string, used: int, max: int|null}> $planUsage
 */
#[Title('Company settings')]
class Company extends Component
{
    use InteractsWithCurrentUser, WithFileUploads;

    public string $name = '';

    public string $brand_color = '';

    public ?TemporaryUploadedFile $logo = null;

    public function mount(): void
    {
        $this->authorize('manageSettings', $this->company());

        $this->name = $this->company()->name;
        $this->brand_color = (string) $this->company()->brand_color;
    }

    public function saveBranding(UpdateCompanyBranding $updateCompanyBranding): void
    {
        $this->authorize('manageSettings', $this->company());

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $updateCompanyBranding->handle(
            $this->company(),
            $validated['name'],
            $validated['brand_color'] === '' ? null : $validated['brand_color'],
            $this->logo,
        );

        $this->reset('logo');

        Flux::toast(variant: 'success', text: __('Company settings updated.'));
    }

    public function removeLogo(): void
    {
        $this->authorize('manageSettings', $this->company());

        $company = $this->company();

        if ($company->logo_disk_path !== null) {
            Storage::disk('local')->delete($company->logo_disk_path);
            $company->update(['logo_disk_path' => null]);
        }

        Flux::toast(variant: 'success', text: __('Logo removed.'));
    }

    public function requestExport(RequestDataExport $requestDataExport): void
    {
        $this->authorize('manageSettings', $this->company());

        $requestDataExport->handle($this->company(), $this->currentUser());

        unset($this->exportRequests);

        Flux::toast(variant: 'success', text: __('Export requested. We\'ll notify you when it\'s ready.'));
    }

    /**
     * @return Collection<int, DataExportRequest>
     */
    #[Computed]
    public function exportRequests(): Collection
    {
        return $this->company()->dataExportRequests()->latest('requested_at')->limit(10)->get();
    }

    /**
     * @return array<int, array{label: string, used: int, max: int|null}>
     */
    #[Computed]
    public function planUsage(): array
    {
        $company = $this->company();
        $plan = $company->plan;

        $teamMembers = PermissionTeam::run(
            $company->id,
            fn () => User::query()->where('company_id', $company->id)->whereHas('roles')->count(),
        );

        return [
            ['label' => __('Communities'), 'used' => $company->communities()->count(), 'max' => $plan?->max_communities],
            ['label' => __('Units'), 'used' => Unit::query()->where('company_id', $company->id)->count(), 'max' => $plan?->max_units],
            ['label' => __('Team members'), 'used' => $teamMembers, 'max' => $plan?->max_team_members],
        ];
    }

    public function render(): View
    {
        return view('livewire.settings.company');
    }

    public function company(): CompanyModel
    {
        return $this->currentUser()->company ?? throw new InvalidArgumentException('Only company members have company settings.');
    }
}
