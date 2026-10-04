<?php

namespace App\Application\Llm\Services;

use App\Domain\Llm\DTOs\LlmConnectionConfig;
use App\Services\RecordingStorage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecordingTranscriber
{
    /** OpenAI-compatible speech-to-text accepts up to 25MB. */
    private const MAX_BYTES = 26_214_400;

    public function __construct(private RecordingStorage $recordings) {}

    /**
     * Stream the recording to speech-to-text and return the transcript.
     * Returns null when transcription is unavailable so analysis can continue from the audio file.
     */
    public function transcribe(
        int $callId,
        ?string $storagePath,
        ?string $storageDisk,
        ?string $sourceUrl,
        ?string $mimeType,
        ?int $fileSizeBytes,
        LlmConnectionConfig $config,
    ): ?string {
        if (! filled($config->credentials->apiKey)) {
            return null;
        }

        if (! filled($storagePath) && ! filled($sourceUrl)) {
            return null;
        }

        try {
            if ($fileSizeBytes !== null && $fileSizeBytes > self::MAX_BYTES) {
                throw new \RuntimeException('حجم فایل صوتی برای تبدیل به متن بیش از حد مجاز است.');
            }

            $cacheKey = 'recording-transcript:'.$callId.':'.($fileSizeBytes ?? 0).':'.md5((string) ($storagePath ?: $sourceUrl));
            $cached = Cache::get($cacheKey);

            if (is_string($cached) && trim($cached) !== '') {
                return $cached;
            }

            $transcript = filled($storagePath)
                ? $this->transcribeStoredFile($storagePath, $storageDisk, $mimeType, $config)
                : $this->transcribeRemoteFile((string) $sourceUrl, $mimeType, $config);

            $transcript = trim($transcript);

            if ($transcript === '') {
                throw new \RuntimeException('تبدیل گفتار به متن نتیجه‌ای نداد.');
            }

            Cache::put($cacheKey, $transcript, now()->addHours(6));

            return $transcript;
        } catch (\Throwable $e) {
            Log::warning('Speech-to-text skipped; analysis continues with the recording', [
                'call_id' => $callId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function transcribeStoredFile(
        string $path,
        ?string $disk,
        ?string $mimeType,
        LlmConnectionConfig $config,
    ): string {
        $opened = $this->recordings->readStream($path, $disk);
        $format = $opened['format'];
        $temporary = $this->copyToTemporaryFile($opened['stream'], $format);

        try {
            return $this->postFile(
                $temporary,
                'recording.'.$format,
                $mimeType ?? $this->mimeType($format),
                $config,
            );
        } finally {
            @unlink($temporary);
        }
    }

    private function transcribeRemoteFile(string $url, ?string $mimeType, LlmConnectionConfig $config): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'call-audio-');

        if ($temporary === false) {
            throw new \RuntimeException('فایل موقت برای تبدیل گفتار به متن ساخته نشد.');
        }

        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) ?: 'mp3';
        $target = $temporary.'.'.$extension;
        rename($temporary, $target);

        try {
            $download = Http::timeout(120)->sink($target)->get($url);

            if (! $download->successful()) {
                throw new \RuntimeException('دانلود فایل صوتی برای تبدیل به متن ناموفق بود.');
            }

            $size = filesize($target);

            if ($size !== false && $size > self::MAX_BYTES) {
                throw new \RuntimeException('حجم فایل صوتی برای تبدیل به متن بیش از حد مجاز است.');
            }

            return $this->postFile(
                $target,
                'recording.'.$extension,
                $mimeType ?? $this->mimeType($extension),
                $config,
            );
        } finally {
            @unlink($target);
        }
    }

    /** @param  resource  $source */
    private function copyToTemporaryFile($source, string $extension): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'call-audio-');

        if ($temporary === false) {
            fclose($source);

            throw new \RuntimeException('فایل موقت برای تبدیل گفتار به متن ساخته نشد.');
        }

        $target = $temporary.'.'.$extension;
        rename($temporary, $target);
        $output = fopen($target, 'wb');

        if ($output === false) {
            fclose($source);
            @unlink($target);

            throw new \RuntimeException('فایل موقت برای تبدیل گفتار به متن ساخته نشد.');
        }

        try {
            stream_copy_to_stream($source, $output);
        } finally {
            fclose($source);
            fclose($output);
        }

        return $target;
    }

    private function postFile(string $path, string $filename, string $mimeType, LlmConnectionConfig $config): string
    {
        $model = $config->settings->transcriptionModel
            ?: (string) config('llm.transcription_model', 'whisper-1');
        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new \RuntimeException('خواندن فایل صوتی برای تبدیل به متن ناموفق بود.');
        }

        try {
            $response = Http::withToken((string) $config->credentials->apiKey)
                ->timeout(180)
                ->attach('file', $stream, $filename, ['Content-Type' => $mimeType])
                ->post($this->endpoint($config), [
                    'model' => $model,
                    'language' => 'fa',
                    'response_format' => 'json',
                ]);
        } finally {
            fclose($stream);
        }

        if (! $response->successful()) {
            throw new \RuntimeException('تبدیل گفتار به متن ناموفق بود (HTTP '.$response->status().'): '.$response->body());
        }

        $body = $response->json();

        return is_array($body) ? (string) ($body['text'] ?? $body['transcript'] ?? '') : '';
    }

    private function endpoint(LlmConnectionConfig $config): string
    {
        $base = rtrim((string) ($config->credentials->baseUrl ?: 'https://api.openai.com/v1'), '/');

        if (str_contains($base, 'generativelanguage.googleapis.com')) {
            throw new \RuntimeException('آدرس API فعلی تبدیل گفتار به متن را پشتیبانی نمی‌کند. آدرس باید با سرویس OpenAI یا سازگار با آن باشد.');
        }

        if (str_ends_with($base, '/audio/transcriptions')) {
            return $base;
        }

        return $base.'/audio/transcriptions';
    }

    private function mimeType(string $format): string
    {
        return match (strtolower($format)) {
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'webm' => 'audio/webm',
            'm4a', 'mp4' => 'audio/mp4',
            default => 'audio/mpeg',
        };
    }
}
