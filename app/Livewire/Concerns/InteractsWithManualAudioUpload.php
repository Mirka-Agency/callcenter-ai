<?php

namespace App\Livewire\Concerns;

trait InteractsWithManualAudioUpload
{
    public ?string $selectedFileName = null;

    public ?int $selectedFileSize = null;

    public string $uploadZoneState = 'idle';

    public bool $showMetadata = false;

    public bool $audioReady = false;

    public function updatedAudio(mixed $value): void
    {
        if ($value === null) {
            $this->selectedFileName = null;
            $this->selectedFileSize = null;
            $this->audioReady = false;

            return;
        }

        $filename = $this->temporaryUploadFilename($value);
        $size = $this->temporaryUploadSize($value);

        $this->selectedFileName = $filename;
        $this->selectedFileSize = $size;
        $this->audioReady = true;
        $this->uploadZoneState = 'idle';
        $this->resetErrorBag('audio');
    }

    public function removeAudio(): void
    {
        $this->reset('audio');
        $this->selectedFileName = null;
        $this->selectedFileSize = null;
        $this->audioReady = false;
        $this->uploadZoneState = 'idle';
        $this->resetErrorBag('audio');
    }

    protected function markUploadZoneSuccess(): void
    {
        $this->uploadZoneState = 'success';
        $this->selectedFileName = null;
        $this->selectedFileSize = null;
    }

    protected function resetUploadFormFields(): void
    {
        $fields = ['audio', 'title', 'customerName', 'customerPhone', 'notes', 'category', 'tags', 'conversationDate'];

        if (property_exists($this, 'employeeId')) {
            $fields[] = 'employeeId';
        }

        $this->reset($fields);
        $this->selectedFileName = null;
        $this->selectedFileSize = null;
        $this->audioReady = false;
        $this->showMetadata = false;
    }

    private function temporaryUploadFilename(mixed $value): ?string
    {
        if (! is_object($value) || ! method_exists($value, 'getClientOriginalName')) {
            return null;
        }

        $filename = $value->getClientOriginalName();

        return filled($filename) && $filename !== 'unknown' ? $filename : null;
    }

    private function temporaryUploadSize(mixed $value): ?int
    {
        if (! is_object($value)) {
            return null;
        }

        if (method_exists($value, 'metaFileData')) {
            $size = $value->metaFileData()['size'] ?? null;

            if ($size !== null) {
                return (int) $size;
            }
        }

        try {
            return method_exists($value, 'getSize') ? (int) $value->getSize() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
