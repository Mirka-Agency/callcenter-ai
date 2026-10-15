<?php

namespace App\Application\Crm\Deals;

use App\Domain\Crm\Deals\DealBarrierStatus;
use App\Domain\Crm\Deals\DealHealthLevel;
use App\Domain\Crm\Deals\DealStatus;
use App\Models\CrmDeal;
use App\Models\CrmDealBarrier;
use App\Models\CrmDealCall;

/**
 * Rule-based deal health. Not win probability and independent of lead score / lifecycle / stage name.
 *
 * Weights (documented, adjustable here):
 * - stage dwell vs threshold: up to -35
 * - inactivity: up to -30
 * - recent linked calls: up to +20
 * - next action present/overdue: +10 / -15
 * - confirmed barriers: up to -20
 * - tasks module: disabled until Tasks ships (reserved weight 0)
 */
class DealHealthScorer
{
    public function __construct(private DealAging $aging) {}

    /** @return array{score: ?int, level: DealHealthLevel, factors: list<array{code: string, label: string, impact: int}>, calculated_at: \Illuminate\Support\Carbon} */
    public function calculate(CrmDeal $deal): array
    {
        $aging = $this->aging->forDeal($deal);
        $factors = [];
        $score = 70;
        $signals = 0;

        if ($deal->status !== DealStatus::Open) {
            return [
                'score' => null,
                'level' => DealHealthLevel::InsufficientData,
                'factors' => [[
                    'code' => 'closed_deal',
                    'label' => 'معامله بسته‌شده است؛ شاخص سلامت فقط برای معاملات باز محاسبه می‌شود.',
                    'impact' => 0,
                ]],
                'calculated_at' => now(),
            ];
        }

        if ($aging['days_in_current_stage'] !== null) {
            $signals++;
            $threshold = $aging['stage_stale_after_days'];
            if ($threshold !== null && $aging['days_in_current_stage'] >= (int) $threshold) {
                $impact = -35;
                $score += $impact;
                $factors[] = [
                    'code' => 'stage_dwell',
                    'label' => 'توقف بیش از آستانه در مرحله فعلی ('.$aging['days_in_current_stage'].' روز).',
                    'impact' => $impact,
                ];
            } elseif ($aging['days_in_current_stage'] >= 10) {
                $impact = -10;
                $score += $impact;
                $factors[] = [
                    'code' => 'stage_dwell_watch',
                    'label' => 'حضور طولانی در مرحله فعلی ('.$aging['days_in_current_stage'].' روز).',
                    'impact' => $impact,
                ];
            } else {
                $factors[] = [
                    'code' => 'stage_dwell_ok',
                    'label' => 'مدت حضور در مرحله در محدوده قابل قبول است.',
                    'impact' => 0,
                ];
            }
        }

        if ($aging['inactive_days'] !== null || $deal->last_activity_at === null) {
            $signals++;
            $inactive = $aging['inactive_days'] ?? 99;
            if ($inactive >= 21) {
                $impact = -30;
                $score += $impact;
                $factors[] = ['code' => 'inactivity', 'label' => 'بیش از ۲۱ روز بدون فعالیت معنادار.', 'impact' => $impact];
            } elseif ($inactive >= 14) {
                $impact = -18;
                $score += $impact;
                $factors[] = ['code' => 'inactivity', 'label' => 'بیش از ۱۴ روز بدون فعالیت معنادار.', 'impact' => $impact];
            } elseif ($inactive <= 3) {
                $impact = 5;
                $score += $impact;
                $factors[] = ['code' => 'recent_activity', 'label' => 'فعالیت اخیر ثبت شده است.', 'impact' => $impact];
            }
        }

        $recentCalls = CrmDealCall::query()
            ->where('crm_deal_id', $deal->id)
            ->where('created_at', '>=', now()->subDays(14))
            ->count();
        $signals++;
        if ($recentCalls > 0) {
            $impact = min(20, $recentCalls * 8);
            $score += $impact;
            $factors[] = ['code' => 'recent_calls', 'label' => $recentCalls.' تماس مرتبط در ۱۴ روز اخیر.', 'impact' => $impact];
        } else {
            $factors[] = ['code' => 'no_recent_calls', 'label' => 'تماس مرتبط جدیدی در ۱۴ روز اخیر نیست.', 'impact' => -5];
            $score -= 5;
        }

        if (filled($deal->next_action_text)) {
            $signals++;
            if ($deal->next_action_due_on && $deal->next_action_due_on->lt(now()->startOfDay())) {
                $impact = -15;
                $score += $impact;
                $factors[] = ['code' => 'next_action_overdue', 'label' => 'اقدام بعدی از موعد گذشته است.', 'impact' => $impact];
            } else {
                $impact = 10;
                $score += $impact;
                $factors[] = ['code' => 'next_action_set', 'label' => 'اقدام بعدی مشخص است.', 'impact' => $impact];
            }
        } else {
            $factors[] = ['code' => 'next_action_missing', 'label' => 'اقدام بعدی ثبت نشده است.', 'impact' => -8];
            $score -= 8;
            $signals++;
        }

        $barriers = CrmDealBarrier::query()
            ->where('crm_deal_id', $deal->id)
            ->where('status', DealBarrierStatus::Confirmed)
            ->count();
        if ($barriers > 0) {
            $signals++;
            $impact = -min(20, $barriers * 8);
            $score += $impact;
            $factors[] = ['code' => 'confirmed_barriers', 'label' => $barriers.' مانع فروش تأییدشده.', 'impact' => $impact];
        }

        $overdueTasks = \App\Models\CrmTask::query()
            ->where('crm_deal_id', $deal->id)
            ->whereIn('status', [
                \App\Domain\Crm\Tasks\TaskStatus::Pending->value,
                \App\Domain\Crm\Tasks\TaskStatus::InProgress->value,
            ])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();
        $openTasks = \App\Models\CrmTask::query()
            ->where('crm_deal_id', $deal->id)
            ->whereIn('status', [
                \App\Domain\Crm\Tasks\TaskStatus::Pending->value,
                \App\Domain\Crm\Tasks\TaskStatus::InProgress->value,
            ])
            ->count();
        $signals++;
        if ($overdueTasks > 0) {
            $impact = -min(20, $overdueTasks * 10);
            $score += $impact;
            $factors[] = [
                'code' => 'tasks_module',
                'label' => $overdueTasks.' وظیفه عقب‌افتاده روی معامله.',
                'impact' => $impact,
            ];
        } elseif ($openTasks > 0) {
            $factors[] = [
                'code' => 'tasks_module',
                'label' => $openTasks.' وظیفه باز روی معامله (بدون تأخیر).',
                'impact' => 0,
            ];
        } else {
            $factors[] = [
                'code' => 'tasks_module',
                'label' => 'وظیفه بازی برای معامله ثبت نشده است.',
                'impact' => 0,
            ];
        }

        if ($signals < 2) {
            return [
                'score' => null,
                'level' => DealHealthLevel::InsufficientData,
                'factors' => [[
                    'code' => 'insufficient_data',
                    'label' => 'داده کافی برای محاسبه سلامت وجود ندارد.',
                    'impact' => 0,
                ]],
                'calculated_at' => now(),
            ];
        }

        $score = max(0, min(100, $score));
        $level = match (true) {
            $score >= 70 => DealHealthLevel::Healthy,
            $score >= 45 => DealHealthLevel::Watch,
            default => DealHealthLevel::AtRisk,
        };

        return [
            'score' => $score,
            'level' => $level,
            'factors' => $factors,
            'calculated_at' => now(),
        ];
    }

    public function persist(CrmDeal $deal): CrmDeal
    {
        $result = $this->calculate($deal);
        $deal->forceFill([
            'health_score' => $result['score'],
            'health_level' => $result['level']->value,
            'health_factors_json' => $result['factors'],
            'health_calculated_at' => $result['calculated_at'],
        ])->save();

        return $deal->fresh() ?? $deal;
    }
}
