<?php

namespace Tests\Feature;

use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Filament\Resources\FailedQueueJobs\Pages\ListFailedQueueJobs;
use App\Models\FailedQueueJob;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FailedQueueJobAdminReasonTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_failed_jobs_table_shows_avalai_credit_reason(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        FailedQueueJob::query()->create([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([
                'uuid' => (string) Str::uuid(),
                'displayName' => AnalyzeAudioJob::class,
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => [
                    'commandName' => AnalyzeAudioJob::class,
                    'command' => serialize(new AnalyzeAudioJob(42)),
                ],
            ]),
            'exception' => 'RuntimeException: OpenAI API error (HTTP 429): {"error":{"code":"insufficient_quota","message":"You exceeded your current quota"}}',
            'failed_at' => now(),
        ]);

        Livewire::test(ListFailedQueueJobs::class)
            ->assertSuccessful()
            ->assertSee('شارژ حساب AvalAI تمام شده است');
    }
}
