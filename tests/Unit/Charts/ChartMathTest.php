<?php

use App\Support\Charts\ChartMath;

it('returns empty everything for no values', function () {
    expect(ChartMath::linePath([]))->toBe(['path' => '', 'areaPath' => '', 'points' => []]);
});

it('places a single value as one point with no path to draw', function () {
    $result = ChartMath::linePath([50], width: 400, height: 120, padding: 8);

    expect($result['path'])->toBe('')
        ->and($result['areaPath'])->toBe('')
        ->and($result['points'])->toHaveCount(1)
        ->and($result['points'][0]['x'])->toEqual(200.0)
        ->and($result['points'][0]['y'])->toEqual(8.0);
});

it('draws a flat line at mid-height when every value is zero', function () {
    $result = ChartMath::linePath([0, 0, 0], width: 400, height: 120, padding: 8);

    expect($result['points'])->toHaveCount(3)
        ->and(array_column($result['points'], 'y'))->toBe([60.0, 60.0, 60.0])
        ->and($result['path'])->toStartWith('M')
        ->and($result['areaPath'])->toEndWith('Z');
});

it('draws a value that dips negative below the zero baseline', function () {
    $points = ChartMath::linePath([10, -5, 20], width: 400, height: 120, padding: 8)['points'];

    // Zero-anchored scale over this series' actual [-5, 20] range.
    $zeroBaselineY = 8 + (20 - 0) / (20 - -5) * (120 - 2 * 8);

    expect($points[2]['y'])->toBe(8.0) // the max value (20) sits at the very top
        ->and($points[1]['y'])->toBeGreaterThan($zeroBaselineY) // -5 draws below zero (SVG y grows down)
        ->and($points[1]['y'])->toBe(112.0)
        ->and($points[0]['y'])->toBe(49.6);
});

it('spaces points evenly across the width', function () {
    $result = ChartMath::linePath([1, 2, 3, 4], width: 400, height: 120, padding: 8);

    expect(array_column($result['points'], 'x'))->toEqual([8.0, 136.0, 264.0, 392.0]);
});

it('scales against a forced min/max instead of the series\' own range', function () {
    // Without forcing, [10, 20] would scale 10 to the bottom; forcing the domain to [0, 100]
    // (e.g. because a sibling series reaches 100) should place both points much higher up.
    $result = ChartMath::linePath([10, 20], width: 400, height: 120, padding: 8, forceMin: 0, forceMax: 100);

    expect($result['points'][0]['y'])->toBeGreaterThan(90.0)
        ->and($result['points'][1]['y'])->toBeGreaterThan($result['points'][0]['y'] - 30)
        ->and($result['points'][1]['y'])->toBeLessThan($result['points'][0]['y']);
});

it('splits a donut into segments whose percents sum to 100', function () {
    $segments = ChartMath::donutSegments([3, 1]);
    $firstSegmentLength = (float) explode(' ', $segments[0]['dasharray'])[0];

    expect($segments)->toHaveCount(2)
        ->and($segments[0]['percent'])->toBe(75.0)
        ->and($segments[1]['percent'])->toBe(25.0)
        ->and($segments[0]['dashoffset'])->toBe(0.0)
        ->and(abs($segments[1]['dashoffset'] - (-$firstSegmentLength)))->toBeLessThan(0.001);
});

it('handles three donut segments summing to 100 percent', function () {
    $segments = ChartMath::donutSegments([1, 1, 2]);

    expect($segments)->toHaveCount(3)
        ->and(array_sum(array_column($segments, 'percent')))->toBe(100.0);
});

it('returns no segments for an all-zero donut', function () {
    expect(ChartMath::donutSegments([0, 0]))->toBe([]);
});

it('rejects a negative donut value', function () {
    ChartMath::donutSegments([3, -1]);
})->throws(InvalidArgumentException::class);
