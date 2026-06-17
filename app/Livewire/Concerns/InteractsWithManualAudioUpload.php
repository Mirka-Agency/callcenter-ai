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

        $filename = method_exists($value, 'getClientOriginalName')
            ? $value->getClientOriginalName()
            : 'unknown';
        $size = method_exists($value, 'getSize')
            ? $value->getSize()
            : null;

        $this->selectedFileName = $filename !== 'unknown' ? $filename : null;
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
        $this->reset(['audio', 'title', 'customerName', 'customerPhone', 'notes', 'category', 'tags', 'conversationDate', 'employeeId']);
        $this->selectedFileName = null;
        $this->selectedFileSize = null;
        $this->audioReady = false;
        $this->showMetadata = false;
    }
}
