<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Cheque = 'cheque';
    case BankTransfer = 'bank_transfer';
    case Online = 'online';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Cheque => __('Cheque'),
            self::BankTransfer => __('Bank transfer'),
            self::Online => __('Online'),
            self::Other => __('Other'),
        };
    }

    /**
     * Methods a manager can record by hand; online payments only arrive through a gateway.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Cash, self::Cheque, self::BankTransfer, self::Other];
    }
}
