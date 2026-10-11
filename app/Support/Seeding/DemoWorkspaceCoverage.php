<?php

namespace App\Support\Seeding;

use App\Domain\Billing\Enums\WalletTransactionType;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Call\Enums\UploaderType;
use App\Domain\Crm\Enums\CrmLogStatus;
use App\Domain\Crm\Enums\CrmOperation;
use App\Domain\Crm\Enums\CrmProviderCode;
use App\Domain\Processing\Enums\ProcessingJobStage;
use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Domain\Processing\Enums\ProcessingLogLevel;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipLogStatus;
use App\Domain\Voip\Enums\VoipOperation;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use App\Models\Call;
use App\Models\CallProcessingJob;
use App\Models\CallProcessingLog;
use App\Models\CrmConnectionLog;
use App\Models\CrmProvider;
use App\Models\CrmSyncLog;
use App\Models\EmployeeIntegrationMeta;
use App\Models\Organization;
use App\Models\OrganizationCrmConnection;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\VoipCallLog;
use App\Models\VoipProvider;
use App\Models\VoipSyncLog;
use App\Models\VoipWebhookLog;
use App\Models\WalletTransaction;
use App\Services\Performance\EmployeePerformanceAnalytics;
use App\Services\Reports\DefinedExtensionCallConstraint;
use App\Services\Reports\OrganizationCallMetrics;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Fills demo-only screens that the call analytics seed leaves empty:
 * VoIP, extensions, CRM, manual uploads, the processing queue, and wallet usage.
 */
final class DemoWorkspaceCoverage
{
    public const VOIP_CONNECTION_NAME = 'سیموتل دمو';

    public const CRM_CONNECTION_NAME = 'دیدار دمو';

    private const DEMO_MARKER = 'نمونه دمو';

    public function seed(Organization $organization): void
    {
        $employees = OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $connection = $this->seedVoipConnection($organization);
        $extensions = $this->seedExtensions($connection, $employees);
        $this->seedVoipVerification($connection, $employees->first());
        $this->attachCalls($organization, $connection, $extensions);
        $this->seedCrm($organization);
        $this->seedWalletUsage($organization);

        app(DefinedExtensionCallConstraint::class)->refreshOrganization($organization->id);
        EmployeePerformanceAnalytics::forgetOrganizationCaches($organization->id);
        app(OrganizationCallMetrics::class)->forgetToday($organization->id);
    }

    private function seedVoipConnection(Organization $organization): OrganizationVoipConnection
    {
        $provider = VoipProvider::query()->where('code', VoipProviderCode::Simotel->value)->firstOrFail();

        $connection = OrganizationVoipConnection::query()->firstOrNew([
            'organization_id' => $organization->id,
            'name' => self::VOIP_CONNECTION_NAME,
        ]);

        if (blank($connection->webhook_token)) {
            $connection->webhook_token = OrganizationVoipConnection::generateWebhookToken();
        }

        $connection->fill([
            'voip_provider_id' => $provider->id,
            'credentials' => [
                'api_url' => 'https://demo.simotel.local/api/v4',
                'api_key' => 'demo-local-key',
            ],
            'settings' => [
                'extension_mapping' => [],
            ],
            'is_default' => true,
            'is_active' => true,
            'ingestion_mode' => 'webhook',
            'polling_enabled' => false,
        ]);
        $connection->save();

        return $connection;
    }

    /**
     * @param  Collection<int, OrganizationUser>  $employees
     * @return array<int, string>
     */
    private function seedExtensions(OrganizationVoipConnection $connection, Collection $employees): array
    {
        $extensions = [];

        foreach ($employees->values() as $index => $employee) {
            $extension = (string) (101 + $index);
            $extensions[$employee->id] = $extension;

            EmployeeIntegrationMeta::withoutEvents(function () use ($employee, $connection, $extension): void {
                EmployeeIntegrationMeta::query()->updateOrCreate(
                    [
                        'organization_user_id' => $employee->id,
                        'integratable_type' => OrganizationVoipConnection::class,
                        'integratable_id' => $connection->id,
                        'key' => 'extension',
                    ],
                    ['value' => $extension],
                );
            });
        }

        return $extensions;
    }

