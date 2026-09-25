<?php

use App\Support\Finance\Money;

it('adds, subtracts and negates in whole cents', function () {
    $a = Money::of(1050);
    $b = Money::of(275);

    expect($a->plus($b)->cents)->toBe(1325)
        ->and($a->minus($b)->cents)->toBe(775)
        ->and($b->minus($a)->cents)->toBe(-775)
        ->and($a->negate()->cents)->toBe(-1050)
        ->and($a->times(3)->cents)->toBe(3150);
});

it('refuses to mix currencies', function (string $method) {
    Money::of(100, 'CAD')->{$method}(Money::of(100, 'USD'));
})->with(['plus', 'minus', 'greaterThan', 'lessThan'])->throws(InvalidArgumentException::class);

it('rejects an invalid currency code', function () {
    new Money(100, 'CA');
})->throws(InvalidArgumentException::class);

it('uppercases the currency', function () {
    expect(Money::of(1, 'usd')->currency)->toBe('USD');
});

it('parses user-entered amounts without floating point', function (string $input, int $cents) {
    expect(Money::parse($input)->cents)->toBe($cents);
})->with([
    ['12', 1200],
    ['12.5', 1250],
    ['12.05', 1205],
    ['1,234.56', 123456],
    ['$99.99', 9999],
    ['-3.10', -310],
    ['0.01', 1],
    ['0', 0],
    [' 7.00 ', 700],
    ["\t7.00\n", 700],
    ['1 234.50', 123450],
]);

it('refuses to parse garbage or sub-cent precision', function (string $input) {
    Money::parse($input);
})->with(['abc', '1.234', '', '1.2.3', '--1'])->throws(InvalidArgumentException::class);

it('takes a percentage in basis points, rounding half away from zero', function (int $cents, int $basisPoints, int $expected) {
    expect(Money::of($cents)->percentOf($basisPoints)->cents)->toBe($expected);
})->with([
    [10000, 150, 150],      // 1.5% of $100
    [3333, 1000, 333],      // 10% of $33.33 = 333.3 -> 333
    [3335, 1000, 334],      // 333.5 -> 334
    [-3335, 1000, -334],    // negative rounds away from zero too
    [100, 5000, 50],
    [0, 1234, 0],
    [5000, 1, 1],           // exactly half a cent rounds up...
    [4999, 1, 0],           // ...just under half rounds down
    [-5000, 1, -1],         // and the same, mirrored, for negatives
    [-4999, 1, 0],
    [100_000_000, 10000, 100_000_000], // 100% is the whole amount, even for large sums
]);

it('allocates so the parts always sum to the whole', function (int $cents, array $ratios, array $expected) {
    $parts = Money::of($cents)->allocate($ratios);

    expect(array_map(fn (Money $money) => $money->cents, $parts))->toBe($expected)
        ->and(array_sum(array_map(fn (Money $money) => $money->cents, $parts)))->toBe($cents);
})->with([
    'even split' => [100, [1, 1], [50, 50]],
    'thirds give the extra cent to the first' => [100, [1, 1, 1], [34, 33, 33]],
    'largest remainder wins' => [100, [1, 2], [33, 67]],
    'unit factors' => [100000, ['33.333333', '33.333333', '33.333334'], [33333, 33333, 33334]],
    'zero ratio gets nothing' => [500, [1, 0, 1], [250, 0, 250]],
    'negative amounts' => [-100, [1, 1, 1], [-34, -33, -33]],
    'a single negative cent' => [-1, [1, 1], [-1, 0]],
    'single ratio' => [777, ['0.5'], [777]],
    'zero amount' => [0, [1, 2], [0, 0]],
]);

it('preserves array keys when allocating', function () {
    $parts = Money::of(100)->allocate(['unit-a' => 1, 'unit-b' => 3]);

    expect(array_keys($parts))->toBe(['unit-a', 'unit-b'])
        ->and($parts['unit-a']->cents)->toBe(25)
        ->and($parts['unit-b']->cents)->toBe(75);
});

it('sums exactly when allocating a large amount over 120 unit factors', function () {
    $factors = array_fill(0, 119, '0.833333');
    $factors[] = '0.833373';

    $parts = Money::of(12345678)->allocate($factors);

    expect(array_sum(array_map(fn (Money $money) => $money->cents, $parts)))->toBe(12345678);
});

it('refuses to allocate across nothing, all zeros, or negative ratios', function (array $ratios) {
    Money::of(100)->allocate($ratios);
})->with([
    'empty' => [[]],
    'all zero' => [[0, '0.0']],
    'negative' => [[1, -1]],
    'not numeric' => [['a']],
])->throws(InvalidArgumentException::class);

it('compares amounts', function () {
    $small = Money::of(100);
    $big = Money::of(200);

    expect($big->greaterThan($small))->toBeTrue()
        ->and($small->greaterThan($big))->toBeFalse()
        ->and($small->greaterThan($small))->toBeFalse()
        ->and($small->lessThan($big))->toBeTrue()
        ->and($big->lessThan($small))->toBeFalse()
        ->and($small->lessThan($small))->toBeFalse()
        ->and($small->min($big))->toBe($small)
        ->and($big->min($small))->toBe($small)
        ->and($small->equals(Money::of(100)))->toBeTrue()
        ->and($small->equals(Money::of(100, 'USD')))->toBeFalse()
        ->and($small->equals($big))->toBeFalse();
});

it('reports its sign', function () {
    expect(Money::zero()->isZero())->toBeTrue()
        ->and(Money::of(1)->isZero())->toBeFalse()
        ->and(Money::of(1)->isPositive())->toBeTrue()
        ->and(Money::of(0)->isPositive())->toBeFalse()
        ->and(Money::of(-1)->isPositive())->toBeFalse()
        ->and(Money::of(-1)->isNegative())->toBeTrue()
        ->and(Money::of(0)->isNegative())->toBeFalse();
});

it('formats for display and for export', function (int $cents, string $display, string $decimal) {
    $money = Money::of($cents);

    expect($money->format())->toBe($display)
        ->and((string) $money)->toBe($display)
        ->and($money->toDecimal())->toBe($decimal);
})->with([
    [0, '$0.00', '0.00'],
    [5, '$0.05', '0.05'],
    [123456, '$1,234.56', '1234.56'],
    [-1200, '-$12.00', '-12.00'],
    [-7, '-$0.07', '-0.07'],
    [-1, '-$0.01', '-0.01'],
    [105, '$1.05', '1.05'],
]);

it('serializes to json', function () {
    expect(json_encode(Money::of(150, 'USD')))->toBe('{"cents":150,"currency":"USD"}');
});
