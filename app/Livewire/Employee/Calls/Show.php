<?php

namespace App\Livewire\Employee\Calls;

use App\Livewire\Concerns\ResolvesRecordingPlayback;
use App\Models\ConversationAnalysis;
use App\Services\EmployeeContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employee')]
#[Title('تحلیل تماس')]
class Show extends Component
{
    use ResolvesRecordingPlayback;

    public ConversationAnalysis $analysis;

    public function mount(ConversationAnalysis $analysis): void
    {
        abort_unless(
            $analysis->organization_id === EmployeeContext::membership()->organization_id,
            404,
        );

        $this->analysis = $analysis->load([
            'employee',
            'call.recording',
            'call.processingJob',
            'call.customer.company',
            'callLog',
            'crmSyncs.crmConnection.provider',
        ]);
    }

    public function recordingPlayback(): array
    {
        return $this->recordingPlaybackState(
            $this->analysis->call?->recording,
            $this->analysis->callLog?->recording_url,
        );
    }

    public function render()
    {
        $playback = $this->recordingPlayback();

        return view('livewire.employee.calls.show', [
            'recordingUrl' => $playback['url'],
            'recordingExpired' => $playback['expired'],
        ]);
    }
}
