<?php

namespace App\Support\Pdf;

use App\Support\JalaliDate;
use GdImage;

class ReportChart
{
    /**
     * @param  list<array{label: string, value: float|null}>  $points
     */
    public static function line(array $points, string $yTitle, string $xTitle, string $color, float $minimum, float $maximum): string
    {
        if ($points === []) {
            return '';
        }

        $maximum = max($maximum, $minimum + 1);

        return self::render($points, $yTitle, $xTitle, $minimum, $maximum, function (GdImage $image, array $geometry) use ($points, $color): void {
            $series = self::color($image, $color);
            $segments = [];
            $current = [];

            foreach ($points as $index => $point) {
                if ($point['value'] === null) {
                    if ($current !== []) {
                        $segments[] = $current;
                        $current = [];
                    }

                    continue;
                }

                $current[] = [$geometry['x']($index), $geometry['y']($point['value']), $point['value']];
            }
            if ($current !== []) {
                $segments[] = $current;
            }

            imagesetthickness($image, 3);
            foreach ($segments as $segment) {
                if (count($segment) > 1) {
                    $previous = null;
                    foreach ($segment as $coordinate) {
                        if ($previous !== null) {
                            imageline($image, $previous[0], $previous[1], $coordinate[0], $coordinate[1], $series);
                        }
                        $previous = $coordinate;
                    }
                }

                foreach ($segment as [$x, $y, $value]) {
                    imagefilledellipse($image, $x, $y, 10, 10, $series);
                    imageellipse($image, $x, $y, 10, 10, self::color($image, '#ffffff'));
                    if ($geometry['labelValues']) {
                        self::text($image, 12, $x, $y - 12, self::color($image, '#18181b'), self::number($value), 'center');
                    }
                }
            }
        });
    }

    /**
     * @param  list<array{label: string, value: float|null}>  $points
     */
    public static function columns(array $points, string $yTitle, string $xTitle, string $color): string
    {
        if ($points === []) {
            return '';
        }

        $peak = max(array_map(fn (array $point): float => (float) ($point['value'] ?? 0), $points));
        $maximum = self::niceCeiling($peak);

        return self::render($points, $yTitle, $xTitle, 0, $maximum, function (GdImage $image, array $geometry) use ($points, $color): void {
            $series = self::color($image, $color);
            $count = count($points);
            $slot = $count > 0 ? $geometry['plotWidth'] / $count : $geometry['plotWidth'];
            $barWidth = max(8, min(42, (int) round($slot * 0.62)));

            foreach ($points as $index => $point) {
                $value = $point['value'];
                if ($value === null) {
                    continue;
                }

                $x = $geometry['x']($index) - (int) ($barWidth / 2);
                $y = $geometry['y']($value);
                $height = max(2, $geometry['bottom'] - $y);
                imagefilledrectangle($image, $x, $geometry['bottom'] - $height, $x + $barWidth, $geometry['bottom'], $series);
                if ($geometry['labelValues']) {
                    self::text($image, 12, $geometry['x']($index), $geometry['bottom'] - $height - 8, self::color($image, '#18181b'), self::number($value), 'center');
                }
            }
        });
    }

    /**
     * @param  list<array{label: string, values: array<string, int>}>  $rows
     * @param  list<array{key: string, label: string, color: string}>  $series
     */
    public static function stacked(array $rows, array $series, string $yTitle, string $xTitle): string
    {
        if ($rows === [] || $series === []) {
            return '';
        }

        $points = array_map(fn (array $row): array => [
            'label' => $row['label'],
            'value' => array_sum($row['values']),
        ], $rows);
        $maximum = self::niceCeiling((float) max(array_column($points, 'value')));

        return self::render($points, $yTitle, $xTitle, 0, $maximum, function (GdImage $image, array $geometry) use ($rows, $series): void {
            $count = count($rows);
            $slot = $count > 0 ? $geometry['plotWidth'] / $count : $geometry['plotWidth'];
            $barWidth = max(8, min(42, (int) round($slot * 0.62)));

            foreach ($rows as $index => $row) {
                $stacked = 0.0;
                $x = $geometry['x']($index) - (int) ($barWidth / 2);
                foreach ($series as $item) {
                    $value = (int) ($row['values'][$item['key']] ?? 0);
                    if ($value <= 0) {
                        continue;
                    }
                    $stacked += $value;
                    $top = $geometry['y']($stacked);
                    $bottom = $geometry['y']($stacked - $value);
                    imagefilledrectangle($image, $x, $top, $x + $barWidth, $bottom, self::color($image, $item['color']));
                }
            }

            self::legend($image, $series, $geometry['width']);
        }, topPadding: 58);
    }

