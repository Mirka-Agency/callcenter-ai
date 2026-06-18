<?php

namespace App\Livewire\Employee\Uploads;

use App\Domain\Call\Enums\ConversationSource;
use App\Livewire\Concerns\ResolvesRecordingPlayback;
use App\Models\Call;
use App\Services\EmployeeContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employee')]
#[Title('جزئیات آپلود')]
class Show extends Component
{
    use ResolvesRecordingPlayback;

    public Call $upload;

    public function mount(Call $upload): void
    {
        abort_unless($upload->source === ConversationSource::ManualUpload, 404);
        abort_unless($upload->organization_id === EmployeeContext::organizationId(), 404);
        abort_unless(
            $upload->organization_user_id === EmployeeContext::membership()->id
            || $upload->uploader_id === auth()->id(),
            404,
        );

        $this->upload = $upload->load(['latestAnalysis', 'recording', 'processingJob']);
    }

    #[On('processing-job-updated')]
    public function onProcessingJobUpdated(array $job = []): void
    {
        if (($job['call_id'] ?? $job['job']['call_id'] ?? null) === $this->upload->id) {
            $this->upload->refresh()->load(['latestAnalysis', 'recording', 'processingJob']);
        }
    }

    public function recordingPlayback(): array
    {
        return $this->recordingPlaybackState($this->upload->recording);
    }

    public function render()
    {
        $playback = $this->stableRecordingPlaybackState($this->upload->recording);

        return view('livewire.employee.uploads.show', [
            'recordingUrl' => $playback['url'],
            'recordingExpired' => $playback['expired'],
            'analysis' => $this->upload->latestAnalysis,
            'call' => $this->upload,
            'organizationId' => EmployeeContext::organizationId(),
            'queueUrl' => $this->upload->processingJob
                ? route('employee.processing-queue.show', $this->upload->processingJob)
                : route('employee.processing-queue.index'),
        ]);
    }
}
