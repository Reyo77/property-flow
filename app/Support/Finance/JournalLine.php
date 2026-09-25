<?php

namespace App\Support\Finance;

use App\Models\Account;
use App\Models\Unit;
use InvalidArgumentException;

/**
 * One side of a journal entry before it is posted.
 */
final readonly class JournalLine
{
    private function __construct(
        public Account $account,
        public int $debitCents,
        public int $creditCents,
        public ?int $unitId,
        public ?string $memo,
    ) {}

    public static function debit(Account $account, Money $amount, ?Unit $unit = null, ?string $memo = null): self
    {
        self::assertPositive($amount);

        return new self($account, $amount->cents, 0, $unit?->id, $memo);
    }

    public static function credit(Account $account, Money $amount, ?Unit $unit = null, ?string $memo = null): self
    {
        self::assertPositive($amount);

        return new self($account, 0, $amount->cents, $unit?->id, $memo);
    }

    /**
     * The same line on the opposite side, used to build a reversal.
     */
    public function flipped(): self
    {
        return new self($this->account, $this->creditCents, $this->debitCents, $this->unitId, $this->memo);
    }

    public static function fromStored(Account $account, int $debitCents, int $creditCents, ?int $unitId, ?string $memo): self
    {
        return new self($account, $debitCents, $creditCents, $unitId, $memo);
    }

    private static function assertPositive(Money $amount): void
    {
        if (! $amount->isPositive()) {
            throw new InvalidArgumentException('A journal line amount must be greater than zero.');
        }
    }
}
