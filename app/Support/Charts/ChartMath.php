<?php

namespace App\Support\Charts;

use InvalidArgumentException;

/**
 * The geometry behind the hand-rolled SVG charts in resources/views/components/charts — pure
 * functions over plain numbers, so they're trivial to unit test and carry no framework
 * dependency. Presentation (color, labels, legends) stays in the Blade components; this class
 * only turns values into coordinates.
 */
class ChartMath
{
    /**
     * A polyline (and matching filled-area path) for a single series, scaled to fit the given
     * box. The scale is always zero-anchored — it extends to whichever extreme the data reaches,
     * but never drops the zero line — so a series that dips negative (a net-loss month) draws
     * visibly below a real baseline instead of an arbitrary min. The trade-off: a series that
     * never approaches zero reads flatter than a min-anchored chart would; that's accepted for an
     * honest baseline.
     *
     * $forceMin/$forceMax let several series share one scale (so multiple lines on the same
     * chart stay comparable) — pass the combined domain across all series instead of letting
     * each one scale to its own range.
     *
     * @param  list<int|float>  $values
     * @return array{path: string, areaPath: string, points: list<array{x: float, y: float}>}
     */
    public static function linePath(array $values, int $width = 400, int $height = 120, int $padding = 8, ?float $forceMin = null, ?float $forceMax = null): array
    {
        $count = count($values);

        if ($count === 0) {
            return ['path' => '', 'areaPath' => '', 'points' => []];
        }

        $min = $forceMin ?? min(0, min($values));
        $max = $forceMax ?? max(0, max($values));
        $innerWidth = $width - 2 * $padding;
        $innerHeight = $height - 2 * $padding;

        $toY = function (float $value) use ($min, $max, $padding, $innerHeight, $height): float {
            if ($max === $min) {
                return $height / 2;
            }

            return $padding + ($max - $value) / ($max - $min) * $innerHeight;
        };

        if ($count === 1) {
            return ['path' => '', 'areaPath' => '', 'points' => [['x' => $width / 2, 'y' => $toY((float) $values[0])]]];
        }

        $points = [];

        foreach ($values as $index => $value) {
            $points[] = [
                'x' => $padding + $index * ($innerWidth / ($count - 1)),
                'y' => $toY((float) $value),
            ];
        }

        $path = 'M'.implode(' L', array_map(fn (array $point) => " {$point['x']},{$point['y']}", $points));

        $baselineY = $toY(0.0);
        $first = $points[0];
        $last = $points[$count - 1];
        $areaPath = "M {$first['x']},{$baselineY}".implode('', array_map(fn (array $point) => " L {$point['x']},{$point['y']}", $points))." L {$last['x']},{$baselineY} Z";

        return ['path' => $path, 'areaPath' => $areaPath, 'points' => $points];
    }

    /**
     * Cumulative stroke-dasharray/dashoffset for an SVG-circle donut, one segment per value. The
     * circle is drawn full-strength for the segment's share of the circumference, then a huge gap
     * for the rest; the negative, cumulative dashoffset walks each segment's visible arc forward
     * around the circle. Pair with a `-rotate-90` transform on the wrapping <svg> so the natural
     * 3-o'clock start becomes 12-o'clock.
     *
     * @param  list<int|float>  $values  Non-negative weights or counts — never signed amounts.
     * @return list<array{dasharray: string, dashoffset: float, percent: float}>
     */
    public static function donutSegments(array $values, int $size = 120, int $strokeWidth = 16): array
    {
        foreach ($values as $value) {
            if ($value < 0) {
                throw new InvalidArgumentException('donutSegments() values must be non-negative.');
            }
        }

        $total = array_sum($values);

        if ($total <= 0) {
            return [];
        }

        $radius = ($size - $strokeWidth) / 2;
        $circumference = 2 * M_PI * $radius;
        $cumulative = 0.0;
        $segments = [];

        foreach ($values as $value) {
            $fraction = $value / $total;
            $length = $fraction * $circumference;

            $segments[] = [
                'dasharray' => "{$length} ".($circumference - $length),
                'dashoffset' => -$cumulative,
                'percent' => round($fraction * 100, 1),
            ];

            $cumulative += $length;
        }

        return $segments;
    }
}
