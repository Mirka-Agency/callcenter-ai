<?php

namespace Tests\Unit;

use App\Support\Pdf\PersianGlyphs;
use App\Support\Pdf\ReportChart;
use PHPUnit\Framework\TestCase;

class ReportChartTest extends TestCase
{
    public function test_persian_words_are_shaped_and_numbers_keep_their_order(): void
    {
        $this->assertSame("\u{FE8E}\u{FEE3}", PersianGlyphs::visual('ما'));
        $this->assertSame('۱۲', PersianGlyphs::visual('۱۲'));
        $this->assertNotSame('مهر', PersianGlyphs::visual('مهر'));
    }

    public function test_higher_score_is_drawn_higher_and_both_points_exist(): void
    {
        $png = ReportChart::line([
            ['label' => '۱ مهر', 'value' => 20],
            ['label' => '۲ مهر', 'value' => 80],
        ], 'امتیاز مکالمه (۰ تا ۱۰۰)', 'بازه زمانی', '#059669', 0, 100);

        $this->assertStringStartsWith("\x89PNG", $png);
        $image = imagecreatefromstring($png);
        $this->assertNotFalse($image);

        $width = imagesx($image);
        $height = imagesy($image);
        $leftTop = $height;
        $rightTop = $height;
        $leftCount = 0;
        $rightCount = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (! $this->isSeriesGreen(imagecolorat($image, $x, $y))) {
                    continue;
                }

                if ($x < $width * 0.45) {
                    $leftTop = min($leftTop, $y);
                    $leftCount++;
                } elseif ($x > $width * 0.55) {
                    $rightTop = min($rightTop, $y);
                    $rightCount++;
                }
            }
        }

        $this->assertGreaterThan(0, $leftCount);
        $this->assertGreaterThan(0, $rightCount);
        $this->assertLessThan($leftTop, $rightTop);
    }

    public function test_column_chart_keeps_a_zero_beside_a_positive_value(): void
    {
        $png = ReportChart::columns([
            ['label' => '۱ مهر', 'value' => 0],
            ['label' => '۲ مهر', 'value' => 4],
        ], 'تعداد تماس', 'بازه زمانی', '#4f46e5');

        $this->assertStringStartsWith("\x89PNG", $png);
        $this->assertNotSame(
            $png,
            ReportChart::columns([
                ['label' => '۱ مهر', 'value' => 4],
                ['label' => '۲ مهر', 'value' => 4],
            ], 'تعداد تماس', 'بازه زمانی', '#4f46e5'),
        );
    }

    public function test_empty_series_produces_no_image(): void
    {
        $this->assertSame('', ReportChart::line([], 'امتیاز', 'بازه', '#059669', 0, 100));
        $this->assertSame('', ReportChart::columns([], 'تعداد', 'بازه', '#4f46e5'));
        $this->assertSame('', ReportChart::stacked([], [], 'تعداد', 'بازه'));
    }

    private function isSeriesGreen(int $color): bool
    {
        $red = ($color >> 16) & 255;
        $green = ($color >> 8) & 255;
        $blue = $color & 255;

        return $green > 120 && $red < 40 && $blue > 70 && $blue < 140;
    }
}
