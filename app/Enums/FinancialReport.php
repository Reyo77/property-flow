<?php

namespace App\Enums;

enum FinancialReport: string
{
    case IncomeStatement = 'income-statement';
    case BalanceSheet = 'balance-sheet';
    case AgedReceivables = 'aged-receivables';
    case BudgetVsActual = 'budget-vs-actual';
    case GeneralLedger = 'general-ledger';

    public function label(): string
    {
        return match ($this) {
            self::IncomeStatement => __('Income statement'),
            self::BalanceSheet => __('Balance sheet'),
            self::AgedReceivables => __('Aged receivables'),
            self::BudgetVsActual => __('Budget vs actual'),
            self::GeneralLedger => __('General ledger'),
        };
    }

    /**
     * Whether the report covers a period (from/to) rather than a single date.
     */
    public function isForPeriod(): bool
    {
        return $this === self::IncomeStatement || $this === self::GeneralLedger;
    }
}
