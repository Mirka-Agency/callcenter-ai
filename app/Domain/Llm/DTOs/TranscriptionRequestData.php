<?php

namespace App\Domain\Llm\DTOs;

readonly class TranscriptionRequestData
{
    public function __construct(
        public int $callId,
        public ?string $storagePath = null,
        public ?string $storageDisk = null,
        public ?string $recordingUrl = null,
        public ?string $mimeType = null,
        public ?string $model = null,
        public ?string $fallbackModel = null,
        public ?PromptContextData $context = null,
        public bool $sendAudioFile = true,
        public ?string $playbackUrl = null,
        public string $language = 'fa',
    ) {}

    public function asAudioRequest(): AudioAnalysisRequestData
    {
        return new AudioAnalysisRequestData(
            callId: $this->callId,
            storagePath: $this->storagePath,
            storageDisk: $this->storageDisk,
            recordingUrl: $this->recordingUrl,
            mimeType: $this->mimeType,
            model: $this->fallbackModel ?? $this->model,
            context: $this->context,
            sendAudioFile: $this->sendAudioFile,
            playbackUrl: $this->playbackUrl,
        );
    }
}
