<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Maintenance\TransitionWorkOrderStatus;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WorkOrderResource;
use App\Models\Community;
use App\Models\WorkOrder;
use App\Support\Api\ApiQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * @group Maintenance
 *
 * Work orders are the jobs that fix service requests (or routine maintenance). Vendors see their
 * own jobs at `GET /my-work-orders`.
 */
class WorkOrderController extends Controller
{
    /**
     * List work orders
     *
     * @queryParam filter[status] `pending`, `in_progress`, `completed` or `cancelled`. Example: pending
     * @queryParam sort Sort by `due_on`, `created_at`. Example: due_on
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\WorkOrderResource
     *
     * @apiResourceModel App\Models\WorkOrder paginate=25 with=assignedUser,assignedVendor
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [WorkOrder::class, $community]);

        return WorkOrderResource::collection(ApiQuery::paginate(
            $request,
            $community->workOrders()->with(['assignedUser', 'assignedVendor'])->getQuery(),
            filters: ['status' => ApiQuery::enum(WorkOrderStatus::class, 'status', 'status')],
            sorts: ['due_on', 'created_at'],
            defaultSort: '-created_at',
        ));
    }

    /**
     * Show a work order
     *
     * @apiResource App\Http\Resources\Api\V1\WorkOrderResource
     *
     * @apiResourceModel App\Models\WorkOrder with=assignedUser,assignedVendor
     */
    public function show(Community $community, WorkOrder $workOrder): WorkOrderResource
    {
        Gate::authorize('view', $workOrder);

        return new WorkOrderResource($workOrder->load(['assignedUser', 'assignedVendor']));
    }

    /**
     * Update progress
     *
     * The team, or the vendor doing the job, moves it along. Completing it can carry notes.
     *
     * @bodyParam status string required The new status. Example: completed
     * @bodyParam completion_notes string Notes when completing. Example: Replaced the cartridge.
     *
     * @apiResource App\Http\Resources\Api\V1\WorkOrderResource
     *
     * @apiResourceModel App\Models\WorkOrder with=assignedUser,assignedVendor
     */
    public function update(Request $request, Community $community, WorkOrder $workOrder, TransitionWorkOrderStatus $transition): WorkOrderResource
    {
        Gate::authorize('updateProgress', $workOrder);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(WorkOrderStatus::class)],
            'completion_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $status = WorkOrderStatus::from($validated['status']);

        try {
            $transition->handle($workOrder, $status, $status === WorkOrderStatus::Completed ? ($validated['completion_notes'] ?? null) : null);
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return new WorkOrderResource($workOrder->refresh()->load(['assignedUser', 'assignedVendor']));
    }
}
