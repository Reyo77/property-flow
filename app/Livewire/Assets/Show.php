<?php

namespace App\Livewire\Assets;

use App\Actions\Maintenance\GenerateDueMaintenanceWorkOrders;
use App\Concerns\MaintenanceScheduleValidationRules;
use App\Enums\Assignee;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Asset;
use App\Models\Community;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WorkOrder;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Asset')]
class Show extends Component
{
    use InteractsWithCurrentUser, MaintenanceScheduleValidationRules;

    public Community $community;

    public Asset $asset;

    #[Locked]
    public ?int $editingScheduleId = null;

    public string $title = '';

    public string $interval_days = '30';

    public string $next_due_on = '';

    public string $assignee_type = '';

    public string $assigned_user_id = '';

    public string $assigned_vendor_id = '';

    public function mount(): void
    {
        $this->authorize('view', $this->asset);
    }

    /**
     * @return Collection<int, MaintenanceSchedule>
     */
    #[Computed]
    public function schedules(): Collection
    {
        return $this->asset->maintenanceSchedules()->with(['assignedUser', 'assignedVendor'])->orderBy('next_due_on')->get();
    }

    /**
     * @return Collection<int, WorkOrder>
     */
    #[Computed]
    public function recentWorkOrders(): Collection
    {
        return $this->asset->workOrders()->latest()->limit(10)->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function staffOptions(): Collection
    {
        return $this->community->users()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Vendor>
     */
    #[Computed]
    public function vendorOptions(): Collection
    {
        return Vendor::query()->orderBy('name')->get();
    }

    public function createSchedule(): void
    {
        $this->authorize('update', $this->asset);

        $this->resetForm();

        Flux::modal('schedule-form')->show();
    }

    public function editSchedule(int $scheduleId): void
    {
        $schedule = $this->findSchedule($scheduleId);

        $this->authorize('update', $this->asset);

        $this->resetValidation();
        $this->editingScheduleId = $schedule->id;
        $this->title = $schedule->title;
        $this->interval_days = (string) $schedule->interval_days;
        $this->next_due_on = $schedule->next_due_on->toDateString();
        $this->assignee_type = (string) $schedule->assignee_type?->value;
        $this->assigned_user_id = (string) $schedule->assigned_user_id;
        $this->assigned_vendor_id = (string) $schedule->assigned_vendor_id;

        Flux::modal('schedule-form')->show();
    }

    public function saveSchedule(): void
    {
        $this->authorize('update', $this->asset);

        $schedule = $this->editingScheduleId === null ? null : $this->findSchedule($this->editingScheduleId);

        $validated = $this->validate($this->maintenanceScheduleRules($this->community, $this->assignee_type));
        $validated['assignee_type'] = $this->assignee_type === '' ? null : $this->assignee_type;
        $validated['assigned_user_id'] = $validated['assigned_user_id'] ?? null;
        $validated['assigned_vendor_id'] = $validated['assigned_vendor_id'] ?? null;

        $schedule === null
            ? $this->asset->maintenanceSchedules()->create($validated)
            : $schedule->update($validated);

        Flux::modal('schedule-form')->close();
        Flux::toast(variant: 'success', text: $schedule === null ? __('Schedule added.') : __('Schedule updated.'));

        $this->resetForm();
        unset($this->schedules);
    }

    public function toggleActive(int $scheduleId): void
    {
        $schedule = $this->findSchedule($scheduleId);

        $this->authorize('update', $this->asset);

        $schedule->update(['active' => ! $schedule->active]);

        unset($this->schedules);
    }

    public function deleteSchedule(int $scheduleId): void
    {
        $schedule = $this->findSchedule($scheduleId);

        $this->authorize('update', $this->asset);

        $schedule->delete();

        Flux::toast(variant: 'success', text: __('Schedule deleted.'));
        unset($this->schedules);
    }

    /**
     * Runs the generator immediately for this asset's own due schedules, useful when a manager
     * wants a work order right now instead of waiting for the daily scheduler.
     */
    public function generateNow(GenerateDueMaintenanceWorkOrders $generateDueMaintenanceWorkOrders): void
    {
        $this->authorize('update', $this->asset);

        $due = $this->asset->maintenanceSchedules()->dueToGenerate()->get();
        $created = $generateDueMaintenanceWorkOrders->handle($due);

        Flux::toast(variant: 'success', text: __(':count work order(s) generated.', ['count' => $created]));
        unset($this->schedules, $this->recentWorkOrders);
    }

    /**
     * @return list<Assignee>
     */
    public function assigneeTypes(): array
    {
        return Assignee::cases();
    }

    public function render(): View
    {
        return view('livewire.assets.show');
    }

    private function findSchedule(int $scheduleId): MaintenanceSchedule
    {
        return $this->asset->maintenanceSchedules()->findOrFail($scheduleId);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingScheduleId', 'title', 'assignee_type', 'assigned_user_id', 'assigned_vendor_id');
        $this->interval_days = '30';
        $this->next_due_on = now()->addDays(30)->toDateString();
    }
}
