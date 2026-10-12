<?php

namespace App\Application\Consultation;

use App\Infrastructure\Sms\SmsIrClient;
use App\Models\ConsultationRequest;
use Illuminate\Support\Facades\Log;

class ConsultationSmsNotifier
{
    public function __construct(private SmsIrClient $sms) {}

    public function notify(ConsultationRequest $request): void
    {
        if (! $this->sms->configured()) {
            Log::warning('sms.ir is not configured; consultation SMS was skipped.', [
                'consultation_request_id' => $request->id,
            ]);

            return;
        }

        $applicant = $this->sms->mobile($request->phone);

        if ($applicant !== null) {
            $this->sms->send(
                $applicant,
                "آوایار\nدرخواست مشاوره شما ثبت شد. به‌زودی با شما تماس می‌گیریم.",
            );
        }

        $owner = $this->sms->mobile((string) config('services.smsir.notify_mobile'));

        if ($owner === null) {
            Log::warning('SMSIR_NOTIFY_MOBILE is missing or not a mobile number.', [
                'consultation_request_id' => $request->id,
            ]);

            return;
        }

        $this->sms->send($owner, $this->ownerMessage($request));
    }

    private function ownerMessage(ConsultationRequest $request): string
    {
        return implode("\n", [
            'آوایار',
            'درخواست مشاوره جدید',
            $request->full_name,
            $request->company_name.' — '.$request->department,
            $request->phone,
        ]);
    }
}
