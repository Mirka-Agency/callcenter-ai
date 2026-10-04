<?php

namespace App\Jobs;

use App\Services\Demo\DemoPersonProvisioner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionDemoPersonJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        private readonly string $phone,
        private readonly string $name,
        private readonly string $email,
        private readonly string $password,
    ) {
        $this->onQueue((string) config('queue.names.followup'));
    }

    public function handle(DemoPersonProvisioner $provisioner): void
    {
        $provisioner->provision($this->phone, $this->name, $this->email, $this->password);
    }
}
