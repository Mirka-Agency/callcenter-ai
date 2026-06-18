<?php

namespace App\Livewire\Employer\ManualAnalyses;

use App\Domain\Call\Enums\ConversationSource;
use App\Livewire\Concerns\ResolvesRecordingPlayback;
use App\Models\Call;
use App\Services\EmployerContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('تحلیل دستی')]
class Show extends Component
{
    use ResolvesRecordingPlayback;

    public Call $upload;

    public function mount(Call $upload): void
    {
        abort_unless($upload->source === ConversationSource::ManualUpload, 404);
        abort_unless($upload->organization_id === EmployerContext::organizationId(), 404);

        $this->upload = $upload->load(['employee', 'uploader', 'latestAnalysis', 'recording', 'processingJob']);
    }

    #[On('processing-job-updated')]
    public function onProcessingJobUpdated(array $job = []): void
    {
        if (($job['call_id'] ?? $job['job']['call_id'] ?? null) === $this->upload->id) {
            $this->upload->refresh()->load(['employee', 'uploader', 'latestAnalysis', 'recording', 'processingJob']);
        }
    }

    public function recordingPlayback(): array
    {
        return $this->recordingPlaybackState($this->upload->recording);
    }

    public function render()
    {
        $playback = $this->stableRecordingPlaybackState($this->upload->recording);

        return view('livewire.employer.manual-analyses.show', [
            'recordingUrl' => $playback['url'],
            'recordingExpired' => $playback['expired'],
            'analysis' => $this->upload->latestAnalysis,
            'call' => $this->upload,
            'organizationId' => EmployerContext::organizationId(),
            'queueUrl' => $this->upload->processingJob
                ? route('employer.processing-queue.show', $this->upload->processingJob)
                : route('employer.processing-queue.index'),
        ]);
    }
}
