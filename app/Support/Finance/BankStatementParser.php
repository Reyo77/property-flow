<?php

namespace App\Support\Finance;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Turns the rows of a bank's CSV export into statement lines. Banks differ, so it accepts either
 * one signed `amount` column or separate `deposit`/`credit` and `withdrawal`/`debit` columns, and
 * dates as YYYY-MM-DD or MM/DD/YYYY.
 */
class BankStatementParser
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{posted_on: CarbonImmutable, description: string, reference: string|null, amount_cents: int}>
     *
     * @throws ValidationException naming the first bad row
     */
    public function parse(array $rows, string $currency = 'CAD'): array
    {
        $lines = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $row = array_map(fn ($value) => is_scalar($value) ? trim((string) $value) : '', $row);

            if (implode('', $row) === '') {
                continue;
            }

            $date = $this->first($row, $this->headers('date'));
            $description = $this->first($row, $this->headers('description'));

            if ($date === null || $description === null) {
                $this->fail($rowNumber, __('needs a date and a description'));
            }

            $lines[] = [
                'posted_on' => $this->date($date, $rowNumber),
                'description' => mb_substr($description, 0, 255),
                'reference' => $this->first($row, $this->headers('reference')),
                'amount_cents' => $this->amount($row, $rowNumber, $currency),
            ];
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['file' => __('The file has no transactions.')]);
        }

        return $lines;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function amount(array $row, int $rowNumber, string $currency): int
    {
        $amount = $this->first($row, ['amount']);
        $deposit = $this->first($row, $this->headers('deposit'));
        $withdrawal = $this->first($row, $this->headers('withdrawal'));

        if ($amount === null && $deposit === null && $withdrawal === null) {
            $this->fail($rowNumber, __('has no amount'));
        }

        try {
            $cents = $amount !== null
                ? $this->money($amount, $currency)->cents
                : ($deposit === null ? 0 : abs($this->money($deposit, $currency)->cents)) - ($withdrawal === null ? 0 : abs($this->money($withdrawal, $currency)->cents));
        } catch (InvalidArgumentException) {
            $this->fail($rowNumber, __('has an amount that is not a number'));
        }

        if ($cents === 0) {
            $this->fail($rowNumber, __('has a zero amount'));
        }

        return $cents;
    }

    /**
     * Accepts "(12.50)" for negatives too, as many banks write them.
     */
    private function money(string $value, string $currency): Money
    {
        if (preg_match('/^\((.+)\)$/', $value, $matches) === 1) {
            return Money::parse($matches[1], $currency)->negate();
        }

        return Money::parse($value, $currency);
    }

    private function date(string $value, int $rowNumber): CarbonImmutable
    {
        foreach (['!Y-m-d', '!m/d/Y', '!n/j/Y', '!Y/m/d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $value);
            } catch (InvalidFormatException) {
                continue;
            }

            // Rejects overflowing dates like 2026-02-30, which PHP would roll into March.
            if ($date instanceof CarbonImmutable && $date->format(ltrim($format, '!')) === $value) {
                return $date;
            }
        }

        $this->fail($rowNumber, __('has a date that is not YYYY-MM-DD or MM/DD/YYYY'));
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $columns
     */
    private function first(array $row, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (($row[$column] ?? '') !== '') {
                return $row[$column];
            }
        }

        return null;
    }

    private function fail(int $rowNumber, string $problem): never
    {
        throw ValidationException::withMessages(['file' => __('Row :row :problem.', ['row' => $rowNumber, 'problem' => $problem])]);
    }

    /**
     * The column headings banks use for a field, first match wins.
     *
     * @return list<string>
     */
    private function headers(string $field): array
    {
        return match ($field) {
            'date' => ['date', 'posted_on', 'transaction_date', 'posting_date'],
            'description' => ['description', 'details', 'memo', 'payee', 'transaction'],
            'reference' => ['reference', 'ref', 'cheque', 'cheque_number', 'check_number'],
            'deposit' => ['deposit', 'deposits', 'credit', 'credits'],
            'withdrawal' => ['withdrawal', 'withdrawals', 'debit', 'debits'],
            default => throw new InvalidArgumentException("Unknown bank statement field: {$field}"),
        };
    }
}
