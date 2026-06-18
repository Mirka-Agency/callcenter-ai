<?php

namespace App\Services;

use App\Domain\Recording\Exceptions\RecordingNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RecordingStorage
{
    public function disk(): string
    {
        return (string) config('recordings.disk', 'local');
    }

    public function putFromLocalPath(string $sourcePath, string $path, ?string $mimeType = null): void
    {
        $stream = fopen($sourcePath, 'rb');

        if ($stream === false) {
            throw RecordingNotFoundException::storageWriteFailed($path, $this->disk());
        }

        try {
            $this->putStream($path, $stream, $mimeType, filesize($sourcePath) ?: null);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     */
    public function putStream(string $path, $stream, ?string $mimeType = null, ?int $bytes = null): void
    {
        $disk = $this->disk();
        $options = ['visibility' => 'private'];

        if ($mimeType) {
            $options['ContentType'] = $mimeType;
            $options['mimetype'] = $mimeType;
        }

        $stored = Storage::disk($disk)->put($path, $stream, $options);

        if ($stored === false || ! Storage::disk($disk)->exists($path)) {
            throw RecordingNotFoundException::storageWriteFailed($path, $disk);
        }

        Log::info('Recording stored', [
            'disk' => $disk,
            'path' => $path,
            'bytes' => $bytes,
        ]);
    }

    public function put(string $path, string $contents, ?string $mimeType = null): void
    {
        $this->putStream($path, $contents, $mimeType, strlen($contents));
    }

    public function exists(string $path, ?string $preferredDisk = null): bool
    {
        return $this->locate($path, $preferredDisk) !== null;
    }

    public function assertExists(string $path, ?string $preferredDisk = null): string
    {
        $located = $this->locate($path, $preferredDisk);

        if ($located === null) {
            throw RecordingNotFoundException::forPath($path, $this->disksToTry($preferredDisk));
        }

        return $located;
    }

    /**
     * @return array{disk: string, content: string, format: string}
     */
    public function readForAnalysis(string $path, ?string $preferredDisk = null): array
    {
        $disk = $this->assertExists($path, $preferredDisk);
        $content = Storage::disk($disk)->get($path);

        if ($content === '' || $content === false) {
            throw RecordingNotFoundException::forPath($path, [$disk]);
        }

        return [
            'disk' => $disk,
            'content' => $content,
            'format' => strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'mp3',
        ];
    }

    public function get(string $path, ?string $preferredDisk = null): string
    {
        $disk = $this->assertExists($path, $preferredDisk);

        return Storage::disk($disk)->get($path);
    }

    public function resolveDisk(string $path, ?string $preferredDisk = null): ?string
    {
        return $this->locate($path, $preferredDisk);
    }

    private function locate(string $path, ?string $preferredDisk = null): ?string
    {
        foreach ($this->disksToTry($preferredDisk) as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function disksToTry(?string $preferredDisk = null): array
    {
        return array_values(array_unique(array_filter([
            $preferredDisk,
            $this->disk(),
            's3',
            'local',
        ])));
    }
}
