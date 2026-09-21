<?php

namespace App\Models;

use App\Services\QueueMonitoring\QueueJobInspection;
use App\Services\QueueMonitoring\QueueJobInspector;
use Illuminate\Database\Eloquent\Model;

class FailedQueueJob extends Model
{
    protected $table = 'failed_jobs';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
        ];
    }

    public function inspection(): QueueJobInspection
    {
        return app(QueueJobInspector::class)->inspect($this->payload, $this->exception);
    }

    public function jobClassLabel(): string
    {
        return $this->inspection()->shortLabel();
    }

    public function callId(): ?int
    {
        return $this->inspection()->callId();
    }

    public function exceptionSummary(): ?string
    {
        return $this->inspection()->exceptionMessage;
    }

    public function failureReason(): ?string
    {
        return $this->inspection()->failureReason ?: $this->exceptionSummary();
    }
}
