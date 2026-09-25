<?php

namespace App\Concerns;

use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReversalReason;
use App\Enums\RecurringChargeMethod;
use App\Models\Account;
use App\Models\ChargeType;
use App\Models\Community;
use App\Models\Unit;
use App\Models\Vendor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait FinanceValidationRules
{
    /**
     * A positive amount typed as dollars and cents ("1,234.50"); parsed later with Money::parse, never as a float.
     */
    private const string AMOUNT_PATTERN = '/^(?!0+(\.0+)?$)[\d,]{1,12}(\.\d{1,2})?$/';

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function invoiceRules(Community $community): array
    {
        return [
            'unit_id' => ['required', 'integer', $this->unitInCommunity($community)],
            'issued_on' => ['required', 'date'],
            'due_on' => ['required', 'date', 'after_or_equal:issued_on'],
            'memo' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.charge_type_id' => [
                'required', 'integer',
                Rule::exists(ChargeType::class, 'id')->where('community_id', $community->id)->where('is_active', true),
            ],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.amount' => ['required', 'string', 'regex:'.self::AMOUNT_PATTERN],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function paymentRules(Community $community): array
    {
        return [
            'unit_id' => ['required', 'integer', $this->unitInCommunity($community)],
            'method' => ['required', Rule::in(array_map(fn (PaymentMethod $method) => $method->value, PaymentMethod::manual()))],
            'amount' => ['required', 'string', 'regex:'.self::AMOUNT_PATTERN],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'memo' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function paymentReversalRules(): array
    {
        return [
            'reversal_reason' => ['required', Rule::enum(PaymentReversalReason::class)],
            'reversed_on' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function chargeTypeRules(Community $community): array
    {
        return [
            'charge_name' => ['required', 'string', 'max:100'],
            'account_id' => [
                'required', 'integer',
                Rule::exists(Account::class, 'id')->where('community_id', $community->id)->where('type', AccountType::Income->value)->where('is_active', true),
            ],
            'default_amount' => ['nullable', 'string', 'regex:'.self::AMOUNT_PATTERN],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function accountRules(Community $community): array
    {
        return [
            'code' => ['required', 'string', 'max:10', 'regex:/^\d{3,10}$/', Rule::unique(Account::class)->where('community_id', $community->id)],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(AccountType::class)],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function recurringChargeRules(Community $community): array
    {
        return [
            'charge_type_id' => [
                'required', 'integer',
                Rule::exists(ChargeType::class, 'id')->where('community_id', $community->id)->where('is_active', true),
            ],
            'description' => ['required', 'string', 'max:255'],
            'applies_to_unit_id' => ['nullable', 'integer', $this->unitInCommunity($community)],
            'method' => ['required', Rule::enum(RecurringChargeMethod::class)],
            'amount' => ['required', 'string', 'regex:'.self::AMOUNT_PATTERN],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function billingSettingsRules(): array
    {
        return [
            'billing_due_day' => ['required', 'integer', 'between:1,28'],
            'bill_approval_limit' => ['required', 'string', 'regex:'.self::AMOUNT_PATTERN],
            'late_fees_enabled' => ['boolean'],
            'grace_days' => ['exclude_unless:late_fees_enabled,true', 'required', 'integer', 'between:0,365'],
            'fee_kind' => ['exclude_unless:late_fees_enabled,true', 'required', Rule::in(['flat', 'percent'])],
            'flat_fee' => ['exclude_unless:fee_kind,flat', 'exclude_unless:late_fees_enabled,true', 'required', 'string', 'regex:'.self::AMOUNT_PATTERN],
            'percent_fee' => ['exclude_unless:fee_kind,percent', 'exclude_unless:late_fees_enabled,true', 'required', 'numeric', 'gt:0', 'max:100', 'decimal:0,2'],
            'minimum_balance' => ['exclude_unless:late_fees_enabled,true', 'nullable', 'string', 'regex:'.self::AMOUNT_PATTERN],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function vendorBillRules(Community $community): array
    {
        return [
            'vendor_id' => ['required', 'integer', Rule::exists(Vendor::class, 'id')->where('company_id', $community->company_id)->withoutTrashed()],
            'account_id' => [
                'required', 'integer',
                Rule::exists(Account::class, 'id')->where('community_id', $community->id)->where('type', AccountType::Expense->value)->where('is_active', true),
            ],
            'description' => ['required', 'string', 'max:255'],
            'vendor_reference' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'string', 'regex:'.self::AMOUNT_PATTERN],
            'billed_on' => ['required', 'date'],
            'due_on' => ['required', 'date', 'after_or_equal:billed_on'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function billPaymentRules(): array
    {
        return [
            'payment_method' => ['required', Rule::in(array_map(fn (PaymentMethod $method) => $method->value, PaymentMethod::manual()))],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function unitInCommunity(Community $community): Exists
    {
        return Rule::exists(Unit::class, 'id')->where('community_id', $community->id)->withoutTrashed();
    }
}
