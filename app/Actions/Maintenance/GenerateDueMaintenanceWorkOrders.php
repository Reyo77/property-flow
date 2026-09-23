<?php

namespace App\Actions\Maintenance;

use App\Models\MaintenanceSchedule;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Creates a work order for every maintenance schedule that has come due, then advances its
 * due date by its interval so it fires again next time around.
 */
class GenerateDueMaintenanceWorkOrders
{
    /**
     * @param  Collection<int, MaintenanceSchedule>|null  $schedules  Defaults to every due schedule,
     *                                                                across every company; pass a scoped subset (e.g. one asset's) to limit the run.
     */
    public function handle(?Collection $schedules = null): int
    {
        $schedules ??= MaintenanceSchedule::query()
            ->withoutGlobalScopes()
            ->dueToGenerate()
            ->with('asset.community')
            ->get();

        foreach ($schedules as $schedule) {
            $this->generateFor($schedule);
        }

        return $schedules->count();
    }

    private function generateFor(MaintenanceSchedule $schedule): void
    {
        DB::transaction(function () use ($schedule): WorkOrder {
            $asset = $schedule->asset;

            $workOrder = $asset->community->workOrders()->make([
                'title' => $schedule->title,
                'assignee_type' => $schedule->assignee_type,
                'assigned_user_id' => $schedule->assigned_user_id,
                'assigned_vendor_id' => $schedule->assigned_vendor_id,
            ]);
            $workOrder->forceFill([
                'company_id' => $asset->company_id,
                'asset_id' => $asset->id,
                'maintenance_schedule_id' => $schedule->id,
            ])->save();

            $schedule->forceFill([
                'next_due_on' => $schedule->next_due_on->copy()->addDays($schedule->interval_days),
                'last_generated_on' => now()->toDateString(),
            ])->save();

            return $workOrder;
        });
    }
}