    /**
     * @param  list<array{label: string, value: float|null}>  $points
     * @param  callable(GdImage, array<string, mixed>): void  $drawSeries
     */
    private static function render(
        array $points,
        string $yTitle,
        string $xTitle,
        float $minimum,
        float $maximum,
        callable $drawSeries,
        int $topPadding = 46,
    ): string {
        $count = count($points);
        $width = 1500;
        $plotHeight = 300;
        $stagger = $count > 12;
        $bottomPadding = $stagger ? 108 : 78;
        $left = 86;
        $right = 28;
        $height = $topPadding + $plotHeight + $bottomPadding;
        $image = imagecreatetruecolor($width, $height);
        imageantialias($image, true);
        imagefill($image, 0, 0, self::color($image, '#ffffff'));

        $bottom = $topPadding + $plotHeight;
        $plotWidth = $width - $left - $right;
        $labelValues = $plotWidth / max(1, $count) >= 36;
        $x = function (int $index) use ($count, $left, $plotWidth): int {
            if ($count <= 1) {
                return (int) round($left + ($plotWidth / 2));
            }

            return (int) round($left + (($index + 0.5) * ($plotWidth / $count)));
        };
        $y = function (float $value) use ($minimum, $maximum, $topPadding, $plotHeight): int {
            $ratio = ($value - $minimum) / ($maximum - $minimum);

            return (int) round($topPadding + ((1 - max(0, min(1, $ratio))) * $plotHeight));
        };
        $grid = self::color($image, '#e4e4e7');
        $axis = self::color($image, '#3f3f46');
        $muted = self::color($image, '#3f3f46');
        imagesetthickness($image, 1);
        for ($step = 0; $step <= 5; $step++) {
            $value = $minimum + (($maximum - $minimum) * ($step / 5));
            $pixel = $y($value);
            imageline($image, $left, $pixel, $width - $right, $pixel, $grid);
            self::text($image, 13, $left - 10, $pixel + 5, $muted, self::number($value), 'right');
        }

        imagesetthickness($image, 2);
        imageline($image, $left, $topPadding, $left, $bottom, $axis);
        imageline($image, $left, $bottom, $width - $right, $bottom, $axis);
        self::text($image, 14, $left, $topPadding - 14, $axis, $yTitle, 'left');

        foreach ($points as $index => $point) {
            $tick = $x($index);
            imageline($image, $tick, $bottom, $tick, $bottom + 5, $axis);
            $row = $stagger && $index % 2 === 1 ? 1 : 0;
            self::text($image, $count > 24 ? 11 : 13, $tick, $bottom + 24 + ($row * 24), $muted, $point['label'], 'center');
        }

        self::text($image, 14, (int) ($left + ($plotWidth / 2)), $height - 16, $axis, $xTitle, 'center');

        $drawSeries($image, [
            'x' => $x,
            'y' => $y,
            'bottom' => $bottom,
            'plotWidth' => $plotWidth,
            'width' => $width,
            'labelValues' => $labelValues,
        ]);

        ob_start();
        imagepng($image, null, 6);
        $png = ob_get_clean();
        imagedestroy($image);

        return is_string($png) ? $png : '';
    }

    /**
     * @param  list<array{key: string, label: string, color: string}>  $series
     */
    private static function legend(GdImage $image, array $series, int $width): void
    {
        $cursor = $width - 28;
        $ink = self::color($image, '#18181b');
        foreach (array_reverse($series) as $item) {
            $label = PersianGlyphs::visual($item['label']);
            $box = imagettfbbox(13, 0, self::font(), $label) ?: [0, 0, 0, 0, 0, 0, 0, 0];
            $labelWidth = abs($box[2] - $box[0]);
            $cursor -= $labelWidth;
            imagettftext($image, 13, 0, $cursor, 24, $ink, self::font(), $label);
            $cursor -= 18;
            imagefilledrectangle($image, $cursor, 12, $cursor + 12, 24, self::color($image, $item['color']));
            $cursor -= 16;
        }
    }

    private static function text(GdImage $image, int $size, int $x, int $y, int $color, string $logical, string $align): void
    {
        if ($logical === '') {
            return;
        }

        $visual = PersianGlyphs::visual($logical);
        $box = imagettfbbox($size, 0, self::font(), $visual) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        $width = abs($box[2] - $box[0]);
        $origin = match ($align) {
            'right' => $x - $width,
            'center' => $x - (int) ($width / 2),
            default => $x,
        };
        imagettftext($image, $size, 0, $origin, $y, $color, self::font(), $visual);
    }

    private static function number(float $value): string
    {
        $rounded = round($value, 1);
        $formatted = abs($rounded - round($rounded)) < 0.05
            ? (string) (int) round($rounded)
            : number_format($rounded, 1, '.', '');

        return JalaliDate::persianDigits($formatted);
    }

    private static function niceCeiling(float $peak): float
    {
        if ($peak <= 1) {
            return 1;
        }

        $exponent = floor(log10($peak));
        $base = 10 ** $exponent;
        $fraction = $peak / $base;
        $nice = match (true) {
            $fraction <= 1 => 1,
            $fraction <= 2 => 2,
            $fraction <= 5 => 5,
            default => 10,
        };

        return $nice * $base;
    }

    private static function color(GdImage $image, string $hex): int
    {
        $hex = ltrim($hex, '#');

        return imagecolorallocate(
            $image,
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }

    private static function font(): string
    {
        return resource_path('fonts/Vazirmatn-Regular.ttf');
    }
}
