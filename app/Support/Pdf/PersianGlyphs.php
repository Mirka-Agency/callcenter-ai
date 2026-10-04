<?php

namespace App\Support\Pdf;

class PersianGlyphs
{
    /** @var array<string, array{0: string, 1: string, 2: string, 3: string}> */
    private const FORMS = [
        'ا' => ["\u{FE8D}", "\u{FE8E}", "\u{FE8D}", "\u{FE8E}"],
        'آ' => ["\u{FE81}", "\u{FE82}", "\u{FE81}", "\u{FE82}"],
        'أ' => ["\u{FE83}", "\u{FE84}", "\u{FE83}", "\u{FE84}"],
        'إ' => ["\u{FE87}", "\u{FE88}", "\u{FE87}", "\u{FE88}"],
        'ب' => ["\u{FE8F}", "\u{FE90}", "\u{FE91}", "\u{FE92}"],
        'پ' => ["\u{FB56}", "\u{FB57}", "\u{FB58}", "\u{FB59}"],
        'ت' => ["\u{FE95}", "\u{FE96}", "\u{FE97}", "\u{FE98}"],
        'ث' => ["\u{FE99}", "\u{FE9A}", "\u{FE9B}", "\u{FE9C}"],
        'ج' => ["\u{FE9D}", "\u{FE9E}", "\u{FE9F}", "\u{FEA0}"],
        'چ' => ["\u{FB7A}", "\u{FB7B}", "\u{FB7C}", "\u{FB7D}"],
        'ح' => ["\u{FEA1}", "\u{FEA2}", "\u{FEA3}", "\u{FEA4}"],
        'خ' => ["\u{FEA5}", "\u{FEA6}", "\u{FEA7}", "\u{FEA8}"],
        'د' => ["\u{FEA9}", "\u{FEAA}", "\u{FEA9}", "\u{FEAA}"],
        'ذ' => ["\u{FEAB}", "\u{FEAC}", "\u{FEAB}", "\u{FEAC}"],
        'ر' => ["\u{FEAD}", "\u{FEAE}", "\u{FEAD}", "\u{FEAE}"],
        'ز' => ["\u{FEAF}", "\u{FEB0}", "\u{FEAF}", "\u{FEB0}"],
        'ژ' => ["\u{FB8A}", "\u{FB8B}", "\u{FB8A}", "\u{FB8B}"],
        'س' => ["\u{FEB1}", "\u{FEB2}", "\u{FEB3}", "\u{FEB4}"],
        'ش' => ["\u{FEB5}", "\u{FEB6}", "\u{FEB7}", "\u{FEB8}"],
        'ص' => ["\u{FEB9}", "\u{FEBA}", "\u{FEBB}", "\u{FEBC}"],
        'ض' => ["\u{FEBD}", "\u{FEBE}", "\u{FEBF}", "\u{FEC0}"],
        'ط' => ["\u{FEC1}", "\u{FEC2}", "\u{FEC3}", "\u{FEC4}"],
        'ظ' => ["\u{FEC5}", "\u{FEC6}", "\u{FEC7}", "\u{FEC8}"],
        'ع' => ["\u{FEC9}", "\u{FECA}", "\u{FECB}", "\u{FECC}"],
        'غ' => ["\u{FECD}", "\u{FECE}", "\u{FECF}", "\u{FED0}"],
        'ف' => ["\u{FED1}", "\u{FED2}", "\u{FED3}", "\u{FED4}"],
        'ق' => ["\u{FED5}", "\u{FED6}", "\u{FED7}", "\u{FED8}"],
        'ک' => ["\u{FB8E}", "\u{FB8F}", "\u{FB90}", "\u{FB91}"],
        'ك' => ["\u{FED9}", "\u{FEDA}", "\u{FEDB}", "\u{FEDC}"],
        'گ' => ["\u{FB92}", "\u{FB93}", "\u{FB94}", "\u{FB95}"],
        'ل' => ["\u{FEDD}", "\u{FEDE}", "\u{FEDF}", "\u{FEE0}"],
        'م' => ["\u{FEE1}", "\u{FEE2}", "\u{FEE3}", "\u{FEE4}"],
        'ن' => ["\u{FEE5}", "\u{FEE6}", "\u{FEE7}", "\u{FEE8}"],
        'و' => ["\u{FEED}", "\u{FEEE}", "\u{FEED}", "\u{FEEE}"],
        'ه' => ["\u{FEE9}", "\u{FEEA}", "\u{FEEB}", "\u{FEEC}"],
        'ة' => ["\u{FE93}", "\u{FE94}", "\u{FE93}", "\u{FE94}"],
        'ی' => ["\u{FBFC}", "\u{FBFD}", "\u{FBFE}", "\u{FBFF}"],
        'ي' => ["\u{FEF1}", "\u{FEF2}", "\u{FEF3}", "\u{FEF4}"],
        'ئ' => ["\u{FE89}", "\u{FE8A}", "\u{FE8B}", "\u{FE8C}"],
        'ء' => ["\u{FE80}", "\u{FE80}", "\u{FE80}", "\u{FE80}"],
    ];

    /** @var array<string, array{0: string, 1: string}> */
    private const LAM_ALEF = [
        'ا' => ["\u{FEFB}", "\u{FEFC}"],
        'آ' => ["\u{FEF5}", "\u{FEF6}"],
        'أ' => ["\u{FEF7}", "\u{FEF8}"],
        'إ' => ["\u{FEF9}", "\u{FEFA}"],
    ];

    /** @var list<string> */
    private const RIGHT_ONLY = ['ا', 'آ', 'أ', 'إ', 'د', 'ذ', 'ر', 'ز', 'ژ', 'و', 'ة', 'ء'];

    public static function visual(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $logical = '';
        $count = count($chars);

        for ($index = 0; $index < $count; $index++) {
            $current = $chars[$index];
            if ($current === "\u{200C}") {
                continue;
            }

            $next = $chars[$index + 1] ?? '';
            if ($current === 'ل' && isset(self::LAM_ALEF[$next])) {
                $joinsPrevious = self::connectsForward($chars[$index - 1] ?? '');
                $logical .= self::LAM_ALEF[$next][$joinsPrevious ? 1 : 0];
                $index++;

                continue;
            }

            if (! isset(self::FORMS[$current])) {
                $logical .= $current;

                continue;
            }

            $joinsPrevious = self::connectsForward($chars[$index - 1] ?? '');
            $joinsNext = self::connectsForward($current) && self::connectsBackward($next);
            $form = match (true) {
                $joinsPrevious && $joinsNext => 3,
                $joinsPrevious => 1,
                $joinsNext => 2,
                default => 0,
            };
            $logical .= self::FORMS[$current][$form];
        }

        $visual = implode('', array_reverse(preg_split('//u', $logical, -1, PREG_SPLIT_NO_EMPTY) ?: []));

        $restored = preg_replace_callback(
            '/[0-9۰-۹]+(?:[.\/][0-9۰-۹]+)*/u',
            fn (array $match): string => implode('', array_reverse(preg_split('//u', $match[0], -1, PREG_SPLIT_NO_EMPTY) ?: [])),
            $visual,
        );

        return is_string($restored) ? $restored : $visual;
    }

    private static function connectsForward(string $char): bool
    {
        return isset(self::FORMS[$char]) && ! in_array($char, self::RIGHT_ONLY, true);
    }

    private static function connectsBackward(string $char): bool
    {
        return isset(self::FORMS[$char]) && $char !== 'ء';
    }
}
