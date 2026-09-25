<?php

namespace App\Concerns;

use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReversalReason;
use App\Models\Account;
use App\Models\ChargeType;
use App\Models\Community;
use App\Models\Unit;
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

    private function unitInCommunity(Community $community): Exists
    {
        return Rule::exists(Unit::class, 'id')->where('community_id', $community->id)->withoutTrashed();
    }
}