    private function seedVoipVerification(OrganizationVoipConnection $connection, ?OrganizationUser $employee): void
    {
        VoipSyncLog::query()->updateOrCreate(
            [
                'organization_voip_connection_id' => $connection->id,
                'operation' => VoipOperation::TestConnection,
            ],
            [
                'status' => VoipLogStatus::Success,
                'message' => self::DEMO_MARKER.': اتصال سیموتل تأیید شد.',
                'records_processed' => 0,
                'payload' => ['demo' => true],
            ],
        );

        VoipWebhookLog::query()
            ->where('organization_voip_connection_id', $connection->id)
            ->where('message', 'like', self::DEMO_MARKER.'%')
            ->delete();

        foreach ([
            VoipWebhookEventType::CallEnded,
            VoipWebhookEventType::RecordingCreated,
            VoipWebhookEventType::CallMissed,
            VoipWebhookEventType::CallAnswered,
        ] as $offset => $event) {
            $log = VoipWebhookLog::query()->create([
                'organization_voip_connection_id' => $connection->id,
                'event_type' => $event->value,
                'resolved_extension' => '101',
                'organization_user_id' => $employee?->id,
                'status' => VoipLogStatus::Success,
                'message' => self::DEMO_MARKER.': '.$event->label(),
                'payload' => ['demo' => true, 'event' => $event->value],
            ]);
            $log->created_at = now()->subHours($offset + 1);
            $log->save();
        }
    }

    /**
     * @param  array<int, string>  $extensions
     */
    private function attachCalls(Organization $organization, OrganizationVoipConnection $connection, array $extensions): void
    {
        $calls = Call::query()
            ->where('organization_id', $organization->id)
            ->where('provider_code', 'demo')
            ->orderBy('id')
            ->get();

        if ($calls->isEmpty()) {
            return;
        }

        $manualIds = $calls
            ->filter(fn (Call $call): bool => $this->callSequence($call) >= 20 && $this->callSequence($call) < 35)
            ->pluck('id')
            ->all();

        $uploaderId = (int) $organization->user_id;
        $recentIds = $calls
            ->sortByDesc(fn (Call $call) => $call->started_at?->getTimestamp() ?? 0)
            ->take(12)
            ->pluck('id')
            ->all();

        foreach ($calls as $call) {
            $extension = $extensions[$call->organization_user_id] ?? '101';
            $sequence = $this->callSequence($call);
            $isManual = in_array($call->id, $manualIds, true);

            if ($call->direction === 'outbound') {
                $call->caller_number = $extension;
            } else {
                $call->receiver_number = $extension;
            }

            if (in_array($sequence, [40, 41, 42, 43], true)) {
                $call->status = CallStatus::Missed->value;
            } elseif ($sequence === 44) {
                $call->status = CallStatus::Failed->value;
            } elseif ($sequence === 45) {
                $call->status = CallStatus::Busy->value;
            }

            $call->organization_voip_connection_id = $connection->id;
            $call->source = $isManual ? ConversationSource::ManualUpload : ConversationSource::Voip;

            if ($isManual) {
                $call->uploader_id = $uploaderId;
                $call->uploader_type = UploaderType::Employer;
            }

            if (in_array($call->id, $recentIds, true)) {
                $call->voip_call_log_id = $this->seedVoipLog($connection, $call, $extension)->id;
            }

            $call->saveQuietly();
        }

        $this->seedProcessingJobs($organization, $calls->whereIn('id', $manualIds)->values(), $uploaderId);
    }

