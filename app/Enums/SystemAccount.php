<?php

namespace App\Enums;

/**
 * Accounts the app posts to automatically. Every community's chart has exactly one of each,
 * found by this key rather than by code or name, which companies are free to change.
 */
enum SystemAccount: string
{
    case Cash = 'cash';
    case Receivables = 'receivables';
    case Payables = 'payables';
    case Deposits = 'deposits';
    case RetainedEarnings = 'retained_earnings';
    case Assessments = 'assessments';
    case LateFees = 'late_fees';
    case AmenityFees = 'amenity_fees';
    case Fines = 'fines';
    case OtherIncome = 'other_income';

    public function type(): AccountType
    {
        return match ($this) {
            self::Cash, self::Receivables => AccountType::Asset,
            self::Payables, self::Deposits => AccountType::Liability,
            self::RetainedEarnings => AccountType::Equity,
            self::Assessments, self::LateFees, self::AmenityFees, self::Fines, self::OtherIncome => AccountType::Income,
        };
    }
}
