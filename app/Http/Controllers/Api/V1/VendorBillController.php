<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\VendorBillStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VendorBillResource;
use App\Models\Community;
use App\Models\VendorBill;
use App\Support\Api\ApiQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Finance
 */
class VendorBillController extends Controller
{
    /**
     * List vendor bills
     *
     * @queryParam filter[status] `pending`, `approved`, `rejected` or `paid`. Example: pending
     * @queryParam sort `billed_on`, `due_on` or `amount_cents`. Example: due_on
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\VendorBillResource
     *
     * @apiResourceModel App\Models\VendorBill paginate=25 with=vendor,account
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [VendorBill::class, $community]);

        return VendorBillResource::collection(ApiQuery::paginate(
            $request,
            $community->vendorBills()->with(['vendor', 'account'])->getQuery(),
            filters: ['status' => ApiQuery::enum(VendorBillStatus::class, 'status', 'status')],
            sorts: ['billed_on', 'due_on', 'amount_cents'],
            defaultSort: 'due_on',
        ));
    }

    /**
     * Show a vendor bill
     *
     * @apiResource App\Http\Resources\Api\V1\VendorBillResource
     *
     * @apiResourceModel App\Models\VendorBill with=vendor,account
     */
    public function show(Community $community, VendorBill $vendorBill): VendorBillResource
    {
        Gate::authorize('viewAny', [VendorBill::class, $community]);

        return new VendorBillResource($vendorBill->load(['vendor', 'account']));
    }
}