    private function seedVoipLog(OrganizationVoipConnection $connection, Call $call, string $extension): VoipCallLog
    {
        $outbound = $call->direction === 'outbound';
        $customerNumber = $outbound ? (string) $call->receiver_number : (string) $call->caller_number;

        if ($customerNumber === '' || $customerNumber === $extension) {
            $customerNumber = '09120000000';
        }

        $status = CallStatus::tryFrom((string) $call->status) ?? CallStatus::Completed;

        return VoipCallLog::query()->updateOrCreate(
            [
                'organization_voip_connection_id' => $connection->id,
                'external_call_id' => 'demo-log-'.$call->id,
            ],
            [
                'organization_id' => $call->organization_id,
                'provider_code' => VoipProviderCode::Simotel->value,
                'direction' => $outbound ? CallDirection::Outbound : CallDirection::Inbound,
                'source_number' => $outbound ? $extension : $customerNumber,
                'destination_number' => $outbound ? $customerNumber : $extension,
                'status' => $status,
                'started_at' => $call->started_at,
                'ended_at' => $call->ended_at,
                'duration' => $call->duration_seconds,
                'recording_url' => 'https://demo.simotel.local/recordings/'.$call->id.'.wav',
                'raw_payload' => [
                    'demo' => true,
                    'extension' => $extension,
                ],
            ],
        );
    }

    /**
     * @param  Collection<int, Call>  $calls
     */
    private function seedProcessingJobs(Organization $organization, Collection $calls, int $uploaderId): void
    {
        $blueprints = [
            [ProcessingJobStatus::Queued, ProcessingJobStage::Queued, 25, null],
            [ProcessingJobStatus::Queued, ProcessingJobStage::Uploaded, 15, null],
            [ProcessingJobStatus::Processing, ProcessingJobStage::WaitingForAi, 65, null],
            [ProcessingJobStatus::Processing, ProcessingJobStage::SendingToAi, 45, null],
            [ProcessingJobStatus::Failed, ProcessingJobStage::ProcessingResult, 85, 'پاسخ مدل ناقص بود و نتیجه ذخیره نشد.'],
            [ProcessingJobStatus::Failed, ProcessingJobStage::SendingToAi, 40, 'فایل صوتی قابل خواندن نبود.'],
            [ProcessingJobStatus::Cancelled, ProcessingJobStage::Queued, 10, 'تحلیل این فایل لغو شد.'],
        ];

        foreach ($calls->values() as $index => $call) {
            [$status, $stage, $progress, $error] = $blueprints[$index] ?? [
                ProcessingJobStatus::Completed,
                ProcessingJobStage::Completed,
                100,
                null,
            ];

            $started = $call->started_at ?? now()->subHours($index + 2);
            $job = CallProcessingJob::query()->updateOrCreate(
                [
                    'call_id' => $call->id,
                    'file_name' => 'demo-coverage-'.$call->id.'.wav',
                ],
                [
                    'job_uuid' => (string) Str::uuid(),
                    'organization_id' => $organization->id,
                    'organization_user_id' => $call->organization_user_id,
                    'uploader_id' => $uploaderId,
                    'status' => $status,
                    'stage' => $stage,
                    'progress_percentage' => $progress,
                    'upload_started_at' => $started,
                    'upload_completed_at' => $started->copy()->addSeconds(20),
                    'queued_at' => $started->copy()->addSeconds(25),
                    'processing_started_at' => $status === ProcessingJobStatus::Queued ? null : $started->copy()->addMinute(),
                    'completed_at' => $status->isTerminal() ? $started->copy()->addMinutes(4) : null,
                    'waiting_seconds' => 40,
                    'processing_duration_seconds' => $status === ProcessingJobStatus::Queued ? null : 90,
                    'error_message' => $error,
                ],
            );

            CallProcessingLog::query()->where('call_processing_job_id', $job->id)->delete();
            CallProcessingLog::query()->create([
                'call_processing_job_id' => $job->id,
                'call_id' => $call->id,
                'level' => $status === ProcessingJobStatus::Failed ? ProcessingLogLevel::Error : ProcessingLogLevel::Info,
                'source' => 'demo',
                'message' => $error ?? self::DEMO_MARKER.': '.$status->label(),
                'context' => ['demo' => true, 'stage' => $stage->value],
                'created_at' => $started,
            ]);
        }
    }

