<?php

namespace App\Livewire\Finance;

use App\Actions\Finance\RunBilling;
use App\Concerns\FinanceValidationRules;
use App\Enums\RecurringChargeMethod;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\ChargeType;
use App\Models\Community;
use App\Models\LateFeeRule;
use App\Models\RecurringCharge;
use App\Models\Unit;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Recurring charges, billing settings (due day, bill approval limit), the late fee rule, and a
 * button to run this month's billing now instead of waiting for the nightly run.
 */
#[Title('Billing')]
class Billing extends Component
{
    use FinanceValidationRules, InteractsWithCurrentUser;

    public Community $community;

    public ?int $editingChargeId = null;

    public string $charge_type_id = '';

    public string $description = '';

    public string $applies_to_unit_id = '';

    public string $method = '';

    public string $amount = '';

    public string $starts_on = '';

    public string $ends_on = '';

    public int $billing_due_day = 1;

    public string $bill_approval_limit = '';

    public bool $late_fees_enabled = false;

    public int $grace_days = 10;

    public string $fee_kind = 'flat';

    public string $flat_fee = '';

    public string $percent_fee = '';

    public string $minimum_balance = '';

    public string $run_month = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [RecurringCharge::class, $this->community]);

        $this->fillSettings();
        $this->run_month = CarbonImmutable::now($this->community->timezone)->format('Y-m');
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->currentUser()->can('create', [RecurringCharge::class, $this->community]);
    }

    /**
     * @return Collection<int, RecurringCharge>
     */
    #[Computed]
    public function charges(): Collection
    {
        return $this->community->recurringCharges()->with(['chargeType', 'unit.building'])->orderByDesc('is_active')->orderBy('description')->get();
    }

    /**
     * @return Collection<int, ChargeType>
     */
    #[Computed]
    public function chargeTypes(): Collection
    {
        return $this->community->chargeTypes()->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function units(): Collection
    {
        return $this->community->units()->with('building')->orderBy('number')->get();
    }

    /**
     * What one month of the active community-wide charges adds up to, as a sanity check.
     */
    #[Computed]
    public function monthlyTotal(): Money
    {
        $unitCount = $this->community->units()->count();

        return Money::of($this->charges()->where('is_active', true)->sum(fn (RecurringCharge $charge) => match (true) {
            $charge->unit_id !== null, $charge->method === RecurringChargeMethod::UnitFactor => $charge->amount_cents,
            default => $charge->amount_cents * $unitCount,
        }), $this->community->currency);
    }

    public function createCharge(): void
    {
        $this->authorize('create', [RecurringCharge::class, $this->community]);

        $this->resetValidation();
        $this->reset('editingChargeId', 'charge_type_id', 'description', 'applies_to_unit_id', 'amount', 'ends_on');
        $this->method = RecurringChargeMethod::Fixed->value;
        $this->starts_on = CarbonImmutable::now($this->community->timezone)->startOfMonth()->toDateString();

        Flux::modal('recurring-charge-form')->show();
    }

    public function editCharge(int $chargeId): void
    {
        $charge = $this->community->recurringCharges()->findOrFail($chargeId);

        $this->authorize('update', $charge);

        $this->resetValidation();
        $this->editingChargeId = $charge->id;
        $this->charge_type_id = (string) $charge->charge_type_id;
        $this->description = $charge->description;
        $this->applies_to_unit_id = $charge->unit_id === null ? '' : (string) $charge->unit_id;
        $this->method = $charge->method->value;
        $this->amount = Money::of($charge->amount_cents)->toDecimal();
        $this->starts_on = $charge->starts_on->toDateString();
        $this->ends_on = $charge->ends_on?->toDateString() ?? '';

        Flux::modal('recurring-charge-form')->show();
    }

    public function updatedChargeTypeId(): void
    {
        $chargeType = $this->chargeTypes()->firstWhere('id', (int) $this->charge_type_id);

        if ($chargeType !== null && $this->description === '') {
            $this->description = $chargeType->name;
        }
    }

    public function saveCharge(): void
    {
        $charge = $this->editingChargeId === null ? null : $this->community->recurringCharges()->findOrFail($this->editingChargeId);

        $charge === null
            ? $this->authorize('create', [RecurringCharge::class, $this->community])
            : $this->authorize('update', $charge);

        $validated = $this->validate($this->recurringChargeRules($this->community));
        $unitId = $validated['applies_to_unit_id'] ? (int) $validated['applies_to_unit_id'] : null;

        $attributes = [
            'charge_type_id' => (int) $validated['charge_type_id'],
            'description' => $validated['description'],
            'unit_id' => $unitId,
            // A single-unit charge is simply that amount; splitting by factor only makes sense community-wide.
            'method' => $unitId === null ? RecurringChargeMethod::from($validated['method']) : RecurringChargeMethod::Fixed,
            'amount_cents' => Money::parse($validated['amount'])->cents,
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['ends_on'] ?: null,
        ];

        if ($charge === null) {
            $charge = new RecurringCharge([...$attributes, 'is_active' => true]);
            $charge->forceFill(['company_id' => $this->community->company_id, 'community_id' => $this->community->id])->save();
        } else {
            $charge->update($attributes);
        }

        Flux::modal('recurring-charge-form')->close();
        Flux::toast(variant: 'success', text: __('Recurring charge saved.'));

        unset($this->charges, $this->monthlyTotal);
    }

    public function toggleCharge(int $chargeId): void
    {
        $charge = $this->community->recurringCharges()->findOrFail($chargeId);

        $this->authorize('update', $charge);

        $charge->update(['is_active' => ! $charge->is_active]);

        unset($this->charges, $this->monthlyTotal);
    }

    public function saveSettings(): void
    {
        $this->authorize('create', [RecurringCharge::class, $this->community]);

        $validated = $this->validate($this->billingSettingsRules());

        $this->community->update([
            'billing_due_day' => $validated['billing_due_day'],
            'bill_approval_limit_cents' => Money::parse($validated['bill_approval_limit'])->cents,
        ]);

        $rule = $this->community->lateFeeRule;

        if ($validated['late_fees_enabled'] ?? false) {
            $attributes = [
                'grace_days' => $validated['grace_days'],
                'flat_cents' => $validated['fee_kind'] === 'flat' ? Money::parse($validated['flat_fee'])->cents : null,
                'percent_basis_points' => $validated['fee_kind'] === 'percent' ? $this->basisPoints((string) $validated['percent_fee']) : null,
                'minimum_balance_cents' => ($validated['minimum_balance'] ?? '') !== '' ? Money::parse($validated['minimum_balance'])->cents : 0,
                'is_active' => true,
            ];

            if ($rule === null) {
                $rule = new LateFeeRule($attributes);
                $rule->forceFill(['company_id' => $this->community->company_id, 'community_id' => $this->community->id])->save();
            } else {
                $rule->update($attributes);
            }
        } elseif ($rule !== null) {
            $rule->update(['is_active' => false]);
        }

        $this->community->unsetRelation('lateFeeRule');
        Flux::toast(variant: 'success', text: __('Billing settings saved.'));
    }

    public function runBilling(RunBilling $runBilling): void
    {
        $this->authorize('create', [RecurringCharge::class, $this->community]);

        $this->validate(['run_month' => ['required', 'date_format:Y-m']]);

        $month = CarbonImmutable::createFromFormat('Y-m-d', "{$this->run_month}-01", $this->community->timezone);

        if (! $month instanceof CarbonImmutable) {
            return;
        }

        $result = $runBilling->handle($this->community, $month->startOfDay(), $this->currentUser());

        Flux::toast(variant: 'success', text: trans_choice(
            '{0} Nothing new to bill for :month (:skipped unit(s) already billed).|{1} Issued 1 invoice for :month, :total.|[2,*] Issued :count invoices for :month, :total.',
            $result->issued,
            ['month' => $month->translatedFormat('F Y'), 'skipped' => $result->alreadyBilled, 'total' => Money::of($result->totalCents)->format()],
        ));

        if ($result->unitsWithoutFactor > 0) {
            Flux::toast(variant: 'warning', text: __(':count unit(s) have no unit factor and were left out of factor-based charges.', ['count' => $result->unitsWithoutFactor]));
        }
    }

    public function render(): View
    {
        return view('livewire.finance.billing');
    }

    /**
     * "1.5" (percent) → 150 basis points, without going through a float.
     */
    private function basisPoints(string $percent): int
    {
        return is_numeric($percent) ? (int) bcmul($percent, '100', 0) : 0;
    }

    private function fillSettings(): void
    {
        $this->billing_due_day = $this->community->billing_due_day;
        $this->bill_approval_limit = Money::of($this->community->bill_approval_limit_cents)->toDecimal();

        $rule = $this->community->lateFeeRule;

        if ($rule === null) {
            return;
        }

        $this->late_fees_enabled = $rule->is_active;
        $this->grace_days = $rule->grace_days;
        $this->fee_kind = $rule->flat_cents !== null ? 'flat' : 'percent';
        $this->flat_fee = $rule->flat_cents !== null ? Money::of($rule->flat_cents)->toDecimal() : '';
        $this->percent_fee = $rule->percent_basis_points !== null ? rtrim(rtrim(number_format($rule->percent_basis_points / 100, 2, '.', ''), '0'), '.') : '';
        $this->minimum_balance = $rule->minimum_balance_cents > 0 ? Money::of($rule->minimum_balance_cents)->toDecimal() : '';
    }
}
