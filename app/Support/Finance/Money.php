<?php

namespace App\Support\Finance;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An immutable amount of money in integer minor units (cents). All arithmetic stays in
 * integers so no amount is ever rounded implicitly; the only place rounding happens is
 * allocate(), which distributes remainders explicitly so the parts always sum to the whole.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    public function __construct(public int $cents, public string $currency = 'CAD')
    {
        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException("Invalid currency code [{$currency}].");
        }
    }

    public static function of(int $cents, string $currency = 'CAD'): self
    {
        return new self($cents, strtoupper($currency));
    }

    public static function zero(string $currency = 'CAD'): self
    {
        return self::of(0, $currency);
    }

    /**
     * Parse a user-entered decimal amount such as "1,234.5" or "-12.30" without going through a float.
     */
    public static function parse(string $amount, string $currency = 'CAD'): self
    {
        $normalized = str_replace([',', ' ', '$'], '', trim($amount));

        if (preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches) !== 1) {
            throw new InvalidArgumentException("Cannot parse [{$amount}] as an amount of money.");
        }

        $cents = ((int) $matches[2] * 100) + (int) str_pad($matches[3] ?? '', 2, '0');

        return self::of($matches[1] === '-' ? -$cents : $cents, $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents - $other->cents, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->cents, $this->currency);
    }

    /**
     * Multiply by a whole-number quantity; percentages go through allocate() or percentOf().
     */
    public function times(int $quantity): self
    {
        return new self($this->cents * $quantity, $this->currency);
    }

    /**
     * A percentage of this amount, rounded half away from zero to the nearest cent.
     * $basisPoints is hundredths of a percent, so 150 = 1.5%.
     */
    public function percentOf(int $basisPoints): self
    {
        $product = $this->cents * $basisPoints;
        $rounded = intdiv($product + ($product >= 0 ? 5000 : -5000), 10000);

        return new self($rounded, $this->currency);
    }

    /**
     * Split this amount in proportion to the given ratios using the largest-remainder method, so
     * the parts always add up to exactly this amount (no cent is created or lost). Ratios may be
     * any non-negative numeric strings, e.g. unit factors like "0.833333".
     *
     * @param  array<array-key, string|int>  $ratios
     * @return array<array-key, self>
     */
    public function allocate(array $ratios): array
    {
        if ($ratios === []) {
            throw new InvalidArgumentException('Cannot allocate money across zero ratios.');
        }

        $scale = 10;
        $total = '0';

        foreach ($ratios as $ratio) {
            if (! is_numeric($ratio) || bccomp((string) $ratio, '0', $scale) < 0) {
                throw new InvalidArgumentException('Allocation ratios must be non-negative numbers.');
            }

            $total = bcadd($total, (string) $ratio, $scale);
        }

        if (bccomp($total, '0', $scale) === 0) {
            throw new InvalidArgumentException('Allocation ratios must not all be zero.');
        }

        $sign = $this->cents < 0 ? -1 : 1;
        $amount = (string) abs($this->cents);
        $parts = [];
        $remainders = [];
        $allocated = 0;

        foreach ($ratios as $key => $ratio) {
            $exact = bcdiv(bcmul($amount, (string) $ratio, $scale), $total, $scale);
            $floor = (int) bcadd($exact, '0', 0);
            $parts[$key] = $floor;
            $remainders[$key] = bcsub($exact, (string) $floor, $scale);
            $allocated += $floor;
        }

        $leftover = abs($this->cents) - $allocated;

        // Hand out the leftover cents one at a time to the largest remainders; ties go to the
        // earliest key so the result is deterministic.
        $order = array_keys($remainders);
        usort($order, fn (int|string $a, int|string $b): int => bccomp($remainders[$b], $remainders[$a], $scale)
            ?: array_search($a, array_keys($ratios), true) <=> array_search($b, array_keys($ratios), true));

        for ($i = 0; $i < $leftover; $i++) {
            $parts[$order[$i]]++;
        }

        return array_map(fn (int $cents): self => new self($sign * $cents, $this->currency), $parts);
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->cents === $other->cents;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->cents > $other->cents;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->cents < $other->cents;
    }

    public function min(self $other): self
    {
        return $this->lessThan($other) ? $this : $other;
    }

    /**
     * The plain decimal amount, e.g. "-1234.50", suitable for inputs and CSV exports.
     */
    public function toDecimal(): string
    {
        $absolute = abs($this->cents);

        return ($this->cents < 0 ? '-' : '').intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * A display string such as "$1,234.50" or "-$12.00".
     */
    public function format(): string
    {
        $absolute = abs($this->cents);

        return ($this->cents < 0 ? '-' : '').'$'.number_format(intdiv($absolute, 100)).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public function __toString(): string
    {
        return $this->format();
    }

    /**
     * @return array{cents: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return ['cents' => $this->cents, 'currency' => $this->currency];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Cannot combine {$this->currency} with {$other->currency}.");
        }
    }
}
