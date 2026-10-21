<?php

namespace App\Application\Llm\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Splits a long recording into short pieces so transcription does not resend
 * the whole file when the model stops early. Returns null when the file should
 * be sent as one piece.
 */
class AudioSegmenter
{
    /** @return list<string>|null */
    public function segments(string $bytes, string $format): ?array
    {
        if ($bytes === '' || ! $this->enabled()) {
            return null;
        }

        $format = $this->safeFormat($format);
        $directory = sys_get_temp_dir().'/avayar-audio-'.bin2hex(random_bytes(8));

        if (! mkdir($directory) && ! is_dir($directory)) {
            return null;
        }

        $source = $directory.'/source.'.$format;

        try {
            if (file_put_contents($source, $bytes) === false) {
                return null;
            }

            $duration = $this->duration($source);
            $threshold = (int) config('llm.transcription.segment_when_longer_than', 180);

            if ($duration === null || $duration <= $threshold) {
                return null;
            }

            $length = max(30, (int) config('llm.transcription.segment_seconds', 150));
            $overlap = max(0, (int) config('llm.transcription.segment_overlap_seconds', 5));
            $step = max(1, $length - $overlap);
            $pieces = [];
            $index = 0;

            for ($start = 0.0; $start < $duration && $index < 24; $start += $step) {
                $target = $directory.'/part-'.$index.'.mp3';
                $remaining = $duration - $start;
                $window = min((float) $length, $remaining);

                if ($window < 0.5) {
                    break;
                }

                if (! $this->cut($source, $target, $start, $window)) {
                    return null;
                }

                $piece = file_get_contents($target);
                if (! is_string($piece) || $piece === '') {
                    return null;
                }

                $pieces[] = $piece;
                $index++;
            }

            return count($pieces) > 1 ? $pieces : null;
        } catch (\Throwable $e) {
            Log::warning('Audio segmentation skipped', ['error' => $e->getMessage()]);

            return null;
        } finally {
            $this->deleteDirectory($directory);
        }
    }

    private function enabled(): bool
    {
        return (bool) config('llm.transcription.segment_long_audio', true);
    }

    private function duration(string $path): ?float
    {
        $process = new Process([
            'ffprobe',
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'csv=p=0',
            $path,
        ]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $duration = (float) trim($process->getOutput());

        return $duration > 0 ? $duration : null;
    }

    private function cut(string $source, string $target, float $start, float $length): bool
    {
        $process = new Process([
            'ffmpeg',
            '-y',
            '-i', $source,
            '-ss', (string) $start,
            '-t', (string) $length,
            '-ac', '1',
            '-ar', '16000',
            '-b:a', '32k',
            $target,
        ]);
        $process->setTimeout(120);
        $process->run();

        return $process->isSuccessful() && is_file($target);
    }

    private function safeFormat(string $format): string
    {
        $format = strtolower(trim($format));

        return in_array($format, ['mp3', 'wav', 'ogg', 'm4a', 'mp4', 'webm'], true) ? $format : 'mp3';
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = scandir($directory);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $directory.'/'.$file;
            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
