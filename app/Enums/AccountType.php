<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Asset => __('Asset'),
            self::Liability => __('Liability'),
            self::Equity => __('Equity'),
            self::Income => __('Income'),
            self::Expense => __('Expense'),
        };
    }

    /**
     * Assets and expenses grow with debits; liabilities, equity and income grow with credits.
     */
    public function isDebitNormal(): bool
    {
        return $this === self::Asset || $this === self::Expense;
    }

    /**
     * An account's balance in its natural direction (positive = normal balance).
     */
    public function balanceFrom(int $debitCents, int $creditCents): int
    {
        return $this->isDebitNormal() ? $debitCents - $creditCents : $creditCents - $debitCents;
    }
}
