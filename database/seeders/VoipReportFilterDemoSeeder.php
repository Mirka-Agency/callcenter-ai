<?php

namespace Database\Seeders;

use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipLogStatus;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\VoipCallLog;
use App\Models\VoipProvider;
use App\Models\VoipWebhookLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class VoipReportFilterDemoSeeder extends Seeder
{
    public const CONNECTION_NAME = 'اتصال تست فیلتر گزارش';

    public function run(): void
    {
        $organization = Organization::query()->where('title', 'امیدویپ جنوب')->first()
            ?? Organization::query()->orderByDesc('id')->first();

        if ($organization === null) {
            throw new \RuntimeException('No organization found to attach the demo VoIP connection.');
        }

        $provider = VoipProvider::query()->where('code', VoipProviderCode::Simotel->value)->firstOrFail();

        $connection = OrganizationVoipConnection::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => self::CONNECTION_NAME,
            ],
            [
                'voip_provider_id' => $provider->id,
                'credentials' => [
                    'api_url' => 'https://simotel.test/api',
                    'api_key' => 'demo-filter-key',
                ],
                'settings' => [
                    'extension_mapping' => [
                        '02191093492' => '101',
                    ],
                ],
                'is_default' => true,
                'is_active' => true,
                'ingestion_mode' => 'webhook',
                'polling_enabled' => false,
            ],
        );

        VoipCallLog::query()
            ->where('organization_voip_connection_id', $connection->id)
            ->delete();
        VoipWebhookLog::query()
            ->where('organization_voip_connection_id', $connection->id)
            ->delete();

        $employees = OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->get();

        $employee101 = $employees->get(0);
        $employee202 = $employees->get(1) ?? $employee101;
        $employee303 = $employees->get(2) ?? $employee101;

        $now = Carbon::now();

        $calls = [
            [
                'external_call_id' => 'CALL-IN-101',
                'direction' => CallDirection::Inbound,
                'source_number' => '09120001001',
                'destination_number' => '101',
                'status' => CallStatus::Completed,
                'duration' => 95,
                'started_at' => $now->copy()->subHours(2),
                'raw_payload' => ['resolved_extension' => '101', 'cuid' => 'CALL-IN-101'],
            ],
            [
                'external_call_id' => 'CALL-IN-101B',
                'direction' => CallDirection::Inbound,
                'source_number' => '09120001002',
                'destination_number' => '101',
                'status' => CallStatus::Missed,
                'duration' => 8,
                'started_at' => $now->copy()->subHour(),
                'raw_payload' => ['extension' => '101', 'cuid' => 'CALL-IN-101B'],
            ],
            [
                'external_call_id' => 'CALL-OUT-101',
                'direction' => CallDirection::Outbound,
                'source_number' => '101',
                'destination_number' => '09121111001',
                'status' => CallStatus::Completed,
                'duration' => 140,
                'started_at' => $now->copy()->subMinutes(40),
                'raw_payload' => ['resolved_extension' => '101', 'call_id' => 'CALL-OUT-101'],
            ],
            [
                'external_call_id' => 'CALL-IN-202',
                'direction' => CallDirection::Inbound,
                'source_number' => '09350002002',
                'destination_number' => '202',
                'status' => CallStatus::Completed,
                'duration' => 61,
                'started_at' => $now->copy()->subMinutes(25),
                'raw_payload' => ['resolved_extension' => '202', 'unique_id' => 'CALL-IN-202'],
            ],
            [
                'external_call_id' => 'CALL-OUT-202',
                'direction' => CallDirection::Outbound,
                'source_number' => '202',
                'destination_number' => '09351112002',
                'status' => CallStatus::Busy,
                'duration' => 12,
                'started_at' => $now->copy()->subMinutes(18),
                'raw_payload' => ['agent_extension' => '202', 'cuid' => 'CALL-OUT-202'],
            ],
            [
                'external_call_id' => 'CALL-IN-303',
                'direction' => CallDirection::Inbound,
                'source_number' => '09220003003',
                'destination_number' => '303',
                'status' => CallStatus::Completed,
                'duration' => 210,
                'started_at' => $now->copy()->subMinutes(10),
                'raw_payload' => ['internal_number' => '303', 'cuid' => 'CALL-IN-303'],
            ],
            [
                'external_call_id' => 'CALL-OUT-303',
                'direction' => CallDirection::Outbound,
                'source_number' => '303',
                'destination_number' => '09221113003',
                'status' => CallStatus::Failed,
                'duration' => 3,
                'started_at' => $now->copy()->subMinutes(5),
                'raw_payload' => ['exten' => '303', 'external_call_id' => 'CALL-OUT-303'],
            ],
        ];

        foreach ($calls as $call) {
            $endedAt = $call['started_at']->copy()->addSeconds((int) $call['duration']);

            VoipCallLog::query()->create([
                'organization_id' => $organization->id,
                'organization_voip_connection_id' => $connection->id,
                'provider_code' => VoipProviderCode::Simotel->value,
                'recording_url' => null,
                'ended_at' => $endedAt,
                ...$call,
            ]);
        }

        $webhooks = [
            [
                'event_type' => VoipWebhookEventType::CallEnded->value,
                'resolved_extension' => '101',
                'organization_user_id' => $employee101?->id,
                'status' => VoipLogStatus::Success,
                'message' => 'CDR ورودی داخلی ۱۰۱',
                'payload' => [
                    'cuid' => 'CALL-IN-101',
                    'type' => 'incoming',
                    'exten' => '101',
                    'src' => '09120001001',
                    'dst' => '101',
                    'disposition' => 'ANSWERED',
                ],
                'created_at' => $now->copy()->subHours(2)->addSeconds(95),
            ],
            [
                'event_type' => VoipWebhookEventType::CallMissed->value,
                'resolved_extension' => '101',
                'organization_user_id' => $employee101?->id,
                'status' => VoipLogStatus::Success,
                'message' => 'تماس از دست‌رفته داخلی ۱۰۱',
                'payload' => [
                    'cuid' => 'CALL-IN-101B',
                    'direction' => 'inbound',
                    'extension' => '101',
                    'src' => '09120001002',
                    'dst' => '101',
                    'disposition' => 'NO ANSWER',
                ],
                'created_at' => $now->copy()->subHour()->addSeconds(8),
            ],
            [
                'event_type' => VoipWebhookEventType::CallEnded->value,
                'resolved_extension' => '101',
                'organization_user_id' => $employee101?->id,
                'status' => VoipLogStatus::Success,
                'message' => 'خروجی داخلی ۱۰۱',
                'payload' => [
                    'call_id' => 'CALL-OUT-101',
                    'direction' => 'outbound',
                    'extension' => '101',
                    'src' => '101',
                    'dst' => '09121111001',
                ],
                'created_at' => $now->copy()->subMinutes(40)->addSeconds(140),
            ],
            [
                'event_type' => VoipWebhookEventType::CallEnded->value,
                'resolved_extension' => '202',
                'organization_user_id' => $employee202?->id,
                'status' => VoipLogStatus::Success,
                'message' => 'ورودی داخلی ۲۰۲',
                'payload' => [
                    'unique_id' => 'CALL-IN-202',
                    'direction' => 'in',
                    'extension' => '202',
                    'src' => '09350002002',
                    'dst' => '202',
                ],
                'created_at' => $now->copy()->subMinutes(25)->addSeconds(61),
            ],
            [
                'event_type' => VoipWebhookEventType::CallEnded->value,
                'resolved_extension' => '202',
                'organization_user_id' => $employee202?->id,
                'status' => VoipLogStatus::Failed,
                'message' => 'خروجی داخلی ۲۰۲ ناموفق',
                'payload' => [
                    'cuid' => 'CALL-OUT-202',
                    'type' => 'outgoing',
                    'exten' => '202',
                    'src' => '202',
                    'dst' => '09351112002',
                ],
                'created_at' => $now->copy()->subMinutes(18)->addSeconds(12),
            ],
            [
                'event_type' => VoipWebhookEventType::CallEnded->value,
                'resolved_extension' => '303',
                'organization_user_id' => $employee303?->id,
                'status' => VoipLogStatus::Success,
                'message' => 'ورودی داخلی ۳۰۳ بدون direction در payload',
                'payload' => [
                    'cuid' => 'CALL-IN-303',
                    'event' => 'cdr',
                    'src' => '09220003003',
                    'dst' => '303',
                ],
                'created_at' => $now->copy()->subMinutes(10)->addSeconds(210),
            ],
            [
                'event_type' => VoipWebhookEventType::AgentStateChanged->value,
                'resolved_extension' => '303',
                'organization_user_id' => $employee303?->id,
                'status' => VoipLogStatus::Pending,
                'message' => 'NewState خروجی داخلی ۳۰۳',
                'payload' => [
                    'uniqueid' => 'CALL-OUT-303',
                    'direction' => 'out',
                    'exten' => '303',
                    'state' => 'inuse',
                ],
                'created_at' => $now->copy()->subMinutes(5),
            ],
        ];

        foreach ($webhooks as $webhook) {
            $createdAt = $webhook['created_at'];
            unset($webhook['created_at']);

            $log = VoipWebhookLog::query()->create([
                'organization_voip_connection_id' => $connection->id,
                ...$webhook,
            ]);
            $log->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        $this->command?->info(sprintf(
            'VoIP demo reports ready: org=%s connection_id=%d calls=%d webhooks=%d',
            $organization->title,
            $connection->id,
            count($calls),
            count($webhooks),
        ));
    }
}
