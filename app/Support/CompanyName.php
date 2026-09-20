<?php

namespace App\Support;

class CompanyName
{
    /** @var list<string> */
    private const PREFIXES = [
        'شرکت سهامی خاص',
        'شرکت سهامی عام',
        'شرکت با مسئولیت محدود',
        'سهامی خاص',
        'سهامی عام',
        'با مسئولیت محدود',
        'شرکت',
        'کمپانی',
        'کمپاني',
        'هلدینگ',
        'گروه',
        'موسسه',
        'مؤسسه',
        'فروشگاه',
        'تعاونی',
        'کارخانه',
        'کارگاه',
    ];

    /** @var list<string> */
    private const SUFFIXES = [
        'سهامی خاص',
        'سهامی عام',
        'با مسئولیت محدود',
        '(سهامی خاص)',
        '(سهامی عام)',
        'ltd',
        'llc',
        'inc',
        'gmbh',
        'co',
        'plc',
    ];

    public static function display(string $name): string
    {
        $name = self::normalizeCharacters(trim($name));
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $name = self::trimPunctuation($name);

        while (preg_match('/^(شرکت)(?:\s+\1)+/u', $name) === 1) {
            $name = preg_replace('/^(شرکت)(?:\s+\1)+/u', '$1', $name) ?? $name;
            $name = trim($name);
        }

        return $name;
    }

    public static function key(string $name): string
    {
        $name = mb_strtolower(self::display($name), 'UTF-8');
        $name = str_replace(['‌', 'ـ'], ' ', $name);
        $name = str_replace(['آ', 'أ', 'إ', 'ٱ'], 'ا', $name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $name = trim($name);

        $name = self::stripAffixes($name);

        return $name;
    }

    public static function matches(string $left, string $right): bool
    {
        $leftKey = self::key($left);
        $rightKey = self::key($right);

        if ($leftKey === '' || $rightKey === '') {
            return false;
        }

        if ($leftKey === $rightKey) {
            return true;
        }

        $shorter = mb_strlen($leftKey) <= mb_strlen($rightKey) ? $leftKey : $rightKey;
        $longer = $shorter === $leftKey ? $rightKey : $leftKey;

        if (mb_strlen($shorter) < 3) {
            return false;
        }

        return (bool) preg_match('/(?<!\p{L})'.preg_quote($shorter, '/').'(?!\p{L})/u', $longer);
    }

    public static function isOwnOrganization(string $companyName, string $organizationTitle): bool
    {
        return self::matches($companyName, $organizationTitle);
    }

    public static function preferDisplay(string $current, string $incoming): string
    {
        $currentDisplay = self::display($current);
        $incomingDisplay = self::display($incoming);

        if ($incomingDisplay === '') {
            return $currentDisplay;
        }

        if ($currentDisplay === '' || $currentDisplay === $incomingDisplay) {
            return $incomingDisplay;
        }

        $currentHasArabic = (bool) preg_match('/[يىك]/u', $current);
        $incomingHasArabic = (bool) preg_match('/[يىك]/u', $incoming);

        if ($currentHasArabic && ! $incomingHasArabic) {
            return $incomingDisplay;
        }

        if (! $currentHasArabic && $incomingHasArabic) {
            return $currentDisplay;
        }

        if (self::key($currentDisplay) === self::key($incomingDisplay)) {
            return $incomingDisplay;
        }

        return $incomingDisplay;
    }

    /** @return list<string> */
    public static function identityKeys(string $name): array
    {
        $display = self::display($name);
        $key = self::key($display);
        $legacy = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? $name), 'UTF-8');

        $variants = [
            $key,
            mb_strtolower($display, 'UTF-8'),
            $legacy,
        ];

        if ($key !== '') {
            foreach (['شرکت', 'هلدینگ', 'گروه', 'موسسه', 'مؤسسه', 'فروشگاه'] as $prefix) {
                $variants[] = mb_strtolower($prefix.' '.$key, 'UTF-8');
                $variants[] = mb_strtolower($prefix.' '.$display, 'UTF-8');
            }
        }

        return self::uniqueNonEmpty($variants);
    }

    /** @return list<string> */
    public static function identityNames(string $name): array
    {
        $display = self::display($name);
        $key = self::key($display);
        $names = [$name, $display, trim($name)];

        if ($key !== '') {
            $names[] = $key;
            $names[] = mb_convert_case($key, MB_CASE_TITLE, 'UTF-8');

            foreach (['شرکت', 'هلدینگ', 'گروه', 'موسسه', 'مؤسسه', 'فروشگاه'] as $prefix) {
                $names[] = $prefix.' '.$key;
                $names[] = $prefix.' '.$display;
            }
        }

        return self::uniqueNonEmpty($names);
    }

    private static function normalizeCharacters(string $name): string
    {
        $replacements = [
            'ي' => 'ی',
            'ى' => 'ی',
            'ك' => 'ک',
            'ۀ' => 'ه',
            'ة' => 'ه',
            'ؤ' => 'و',
            'ئ' => 'ی',
            'أ' => 'ا',
            'إ' => 'ا',
            'ٱ' => 'ا',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $name);
    }

    private static function stripAffixes(string $name): string
    {
        $changed = true;

        while ($changed && $name !== '') {
            $changed = false;

            foreach (self::PREFIXES as $prefix) {
                $prefix = mb_strtolower($prefix, 'UTF-8');
                if (str_starts_with($name, $prefix.' ') || $name === $prefix) {
                    $name = trim(mb_substr($name, mb_strlen($prefix)));
                    $changed = true;
                    break;
                }
            }

            foreach (self::SUFFIXES as $suffix) {
                $suffix = mb_strtolower($suffix, 'UTF-8');
                if (str_ends_with($name, ' '.$suffix) || $name === $suffix) {
                    $name = trim(mb_substr($name, 0, mb_strlen($name) - mb_strlen($suffix)));
                    $changed = true;
                    break;
                }
            }
        }

        return self::trimPunctuation($name);
    }

    private static function trimPunctuation(string $name): string
    {
        $name = preg_replace('/^[\s،,.؛;:\-]+|[\s،,.؛;:\-]+$/u', '', $name) ?? $name;

        return trim($name);
    }

    /** @param  list<string>  $values
     * @return list<string>
     */
    private static function uniqueNonEmpty(array $values): array
    {
        $unique = [];

        foreach ($values as $value) {
            $value = trim($value);
            if ($value === '' || in_array($value, $unique, true)) {
                continue;
            }

            $unique[] = $value;
        }

        return $unique;
    }
}
