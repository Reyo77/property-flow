<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Finance\RecordPayment;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Models\Community;
use App\Models\Payment;
use App\Models\Unit;
use App\Models\User;
use App\Support\Api\ApiQuery;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

/**
 * @group Finance
 */
class PaymentController extends Controller
{
    /**
     * List payments
     *
     * Finance team only; residents see theirs in the unit account.
     *
     * @queryParam filter[unit_id] integer Example: 1
     * @queryParam filter[method] `cash`, `cheque`, `bank_transfer`, `online` or `other`. Example: cheque
     * @queryParam sort `received_on`. Example: -received_on
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\PaymentResource
     *
     * @apiResourceModel App\Models\Payment paginate=25 with=unit.building
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Payment::class, $community]);

        return PaymentResource::collection(ApiQuery::paginate(
            $request,
            $community->payments()->with('unit.building')->getQuery(),
            filters: [
                'unit_id' => fn (Builder $query, string $value) => $query->where('unit_id', (int) $value),
                'method' => ApiQuery::enum(PaymentMethod::class, 'method', 'method'),
            ],
            sorts: ['received_on'],
            defaultSort: '-received_on',
        ));
    }

    /**
     * Record a payment
     *
     * Finance team only, for money received outside the app (cash, cheque, bank transfer). It is
     * applied to the unit's oldest open invoices; any excess stays as a credit.
     *
     * @bodyParam unit_id integer required Example: 1
     * @bodyParam method string required `cash`, `cheque`, `bank_transfer` or `other`. Example: cheque
     * @bodyParam amount_cents integer required Example: 45000
     * @bodyParam received_on string required YYYY-MM-DD, not in the future. Example: 2026-10-01
     * @bodyParam reference string Cheque number or transfer reference. Example: CHQ 204
     * @bodyParam memo string Example: October fees
     */
    #[ResponseFromApiResource(PaymentResource::class, Payment::class, status: 201, with: ['unit.building'])]
    public function store(Request $request, Community $community, RecordPayment $recordPayment): JsonResponse
    {
        Gate::authorize('create', [Payment::class, $community]);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', Rule::exists(Unit::class, 'id')->where('community_id', $community->id)->withoutTrashed()],
            'method' => ['required', Rule::in(array_map(fn (PaymentMethod $method) => $method->value, PaymentMethod::manual()))],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000'],
            'received_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'memo' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $payment = $recordPayment->handle(
            $community->units()->findOrFail((int) $validated['unit_id']),
            PaymentMethod::from($validated['method']),
            Money::of((int) $validated['amount_cents'], $community->currency),
            CarbonImmutable::parse($validated['received_on']),
            $validated['reference'] ?? null,
            $validated['memo'] ?? null,
            $user,
        );

        return (new PaymentResource($payment->load('unit.building')))->response()->setStatusCode(201);
    }

    /**
     * Show a payment
     *
     * Residents may view their own units' payments, and download the receipt from `receipt_url`.
     *
     * @apiResource App\Http\Resources\Api\V1\PaymentResource
     *
     * @apiResourceModel App\Models\Payment with=unit.building
     */
    public function show(Community $community, Payment $payment): PaymentResource
    {
        Gate::authorize('view', $payment);

        return new PaymentResource($payment->load('unit.building'));
    }
}
