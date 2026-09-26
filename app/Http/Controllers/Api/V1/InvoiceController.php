<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Community;
use App\Models\Invoice;
use App\Support\Api\ApiQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @group Finance
 */
class InvoiceController extends Controller
{
    /**
     * List invoices
     *
     * Finance team only; residents see theirs in the unit account.
     *
     * @queryParam filter[unit_id] integer Example: 1
     * @queryParam filter[status] `open`, `partially_paid`, `paid` or `voided`. Example: open
     * @queryParam sort `issued_on` or `due_on`. Example: -issued_on
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\InvoiceResource
     *
     * @apiResourceModel App\Models\Invoice paginate=25 with=unit.building
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Invoice::class, $community]);

        return InvoiceResource::collection(ApiQuery::paginate(
            $request,
            $community->invoices()->withPaid()->with('unit.building')->getQuery(),
            filters: [
                'unit_id' => fn (Builder $query, string $value) => $query->where('unit_id', (int) $value),
                'status' => fn (Builder $query, string $value) => match (InvoiceStatus::tryFrom($value)) {
                    InvoiceStatus::Voided => $query->whereNotNull('voided_at'),
                    InvoiceStatus::Paid => $query->whereNull('voided_at')->havingRaw('COALESCE(paid_cents, 0) >= total_cents'),
                    InvoiceStatus::PartiallyPaid => $query->whereNull('voided_at')->havingRaw('COALESCE(paid_cents, 0) > 0 AND COALESCE(paid_cents, 0) < total_cents'),
                    InvoiceStatus::Open => $query->whereNull('voided_at')->havingRaw('COALESCE(paid_cents, 0) = 0'),
                    null => throw ValidationException::withMessages(['filter.status' => __('Use open, partially_paid, paid or voided.')]),
                },
            ],
            sorts: ['issued_on', 'due_on'],
            defaultSort: '-issued_on',
        ));
    }

    /**
     * Show an invoice
     *
     * Residents may view their own units' invoices.
     *
     * @apiResource App\Http\Resources\Api\V1\InvoiceResource
     *
     * @apiResourceModel App\Models\Invoice with=unit.building,lines
     */
    public function show(Community $community, Invoice $invoice): InvoiceResource
    {
        Gate::authorize('view', $invoice);

        return new InvoiceResource($invoice->load(['unit.building', 'lines']));
    }
}
