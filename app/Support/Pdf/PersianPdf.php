<?php

namespace App\Support\Pdf;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class PersianPdf
{
    /**
     * @param  array<string, string>  $images
     */
    public static function render(string $html, array $images = [], string $format = 'A4-L', ?string $title = null): string
    {
        $directory = storage_path('app/mpdf');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $fontDir = resource_path('fonts');
        $config = (new ConfigVariables)->getDefaults();
        $fontData = (new FontVariables)->getDefaults();

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $format,
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 16,
            'tempDir' => $directory,
            'fontDir' => array_merge($config['fontDir'], [$fontDir]),
            'fontdata' => $fontData['fontdata'] + [
                'vazirmatn' => [
                    'R' => 'Vazirmatn-Regular.ttf',
                    'B' => 'Vazirmatn-Bold.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'default_font' => 'vazirmatn',
            'directionality' => 'rtl',
        ]);
        $pdf->img_dpi = 150;

        $pdf->SetDirectionality('rtl');
        if ($title !== null) {
            $pdf->SetTitle($title);
        }

        $pdf->SetHTMLFooter('
            <table width="100%" style="border-top: 1px solid #e4e4e7; color: #71717a; font-size: 8pt;">
                <tr>
                    <td>'.e($title ?? '').'</td>
                    <td style="text-align: left;">صفحه {PAGENO} از {nbpg}</td>
                </tr>
            </table>
        ');

        foreach ($images as $name => $contents) {
            if ($contents !== '') {
                $pdf->imageVars[$name] = $contents;
            }
        }

        $pdf->WriteHTML($html);

        return $pdf->Output('', Destination::STRING_RETURN);
    }
}
