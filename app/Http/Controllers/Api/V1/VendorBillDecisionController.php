<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Finance\DecideVendorBill;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VendorBillResource;
use App\Models\Community;
use App\Models\User;
use App\Models\VendorBill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * @group Finance
 */
class VendorBillDecisionController extends Controller
{
    /**
     * Approve or reject a vendor bill
     *
     * Bills over the community's approval limit need the board. A reason is required to reject.
     *
     * @bodyParam decision string required `approved` or `rejected`. Example: approved
     * @bodyParam notes string Required when rejecting. Example: Quote was for less.
     *
     * @apiResource App\Http\Resources\Api\V1\VendorBillResource
     *
     * @apiResourceModel App\Models\VendorBill with=vendor,account
     */
    public function store(Request $request, Community $community, VendorBill $vendorBill, DecideVendorBill $decide): VendorBillResource
    {
        Gate::authorize('approve', $vendorBill);

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'notes' => ['nullable', 'required_if:decision,rejected', 'string', 'max:1000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $validated['decision'] === 'approved'
                ? $decide->approve($vendorBill, $user, $validated['notes'] ?? null)
                : $decide->reject($vendorBill, $user, (string) $validated['notes']);
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['decision' => $exception->getMessage()]);
        }

        return new VendorBillResource($vendorBill->refresh()->load(['vendor', 'account']));
    }
}
