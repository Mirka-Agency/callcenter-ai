<?php

namespace App\Application\Intelligence\Jobs;

use App\Domain\Performance\Contracts\EmployeePerformanceRepositoryInterface;
use App\Models\Call;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateEmployeeMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $callId)
    {
        $this->onQueue((string) config('queue.names.followup'));
    }

    public function handle(EmployeePerformanceRepositoryInterface $performance): void
    {
        $call = Call::query()->find($this->callId);

        if (! $call) {
            return;
        }

        if (! $call->organization_user_id) {
            return;
        }

        $performance->recalculateForEmployee($call->organization_id, $call->organization_user_id);
    }
}
