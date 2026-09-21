<?php

namespace App\Infrastructure\Voip\Support;

/**
 * Issabel/Asterisk MixMonitor stores files under YYYY/MM/DD, but CDR webhooks
 * often send only the basename prefixed with the HTTP root:
 *   http://pbx/monitor/exten-116-...-20260920-092247-....wav
 * instead of:
 *   http://pbx/monitor/2026/09/20/exten-116-...-20260920-092247-....wav
 */
final class DatedMonitorRecordingUrl
{
    public static function normalize(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'], $parts['path'])) {
            return $url;
        }

        if (! in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return $url;
        }

        $path = (string) $parts['path'];
        $filename = basename($path);

        if (preg_match('/-(\d{8})-(\d{6})-/', $filename, $matches) !== 1) {
            return $url;
        }

        $ymd = $matches[1];
        $hms = $matches[2];

        if (! self::isValidStamp($ymd, $hms)) {
            return $url;
        }

        $year = substr($ymd, 0, 4);
        $month = substr($ymd, 4, 2);
        $day = substr($ymd, 6, 2);
        $datedSuffix = '/'.$year.'/'.$month.'/'.$day.'/'.$filename;

        if (str_ends_with($path, $datedSuffix)) {
            return $url;
        }

        $directory = rtrim(dirname($path), '/');

        if ($directory === '' || $directory === '.') {
            return $url;
        }

        return self::rebuild($parts, $directory.$datedSuffix);
    }

    /**
     * Prefer the dated MixMonitor path, then the original URL if they differ.
     *
     * @return list<string>
     */
    public static function downloadCandidates(string $url): array
    {
        $normalized = self::normalize($url) ?? $url;

        return array_values(array_unique(array_filter([$normalized, $url], fn (string $candidate) => $candidate !== '')));
    }

    public static function isDirectoryOnly(?string $url): bool
    {
        if ($url === null || $url === '') {
            return true;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $filename = basename($path);

        return $filename === '' || $filename === '/' || ! self::isAudioFilename($filename);
    }

    public static function isAudioFilename(string $name): bool
    {
        return preg_match('/\.(wav|mp3|gsm|ogg|m4a|mp4)$/i', $name) === 1;
    }

    public static function publicBase(?string $url): ?string
    {
        if ($url === null || $url === '') {
            $configured = rtrim((string) config('voip.recordings_public_base', ''), '/');

            return $configured !== '' ? $configured : null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'], $parts['path'])) {
            return null;
        }

        $path = (string) $parts['path'];
        $directory = self::isAudioFilename(basename($path))
            ? rtrim(dirname($path), '/')
            : rtrim($path, '/');

        if (preg_match('#/\d{4}/\d{2}/\d{2}$#', $directory)) {
            $directory = preg_replace('#/\d{4}/\d{2}/\d{2}$#', '', $directory) ?? $directory;
        }

        return self::rebuild($parts, $directory === '' ? '/' : $directory);
    }

    public static function listingUrl(?string $url, \DateTimeInterface $at): ?string
    {
        $base = self::publicBase($url);

        if ($base === null) {
            return null;
        }

        return rtrim($base, '/').'/'.$at->format('Y/m/d').'/';
    }

    public static function filenameForUniqueId(string $listingHtml, string $uniqueId): ?string
    {
        $uniqueId = trim($uniqueId);

        if ($uniqueId === '' || ! preg_match_all('/href="([^"]+)"/i', $listingHtml, $matches)) {
            return null;
        }

        foreach ($matches[1] as $href) {
            $name = basename(urldecode((string) $href));

            if (self::isAudioFilename($name) && str_contains($name, $uniqueId)) {
                return $name;
            }
        }

        return null;
    }

    public static function joinListingUrl(string $listingUrl, string $filename): string
    {
        return rtrim($listingUrl, '/').'/'.ltrim($filename, '/');
    }

    public static function fromSpoolPath(string $path, ?string $publicBase = null): ?string
    {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return self::normalize($path);
        }

        $base = rtrim((string) ($publicBase ?: config('voip.recordings_public_base', '')), '/');

        if ($base === '') {
            return null;
        }

        $relative = preg_replace('#^/var/spool/asterisk/monitor/?#', '', $path) ?? $path;

        return self::normalize($base.'/'.ltrim((string) $relative, '/'));
    }

    private static function isValidStamp(string $ymd, string $hms): bool
    {
        $year = (int) substr($ymd, 0, 4);
        $month = (int) substr($ymd, 4, 2);
        $day = (int) substr($ymd, 6, 2);

        if (! checkdate($month, $day, $year)) {
            return false;
        }

        $hour = (int) substr($hms, 0, 2);
        $minute = (int) substr($hms, 2, 2);
        $second = (int) substr($hms, 4, 2);

        return $hour <= 23 && $minute <= 59 && $second <= 59;
    }

    /** @param array<string, mixed> $parts */
    private static function rebuild(array $parts, string $path): string
    {
        $url = strtolower((string) $parts['scheme']).'://';

        if (isset($parts['user'])) {
            $url .= $parts['user'];
            if (isset($parts['pass'])) {
                $url .= ':'.$parts['pass'];
            }
            $url .= '@';
        }

        $url .= $parts['host'];

        if (isset($parts['port'])) {
            $url .= ':'.$parts['port'];
        }

        $url .= $path;

        if (isset($parts['query'])) {
            $url .= '?'.$parts['query'];
        }

        if (isset($parts['fragment'])) {
            $url .= '#'.$parts['fragment'];
        }

        return $url;
    }
}
