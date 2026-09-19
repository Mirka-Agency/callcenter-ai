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
                'audio' => 'بارگذاری دستی تماس در حال حاضر غیرفعال است. با مدیر شرکت تماس بگیرید.',
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = array_map('strtolower', $settings->allowed_extensions ?? []);

        if (! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                'audio' => 'این فرمت پشتیبانی نمی‌شود. فرمت‌های مجاز: '.implode('، ', $allowed).'.',
            ]);
        }

        if ($file->getSize() > $settings->max_file_size_bytes) {
            $maxMb = round($settings->max_file_size_bytes / 1024 / 1024, 1);

            throw ValidationException::withMessages([
                'audio' => "حجم فایل بیش از حد مجاز ({$maxMb} مگابایت) است. فایل کوچک‌تری انتخاب کنید.",
            ]);
        }

        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        $allowedMimes = self::MIME_MAP[$extension] ?? [];

        if ($allowedMimes && ! in_array($mimeType, $allowedMimes, true) && ! str_starts_with($mimeType, 'audio/')) {
            throw ValidationException::withMessages([
                'audio' => 'فایل انتخاب‌شده معتبر نیست. لطفاً یک فایل صوتی واقعی بارگذاری کنید.',
            ]);
        }

        $duration = $this->durationExtractor->extract($file->getRealPath());

        if ($duration !== null && $duration > $settings->max_duration_seconds) {
            $maxMinutes = (int) round($settings->max_duration_seconds / 60);

            throw ValidationException::withMessages([
                'audio' => "مدت تماس بیش از حد مجاز ({$maxMinutes} دقیقه) است. فایل کوتاه‌تری انتخاب کنید.",
            ]);
        }

        return [
            'extension' => $extension,
            'mime_type' => $mimeType,
            'duration_seconds' => $duration,
        ];
    }
}
