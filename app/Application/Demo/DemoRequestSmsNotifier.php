<?php

namespace App\Application\Demo;

use App\Infrastructure\Sms\SmsIrClient;
use App\Models\DemoRequest;
use Illuminate\Support\Facades\Log;

class DemoRequestSmsNotifier
{
    public function __construct(private SmsIrClient $sms) {}

    public function notify(DemoRequest $request): void
    {
        if (! $this->sms->configured()) {
            Log::warning('sms.ir is not configured; demo request SMS was skipped.', [
                'demo_request_id' => $request->id,
            ]);

            return;
        }

        $applicant = $this->sms->mobile($request->phone);

        if ($applicant !== null) {
            $this->sms->send($applicant, implode("\n", [
                'درخواست دمو شما با موفقیت ثبت شد. ✅',
                'از اعتماد شما به آوایار سپاسگزاریم.',
                'کارشناسان ما به‌زودی برای هماهنگی دمو با شما تماس خواهند گرفت.',
                '',
                'آوایار | هوشمندتر بشنوید، بهتر تصمیم بگیرید',
                '',
                'لغو ۱۱',
            ]));
        }

        $owner = $this->sms->mobile((string) config('services.smsir.notify_mobile'));

        if ($owner === null) {
            Log::warning('SMSIR_NOTIFY_MOBILE is missing or not a mobile number.', [
                'demo_request_id' => $request->id,
            ]);

            return;
        }

        $this->sms->send($owner, $this->ownerMessage($request));
    }

    private function ownerMessage(DemoRequest $request): string
    {
        $lines = [
            'آوایار',
            'درخواست دمو جدید',
            $request->full_name,
            $request->company_name.' — '.$request->business_domain,
            $request->phone,
        ];

        if (filled($request->website_url)) {
            $lines[] = $request->website_url;
        }

        return implode("\n", $lines);
    }
}