    private function seedCrm(Organization $organization): void
    {
        $provider = CrmProvider::query()->where('code', CrmProviderCode::Didar->value)->firstOrFail();

        $connection = OrganizationCrmConnection::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => self::CRM_CONNECTION_NAME,
            ],
            [
                'crm_provider_id' => $provider->id,
                'credentials' => [
                    'api_url' => CrmProviderCode::Didar->defaultApiUrl(),
                    'api_key' => 'demo-local-key',
                ],
                'settings' => [
                    'pipeline_id' => DemoCrmCatalog::PIPELINE_ID,
                    'pipeline_stage_id' => DemoCrmCatalog::STAGE_ID,
                    'deal_owner_id' => DemoCrmCatalog::OWNER_ID,
                ],
                'is_default' => true,
                'is_active' => true,
            ],
        );

        CrmConnectionLog::query()->updateOrCreate(
            [
                'organization_crm_connection_id' => $connection->id,
                'operation' => CrmOperation::TestConnection,
            ],
            [
                'status' => CrmLogStatus::Success,
                'message' => self::DEMO_MARKER.': اتصال دیدار تأیید شد.',
                'request_payload' => ['demo' => true],
                'response_payload' => ['ok' => true],
            ],
        );

        CrmSyncLog::query()
            ->where('organization_crm_connection_id', $connection->id)
            ->where('message', 'like', self::DEMO_MARKER.'%')
            ->delete();

        foreach ([
            [CrmOperation::SyncCallIntelligence, 6, 'خلاصه و احساس ۶ تماس به معامله منتقل شد.'],
            [CrmOperation::CreateTask, 3, 'سه وظیفه پیگیری برای سرنخ‌های گرم ساخته شد.'],
            [CrmOperation::ListPipelines, 2, 'کاریز فروش و پشتیبانی خوانده شد.'],
        ] as [$operation, $records, $message]) {
            CrmSyncLog::query()->create([
                'organization_crm_connection_id' => $connection->id,
                'operation' => $operation,
                'status' => CrmLogStatus::Success,
                'records_processed' => $records,
                'message' => self::DEMO_MARKER.': '.$message,
                'payload' => ['demo' => true],
            ]);
        }
    }

    private function seedWalletUsage(Organization $organization): void
    {
        WalletTransaction::query()
            ->where('organization_id', $organization->id)
            ->where('description', 'like', self::DEMO_MARKER.'%')
            ->delete();

        $balance = (float) DemoCatalog::WALLET_BALANCE_IRR;
        $usages = [1850, 2400, 960, 3100, 1420, 2750, 1180, 860];

        foreach ($usages as $index => $amount) {
            $before = $balance;
            $balance -= $amount;
            $this->walletRow(
                $organization->id,
                WalletTransactionType::AiUsage,
                -$amount,
                $before,
                $balance,
                self::DEMO_MARKER.': تحلیل تماس‌های روز '.($index + 1),
                now()->subDays(count($usages) - $index)->setTime(11, 15),
            );
        }

        $this->walletRow(
            $organization->id,
            WalletTransactionType::Deposit,
            (float) DemoCatalog::WALLET_BALANCE_IRR - $balance,
            $balance,
            (float) DemoCatalog::WALLET_BALANCE_IRR,
            self::DEMO_MARKER.': شارژ تکمیلی برای ادامه تحلیل‌ها',
            now()->subHours(2),
        );
    }

    private function walletRow(
        int $organizationId,
        WalletTransactionType $type,
        float $amount,
        float $before,
        float $after,
        string $description,
        \Carbon\CarbonInterface $at,
    ): void {
        $transaction = new WalletTransaction([
            'organization_id' => $organizationId,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'description' => $description,
        ]);
        $transaction->created_at = $at;
        $transaction->save();
    }

    private function callSequence(Call $call): int
    {
        if (preg_match('/-call-(\d+)$/', (string) $call->external_call_id, $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }
}
