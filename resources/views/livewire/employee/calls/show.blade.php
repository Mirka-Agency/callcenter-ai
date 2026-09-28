@php
    $visibilityMode = \App\Support\CustomerAnalysisVisibility::mode(
        \App\Services\EmployeeContext::membership()->id,
        $analysis,
        false,
    );
@endphp

@include('livewire.shared.analysis-detail', [
    'analysis' => $analysis,
    'recordingUrl' => $recordingUrl,
    'recordingExpired' => $recordingExpired ?? false,
    'deletedRecordingUrl' => $deletedRecordingUrl ?? null,
    'visibilityMode' => $visibilityMode,
])
