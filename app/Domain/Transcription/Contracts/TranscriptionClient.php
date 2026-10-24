<?php

namespace App\Domain\Transcription\Contracts;

use App\Domain\Transcription\DTOs\TranscriptionContextData;

interface TranscriptionClient
{
    public function validateConfiguration(): void;

    /** @param resource $audio */
    public function upload($audio, string $filename): string;

    public function submit(string $fileId, string $model, string $reference, ?TranscriptionContextData $context = null): string;

    /** @return array<string, mixed> */
    public function status(string $transcriptionId): array;

    /** @return list<array<string, mixed>> */
    public function tokens(string $transcriptionId): array;

    public function deleteTranscription(string $transcriptionId): void;

    public function deleteFile(string $fileId): void;
}
