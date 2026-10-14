<?php

namespace App\Application\Crm\Deals\Actions;

use App\Domain\Crm\Deals\DealBarrierStatus;
use App\Models\ConversationAnalysis;
use App\Models\CrmDeal;
use App\Models\CrmDealBarrier;
use Illuminate\Support\Facades\DB;

class SyncDealBarriersFromAnalysis
{
    private const CATEGORY_MAP = [
        'price' => 'price_objection',
        'trust' => 'trust_gap',
        'timing' => 'bad_timing',
        'technical' => 'technical_issue',
        'other' => 'other',
    ];

    private const ACTION_HINTS = [
        'price_objection' => 'بازبینی پیشنهاد قیمت و ارزش پیشنهادی.',
        'trust_gap' => 'ارسال نمونه کار یا معرفی مشتری مرجع.',
        'bad_timing' => 'زمان‌بندی پیگیری بعدی را مشخص کنید.',
        'technical_issue' => 'هماهنگی با تیم محصول برای رفع ابهام فنی.',
        'other' => 'پیگیری مانع با کارشناس مسئول.',
        'budget' => 'بررسی بودجه و گزینه‌های پرداخت.',
        'competitor' => 'مقایسه شفاف با رقیب مطرح‌شده.',
        'manager_approval' => 'آماده‌سازی خلاصه برای تأیید مدیر.',
        'feature_gap' => 'مستند کردن نیاز و بررسی امکان محصول.',
        'contract_ambiguity' => 'شفاف‌سازی شرایط قرارداد.',
    ];

    public function handle(CrmDeal $deal, ConversationAnalysis $analysis): void
    {
        $concerns = is_array($analysis->concerns_json) ? $analysis->concerns_json : [];

        if ($concerns === []) {
            return;
        }

        DB::transaction(function () use ($deal, $analysis, $concerns): void {
            foreach ($concerns as $concern) {
                if (! is_array($concern)) {
                    continue;
                }

                $type = strtolower(trim((string) ($concern['type'] ?? 'other')));
                $text = trim((string) ($concern['text'] ?? ''));

                if ($text === '') {
                    continue;
                }

                // Never invent evidence: only store the analysis concern text as-is.
                $category = self::CATEGORY_MAP[$type] ?? 'other';
                $label = mb_substr($text, 0, 180);

                $existing = CrmDealBarrier::query()
                    ->where('crm_deal_id', $deal->id)
                    ->where('category', $category)
                    ->where('label', $label)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof CrmDealBarrier) {
                    if ($existing->status === DealBarrierStatus::Dismissed) {
                        continue;
                    }

                    if ($existing->status === DealBarrierStatus::Confirmed) {
                        // Human-confirmed barriers are not rewritten by AI.
                        $existing->update([
                            'occurrence_count' => ((int) $existing->occurrence_count) + 1,
                            'last_seen_at' => now(),
                            'source_call_id' => $analysis->call_id,
                            'source_conversation_analysis_id' => $analysis->id,
                        ]);

                        continue;
                    }

                    $existing->update([
                        'occurrence_count' => ((int) $existing->occurrence_count) + 1,
                        'evidence_text' => $text,
                        'last_seen_at' => now(),
                        'source_call_id' => $analysis->call_id,
                        'source_conversation_analysis_id' => $analysis->id,
                        'suggested_action' => self::ACTION_HINTS[$category] ?? self::ACTION_HINTS['other'],
                    ]);

                    continue;
                }

                CrmDealBarrier::query()->create([
                    'organization_id' => $deal->organization_id,
                    'crm_deal_id' => $deal->id,
                    'category' => $category,
                    'label' => $label,
                    'status' => DealBarrierStatus::Suggested,
                    'occurrence_count' => 1,
                    'evidence_text' => $text,
                    'suggested_action' => self::ACTION_HINTS[$category] ?? self::ACTION_HINTS['other'],
                    'source_call_id' => $analysis->call_id,
                    'source_conversation_analysis_id' => $analysis->id,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                ]);
            }
        });
    }
}
