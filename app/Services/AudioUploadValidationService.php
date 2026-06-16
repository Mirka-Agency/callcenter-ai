<?php

namespace App\Services;

use App\Models\AudioUploadSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AudioUploadValidationService
{
    private const MIME_MAP = [
        'mp3' => ['audio/mpeg', 'audio/mp3'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/wave'],
        'm4a' => ['audio/mp4', 'audio/x-m4a', 'audio/m4a'],
        'ogg' => ['audio/ogg', 'application/ogg'],
        'flac' => ['audio/flac', 'audio/x-flac'],
    ];

    public function __construct(
        private AudioDurationExtractor $durationExtractor,
    ) {}

    /**
     * @return array{extension: string, mime_type: string, duration_seconds: ?int}
     */
    public function validate(UploadedFile $file): array
    {
        $settings = AudioUploadSettings::current();

        if (! $settings->is_active) {
            throw ValidationException::withMessages([
                'audio' => 'آپلود دستی فایل صوتی در حال حاضر غیرفعال است.',
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = array_map('strtolower', $settings->allowed_extensions ?? []);

        if (! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                'audio' => 'فرمت فایل پشتیبانی نمی‌شود. فرمت‌های مجاز: '.implode('، ', $allowed).'.',
            ]);
        }

        if ($file->getSize() > $settings->max_file_size_bytes) {
            $maxMb = round($settings->max_file_size_bytes / 1024 / 1024, 1);

            throw ValidationException::withMessages([
                'audio' => "حجم فایل از حداکثر مجاز {$maxMb} مگابایت بیشتر است.",
            ]);
        }

        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        $allowedMimes = self::MIME_MAP[$extension] ?? [];

        if ($allowedMimes && ! in_array($mimeType, $allowedMimes, true) && ! str_starts_with($mimeType, 'audio/')) {
            throw ValidationException::withMessages([
                'audio' => 'فایل آپلودشده یک فایل صوتی معتبر به نظر نمی‌رسد.',
            ]);
        }

        $duration = $this->durationExtractor->extract($file->getRealPath());

        if ($duration !== null && $duration > $settings->max_duration_seconds) {
            $maxMinutes = (int) round($settings->max_duration_seconds / 60);

            throw ValidationException::withMessages([
                'audio' => "مدت فایل صوتی از حداکثر مجاز {$maxMinutes} دقیقه بیشتر است.",
            ]);
        }

        return [
            'extension' => $extension,
            'mime_type' => $mimeType,
            'duration_seconds' => $duration,
        ];
    }

    /**
     * @return array{extension: string, mime_type: string, duration_seconds: ?int}
     */
    public function validatePath(string $absolutePath, string $originalFilename): array
    {
        if (! is_file($absolutePath)) {
            throw ValidationException::withMessages([
                'audio' => 'فایل نمونه یافت نشد.',
            ]);
        }

        $settings = AudioUploadSettings::current();

        if (! $settings->is_active) {
            throw ValidationException::withMessages([
                'audio' => 'آپلود دستی فایل صوتی در حال حاضر غیرفعال است.',
            ]);
        }

        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $allowed = array_map('strtolower', $settings->allowed_extensions ?? []);

        if (! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                'audio' => 'فرمت فایل پشتیبانی نمی‌شود. فرمت‌های مجاز: '.implode('، ', $allowed).'.',
            ]);
        }

        $fileSize = filesize($absolutePath) ?: 0;

        if ($fileSize > $settings->max_file_size_bytes) {
            $maxMb = round($settings->max_file_size_bytes / 1024 / 1024, 1);

            throw ValidationException::withMessages([
                'audio' => "حجم فایل از حداکثر مجاز {$maxMb} مگابایت بیشتر است.",
            ]);
        }

        $mimeType = mime_content_type($absolutePath) ?: 'application/octet-stream';
        $allowedMimes = self::MIME_MAP[$extension] ?? [];

        if ($allowedMimes && ! in_array($mimeType, $allowedMimes, true) && ! str_starts_with($mimeType, 'audio/')) {
            throw ValidationException::withMessages([
                'audio' => 'فایل نمونه یک فایل صوتی معتبر به نظر نمی‌رسد.',
            ]);
        }

        $duration = $this->durationExtractor->extract($absolutePath);

        if ($duration !== null && $duration > $settings->max_duration_seconds) {
            $maxMinutes = (int) round($settings->max_duration_seconds / 60);

            throw ValidationException::withMessages([
                'audio' => "مدت فایل صوتی از حداکثر مجاز {$maxMinutes} دقیقه بیشتر است.",
            ]);
        }

        return [
            'extension' => $extension,
            'mime_type' => $mimeType,
            'duration_seconds' => $duration,
        ];
    }
}
