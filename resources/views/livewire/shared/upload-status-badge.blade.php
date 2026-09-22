@php
    $processingJob = $call?->processingJob ?? null;

    if ($processingJob) {
        $label = $processingJob->stage->label();
        $badge = match ($processingJob->status) {
            \App\Domain\Processing\Enums\ProcessingJobStatus::Completed => 'completed',
            \App\Domain\Processing\Enums\ProcessingJobStatus::Failed => 'failed',
            \App\Domain\Processing\Enums\ProcessingJobStatus::Cancelled => 'failed',
            default => 'processing',
        };
    } else {
        $status = $call?->processing_status?->value ?? 'pending';
        $label = match ($status) {
            'analyzed' => $analysis ? 'تحلیل‌شده' : 'تکمیل‌شده',
            'analyzing', 'downloading' => 'در حال پردازش',
            'failed' => 'ناموفق',
            'skipped' => 'رد شده',
            default => $analysis ? 'تحلیل‌شده' : 'در انتظار',
        };
        $badge = match ($status) {
            'analyzed' => 'completed',
            'analyzing', 'downloading' => 'processing',
            'failed' => 'failed',
            'skipped' => 'pending',
            default => $analysis ? 'completed' : 'pending',
        };
    }
@endphp

<span @class([
    'saas-badge-success' => $badge === 'completed',
    'saas-badge-warning' => $badge === 'processing',
    'saas-badge-danger' => $badge === 'failed',
    'rounded-md px-3 py-1 text-xs font-medium',
    'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => $badge === 'pending',
])>{{ $label }}</span>
