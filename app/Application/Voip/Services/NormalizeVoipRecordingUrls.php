<?php

namespace App\Application\Voip\Services;

use App\Application\Intelligence\Services\CallAnalysisQueueService;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Infrastructure\Voip\Support\DatedMonitorRecordingUrl;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\VoipCallLog;

class NormalizeVoipRecordingUrls
{
    public function __construct(
        private CallAnalysisQueueService $queue,
    ) {}

    /**
     * @return array{logs: int, recordings: int, requeued: int}
     */
    public function run(bool $requeueFailedDownloads = true, bool $dryRun = false): array
    {
        $changedLogIds = [];
        $logsUpdated = 0;
        $recordingsUpdated = 0;

        VoipCallLog::query()
            ->whereNotNull('recording_url')
            ->orderBy('id')
            ->chunkById(200, function ($logs) use ($dryRun, &$changedLogIds, &$logsUpdated): void {
                foreach ($logs as $log) {
                    $fixed = DatedMonitorRecordingUrl::normalize($log->recording_url);

                    if ($fixed === null || $fixed === $log->recording_url) {
                        continue;
                    }

                    $logsUpdated++;
                    $changedLogIds[] = $log->id;

                    if (! $dryRun) {
                        $log->update(['recording_url' => $fixed]);
                    }
                }
            });

        CallRecording::query()
            ->whereNotNull('source_url')
            ->orderBy('id')
            ->chunkById(200, function ($recordings) use ($dryRun, &$recordingsUpdated): void {
                foreach ($recordings as $recording) {
                    $fixed = DatedMonitorRecordingUrl::normalize($recording->source_url);

                    if ($fixed === null || $fixed === $recording->source_url) {
                        continue;
                    }

                    $recordingsUpdated++;

                    if (! $dryRun) {
                        $recording->update(['source_url' => $fixed]);
                    }
                }
            });

        $requeued = 0;

        if ($requeueFailedDownloads && $changedLogIds !== []) {
            $calls = Call::query()
                ->with(['voipCallLog', 'recording'])
                ->whereIn('voip_call_log_id', $changedLogIds)
                ->where('processing_status', CallProcessingStatus::Failed)
                ->where('processing_error', 'like', '%Failed to download recording%')
                ->get();

            foreach ($calls as $call) {
                $requeued++;

                if ($dryRun) {
                    continue;
                }

                $this->queue->dispatchForCall($call, forceReanalyze: true);
            }
        }

        return [
            'logs' => $logsUpdated,
            'recordings' => $recordingsUpdated,
            'requeued' => $requeued,
        ];
    }
}
