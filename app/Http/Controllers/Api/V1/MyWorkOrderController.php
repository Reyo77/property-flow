<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WorkOrderResource;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Api\ApiQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Maintenance
 */
class MyWorkOrderController extends Controller
{
    /**
     * List my jobs (vendors)
     *
     * The open work orders assigned to your vendor company, across communities. Update them with
     * `PATCH /communities/{community}/work-orders/{workOrder}`. Empty for anyone who isn't a vendor.
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\WorkOrderResource
     *
     * @apiResourceModel App\Models\WorkOrder paginate=25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('vendor');

        return WorkOrderResource::collection(ApiQuery::paginate(
            $request,
            WorkOrder::query()
                ->where('assigned_vendor_id', $user->vendor->id ?? 0)
                ->whereNotIn('status', [WorkOrderStatus::Completed, WorkOrderStatus::Cancelled])
                ->with(['assignedUser', 'assignedVendor']),
            sorts: ['due_on', 'created_at'],
            defaultSort: 'due_on',
        ));
    }
}
