<?php

namespace App\Support\Seeding;

use App\Application\Crm\Deals\AnalysisDealEvidence;
use App\Application\Crm\Deals\DealAudit;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Call\Enums\UploaderType;
use App\Domain\Coaching\CoachingCatalog;
use App\Domain\Crm\Deals\DealActivityType;
use App\Domain\Crm\Deals\DealSource;
use App\Domain\Crm\Deals\DealStatus;
use App\Domain\Crm\Deals\DealSuggestionStatus;
use App\Domain\Crm\Deals\DealSuggestionType;
use App\Domain\Crm\Deals\PipelineStagePalette;
use App\Domain\Crm\Invoices\InvoiceDocumentType;
use App\Domain\Crm\Invoices\InvoiceStatus;
use App\Domain\Crm\OrganizationCrmMode;
use App\Domain\Crm\Products\ProductType;
use App\Domain\Crm\Tasks\TaskDueBasis;
use App\Domain\Crm\Tasks\TaskPriority;
use App\Domain\Crm\Tasks\TaskSource;
use App\Domain\Crm\Tasks\TaskStatus;
use App\Domain\Customer\CustomerLifecycleStage;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Domain\Processing\Enums\ProcessingJobStage;
use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipLogStatus;
use App\Domain\Voip\Enums\VoipOperation;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Enums\ReportDatePreset;
use App\Enums\UserRole;
use App\Models\Call;
use App\Models\CallProcessingJob;
use App\Models\CallRecording;
use App\Models\ConversationAnalysis;
use App\Models\CrmDeal;
use App\Models\CrmDealAiSuggestion;
use App\Models\CrmDealCall;
use App\Models\CrmDealContact;
use App\Models\CrmInvoice;
use App\Models\CrmInvoiceLine;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\CrmProduct;
use App\Models\CrmProductCategory;
use App\Models\CrmTask;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\CustomerContactMethod;
use App\Models\EmployeeIntegrationMeta;
use App\Models\LlmModel;
use App\Models\Organization;
use App\Models\OrganizationActivity;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\User;
use App\Models\VoipCallLog;
use App\Models\VoipProvider;
use App\Models\VoipSyncLog;
use App\Services\CallIntake\CallIntakeSettings;
use App\Services\CallIntake\Filters\InternalAgentCallsFilter;
use App\Services\CustomerCompanyService;
use App\Services\Performance\EmployeePerformanceAnalytics;
use App\Services\Reports\DefinedExtensionCallConstraint;
use App\Services\Reports\OrganizationCallMetrics;
use App\Support\CompanyWorkCalendar;
use Carbon\Carbon;
use Faker\Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One believable tenant for live demos on avayar.org.
 * Visible copy never says the records are samples.
 */
final class AvayarShowcase
{
    public const EMPLOYER_EMAIL = 'nima.rahimi@avayar.org';

    public const PASSWORD = 'Avayar#1404';

    public const TITLE = 'آوایار';

    private const PIPELINE = 'فروش';

    private const CALLS = 84;

    private const CALLS_TODAY = 8;

    public function __construct(
        private DemoConversationContentBuilder $content,
        private CustomerCompanyService $companies,
        private DealAudit $dealAudit,
    ) {}

    /** @return array<string, mixed> */
    public function seed(): array
    {
        $existing = $this->existingOrganization();

        if ($existing instanceof Organization && $this->alreadyFilled($existing)) {
            $this->ensureTodayDissatisfied($existing);
            $this->ensureChartSeries($existing);
            $this->ensureKpiActivity($existing);

            return $this->summary($existing, created: false);
        }

        $organization = $existing ?? $this->createOrganization();
        $employees = $this->ensureEmployees($organization);
        $customers = $this->ensureCustomers($organization, $employees);
        $this->ensureCalls($organization, $employees, $customers);
        $this->ensureVoip($organization, $employees);
        $this->ensureCrm($organization, $employees, $customers);

        $this->ensureTodayDissatisfied($organization);
        $this->ensureChartSeries($organization);
        $this->ensureKpiActivity($organization);

        return $this->summary($organization->fresh(), created: ! $existing instanceof Organization);
    }

    private function existingOrganization(): ?Organization
    {
        $employer = User::query()->where('email', self::EMPLOYER_EMAIL)->first();

        if (! $employer instanceof User) {
            $clash = Organization::query()->where('title', self::TITLE)->where('is_demo', false)->exists();

            if ($clash) {
                throw new \RuntimeException('یک سازمان واقعی با نام آوایار وجود دارد. برای جلوگیری از قاطی شدن داده‌ها، چیزی ساخته نشد.');
            }

            return null;
        }

        $organization = Organization::query()->where('user_id', $employer->id)->first();

        if (! $organization instanceof Organization) {
            throw new \RuntimeException('کاربر مدیر آوایار هست ولی سازمانش پیدا نشد.');
        }

        if ($organization->title !== self::TITLE) {
            throw new \RuntimeException('ایمیل مدیر آوایار به سازمان دیگری وصل است.');
        }

        return $organization;
    }

    private function alreadyFilled(Organization $organization): bool
    {
        return CrmPipeline::query()
            ->where('organization_id', $organization->id)
            ->where('name', self::PIPELINE)
            ->exists()
            && Call::query()->where('organization_id', $organization->id)->count() >= self::CALLS;
    }

    /**
     * Today's summary only lists negative, evaluable calls that happened today.
     */
    private function ensureTodayDissatisfied(Organization $organization): void
    {
        $cases = [
            [
                'phone' => '09205173864',
                'hour' => 9,
                'minute' => 18,
                'score' => 46,
                'concern' => 'تأخیر در ارسال سفارش‌های شمال تهران',
                'summary' => 'مینا افشار از فروشگاه خانه‌کالا تماس گرفت. سه سفارش شمال تهران هنوز به دست مشتری نرسیده و گفت اگر تا عصر تعیین تکلیف نشود شکایت را بالاتر می‌برد.',
                'transcript' => "مشتری: سه روزه سفارش‌های شمال تهران نرسیده. مشتری‌ها دارند به خودمان زنگ می‌زنند.\nکارشناس: حق دارید. کد سفارش‌ها را می‌گیرم و وضعیت انبار را همین الان چک می‌کنم.\nمشتری: دیروز هم قول دادید خبر بدهید و خبری نشد. این کیفیت خدمات برای ما قابل قبول نیست.\nکارشناس: عذرخواهی می‌کنم. تا ظهر نتیجه را خودم به شما زنگ می‌زنم.",
            ],
            [
                'phone' => '09331450826',
                'hour' => 10,
                'minute' => 42,
                'score' => 41,
                'concern' => 'شکایت از لحن پاسخگویی و نوبت اشتباه',
                'summary' => 'پارمیدا شاهی از کلینیک رویال ناراضی بود. نوبت بیمار جابه‌جا شده و گفت لحن کارشناس دیروز تند بوده.',
                'transcript' => "مشتری: بیماری که برای یازده هماهنگ کرده بودید را فرستادید ساعت نه. دکتر معطل شد.\nکارشناس: ببخشید، صف صبح شلوغ بوده.\nمشتری: این عذرخواهی نیست. بیمار از لحن همکار شما هم شاکی بود و گفت دیگر با این خط تماس نمی‌گیرد.\nکارشناس: موضوع را برای سرپرست شیفت ثبت می‌کنم و خودم نوبت را اصلاح می‌کنم.",
            ],
            [
                'phone' => '09302261845',
                'hour' => 12,
                'minute' => 7,
                'score' => 44,
                'concern' => 'از دست رفتن نوبت به‌خاطر تماس بی‌پاسخ',
                'summary' => 'هستی راد از کلینیک دندانپزشکی ونک گفت ظهرها تلفن را برنمی‌دارند و دو بیمار نوبت را از دست داده‌اند.',
                'transcript' => "مشتری: از یازده تا دوازده و نیم شش بار زنگ زدیم و کسی جواب نداد.\nکارشناس: شیفت ظهر دو نفر کمتر بوده.\nمشتری: بیمار آمده بوده پشت در و برگشته. این برای کلینیک بدقولی است.\nکارشناس: اسامی را می‌گیرم و برای عصر جا باز می‌کنم. گزارش تماس‌های بی‌پاسخ را هم برای دکتر مرادی می‌فرستم.",
            ],
            [
                'phone' => '09354471026',
                'hour' => 15,
                'minute' => 26,
                'score' => 49,
                'concern' => 'تأخیر در تحویل بار و نبود اطلاع‌رسانی',
                'summary' => 'سمیرا دولت‌آبادی از ره‌آورد بار گفت گیرنده بار از دیروز منتظر است و هیچ تماسی درباره تأخیر نگرفته.',
                'transcript' => "مشتری: بار کرج باید دیروز تخلیه می‌شد. گیرنده می‌گوید کسی حتی زنگ نزده.\nکارشناس: اجازه بدید موقعیت راننده را ببینم.\nمشتری: این سومین بار این ماه است. اگر اطلاع‌رسانی نباشد مشتری بار را کنسل می‌کند.\nکارشناس: راننده ساعت شانزده به مقصد می‌رسد. خودم به گیرنده خبر می‌دهم.",
            ],
        ];

        $changed = 0;

        foreach ($cases as $case) {
            $customer = Customer::query()
                ->where('organization_id', $organization->id)
                ->where('normalized_phone', $case['phone'])
                ->first();

            if (! $customer instanceof Customer) {
                continue;
            }

            $call = Call::query()
                ->where('organization_id', $organization->id)
                ->where('customer_id', $customer->id)
                ->latest('started_at')
                ->first();

            if (! $call instanceof Call) {
                continue;
            }

            $started = now(CompanyWorkCalendar::TIMEZONE)
                ->setTime($case['hour'], $case['minute'])
                ->utc();

            if ($started->greaterThan(now())) {
                $started = now()->subMinutes(20 + $changed * 25);
            }

            $ended = $started->copy()->addMinutes(7);
            $call->forceFill([
                'started_at' => $started,
                'ended_at' => $ended,
                'conversation_date' => $started,
                'duration_seconds' => 420,
                'title' => $case['concern'],
                'category' => 'پشتیبانی',
                'notes' => $case['summary'],
                'status' => 'completed',
            ])->saveQuietly();

            $analysis = ConversationAnalysis::query()->where('call_id', $call->id)->first();

            if (! $analysis instanceof ConversationAnalysis) {
                continue;
            }

            $analysis->forceFill([
                'sentiment' => AnalysisSentiment::Negative,
                'score' => $case['score'],
                'is_evaluable' => true,
                'is_personal' => false,
                'needs_attention' => true,
                'summary' => $case['summary'],
                'transcript' => $case['transcript'],
                'overall_evaluation' => 'مشتری از کیفیت رسیدگی ناراضی ماند و پیگیری امروز لازم است.',
                'concerns_json' => [[
                    'type' => 'service',
                    'text' => $case['concern'],
                    'severity' => 'high',
                ]],
                'weaknesses_json' => ['جمع‌بندی و زمان پیگیری برای مشتری شفاف نشد'],
                'attention_json' => [
                    'needed' => true,
                    'categories' => ['service'],
                    'reason' => $case['concern'],
                ],
                'analyzed_at' => $ended->copy()->addMinutes(3),
            ])->saveQuietly();

            $customer->forceFill([
                'last_contact_at' => $started,
                'conversation_trend' => 'declining',
                'recommended_next_action' => 'تماس جبرانی امروز',
            ])->saveQuietly();

            $changed++;
        }

        if ($changed === 0) {
            return;
        }

        $since = config('dashboard.insight_lists_since');
        $sinceKey = is_string($since) && trim($since) !== ''
            ? (string) Carbon::parse($since)->getTimestamp()
            : 'none';
        $dayKey = now(CompanyWorkCalendar::TIMEZONE)->toDateString();

        Cache::forget("dashboard:sentiment:{$organization->id}:30:10:{$sinceKey}:{$dayKey}");
        Cache::forget("dashboard:sentiment:{$organization->id}:30:10:{$sinceKey}:any");
        app(OrganizationCallMetrics::class)->forgetToday($organization->id);
    }

    /**
     * Quality and volume charts hide days that have no recorded call on a
     * registered extension. Coaching charts need a skill score on each analysis.
     */
    private function ensureChartSeries(Organization $organization): void
    {
        $connection = OrganizationVoipConnection::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('is_default')
            ->first();

        $extensions = $connection instanceof OrganizationVoipConnection
            ? EmployeeIntegrationMeta::query()
                ->where('integratable_type', OrganizationVoipConnection::class)
                ->where('integratable_id', $connection->id)
                ->where('key', 'extension')
                ->pluck('value', 'organization_user_id')
            : collect();

        Call::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->each(function (Call $call) use ($connection, $extensions): void {
                $extension = trim((string) ($extensions[$call->organization_user_id] ?? ''));
                $changes = [];

                if ($connection instanceof OrganizationVoipConnection
                    && (int) $call->organization_voip_connection_id !== (int) $connection->id) {
                    $changes['organization_voip_connection_id'] = $connection->id;
                }

                $caller = trim((string) $call->caller_number);
                $receiver = trim((string) $call->receiver_number);

                if ($extension !== '' && $caller !== $extension && $receiver !== $extension) {
                    if ($call->direction === 'outbound') {
                        $changes['caller_number'] = $extension;
                    } else {
                        $changes['receiver_number'] = $extension;
                    }
                }

                if ($changes !== []) {
                    $call->forceFill($changes)->saveQuietly();
                }

                CallRecording::query()->updateOrCreate(
                    ['call_id' => $call->id],
                    [
                        'storage_disk' => 'local',
                        'storage_path' => 'recordings/avayar/'.$call->id.'.wav',
                        'mime_type' => 'audio/wav',
                        'file_size_bytes' => 48000,
                        'duration_seconds' => $call->duration_seconds,
                        'channels' => 1,
                        'status' => 'completed',
                        'is_expired' => false,
                        'downloaded_at' => $call->ended_at ?? $call->started_at,
                    ],
                );
            });

        $notes = [
            'call_opening' => 'شروع تماس با معرفی کوتاه و دلیل تماس بود.',
            'need_discovery' => 'درباره حجم تماس روزانه و شیفت‌ها سؤال شد.',
            'active_listening' => 'حرف مشتری تا آخر شنیده شد و بعد جواب داده شد.',
            'conversation_management' => 'مکالمه از حاشیه برگشت به موضوع اصلی.',
            'product_knowledge' => 'جزئیات پلن پاسخگویی درست توضیح داده شد.',
            'presentation' => 'فایده خدمت با یک مثال از مشتری مشابه گفته شد.',
            'objection_handling' => 'اعتراض قیمت شنیده شد و با تفاوت پلن‌ها جواب داده شد.',
            'negotiation' => 'شرایط پرداخت و مدت قرارداد روی میز ماند.',
            'closing' => 'قدم بعدی و زمان تماس بعدی مشخص شد.',
            'follow_up' => 'زمان پیگیری و مسئول آن برای مشتری تکرار شد.',
            'empathy' => 'نارضایتی مشتری بدون عجله تأیید شد.',
            'dissatisfied_customer' => 'برای شکایت زمان جبران مشخص شد.',
            'communication_quality' => 'جمله‌ها کوتاه و قابل فهم بودند.',
            'compliance' => 'معرفی شرکت و اجازه ضبط طبق روال گفته شد.',
            'call_closing' => 'جمع‌بندی آخر تماس برای مشتری تکرار شد.',
        ];

        ConversationAnalysis::query()
            ->where('organization_id', $organization->id)
            ->where('is_evaluable', true)
            ->orderBy('id')
            ->each(function (ConversationAnalysis $analysis) use ($notes): void {
                $current = $analysis->coaching_analysis_json;
                if (is_array($current) && is_array($current['skills'] ?? null) && $current['skills'] !== []) {
                    return;
                }

                $skills = [];
                foreach (CoachingCatalog::skills() as $index => $skill) {
                    $delta = (($analysis->id + $index * 5) % 17) - 8;
                    $score = max(42, min(96, (int) $analysis->score + $delta));

                    if (in_array($skill['key'], ['objection_handling', 'dissatisfied_customer'], true) && (int) $analysis->score < 70) {
                        $score = min($score, 61);
                    }

                    $skills[] = [
                        'skill_key' => $skill['key'],
                        'score' => $score,
                        'status' => CoachingCatalog::statusFor($score),
                        'confidence' => 0.84,
                        'feedback' => $notes[$skill['key']] ?? $skill['label'],
                        'recommendation' => $score < 70
                            ? 'در تماس بعدی همین بخش را با یک مثال مشخص جمع کنید.'
                            : 'همین شکل را در تماس‌های مشابه ادامه دهید.',
                        'evidence' => [[
                            'description' => $notes[$skill['key']] ?? $skill['label'],
                            'quote_or_summary' => $notes[$skill['key']] ?? $skill['label'],
                            'start_time' => 18 + ($index * 11),
                            'end_time' => 34 + ($index * 11),
                        ]],
                    ];
                }

                $analysis->forceFill([
                    'coaching_analysis_json' => ['skills' => $skills],
                ])->saveQuietly();
            });

        Customer::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->each(function (Customer $customer): void {
                if (is_array($customer->common_concerns_json) && $customer->common_concerns_json !== []) {
                    return;
                }

                $counts = [];

                ConversationAnalysis::query()
                    ->where('organization_id', $customer->organization_id)
                    ->whereIn('call_id', Call::query()->where('customer_id', $customer->id)->select('id'))
                    ->get(['concerns_json'])
                    ->each(function (ConversationAnalysis $analysis) use (&$counts): void {
                        foreach ($analysis->concerns_json ?? [] as $concern) {
                            $type = is_array($concern) ? (string) ($concern['type'] ?? 'other') : 'other';
                            $counts[$type] = ($counts[$type] ?? 0) + 1;
                        }
                    });

                if ($counts === []) {
                    return;
                }

                arsort($counts);

                $customer->forceFill([
                    'common_concerns_json' => collect($counts)
                        ->map(fn (int $count, string $type) => ['type' => $type, 'count' => $count])
                        ->values()
                        ->take(5)
                        ->all(),
                ])->saveQuietly();
            });

        app(DefinedExtensionCallConstraint::class)->refreshOrganization($organization->id);
        $this->forgetChartCaches($organization);
    }

    private function forgetChartCaches(Organization $organization): void
    {
        EmployeePerformanceAnalytics::forgetOrganizationCaches($organization->id);
        app(OrganizationCallMetrics::class)->forgetToday($organization->id);

        $fingerprint = md5(json_encode(app(DefinedExtensionCallConstraint::class)->matchSetFingerprint($organization->id)) ?: '');
        $intake = app(CallIntakeSettings::class)->cacheToken($organization->id);

        foreach (ReportDatePreset::cases() as $preset) {
            if ($preset === ReportDatePreset::Custom) {
                continue;
            }

            [$from, $to] = $preset->resolve();
            $from = $from->copy()->timezone(CompanyWorkCalendar::TIMEZONE)->startOfDay()->utc();
            $to = $to->copy()->timezone(CompanyWorkCalendar::TIMEZONE)->endOfDay()->utc();
            $key = $organization->id.'|'.$from->getTimestamp().'|'.$to->getTimestamp().'|'.$fingerprint.'|'.$intake.'|recorded';
            Cache::forget('extension-activity:'.$key);
        }
    }

    /**
     * Keep today, this week, and the previous month from reading as zero.
     * Safe to run again: dates are anchored to the current day.
     */
    public function refreshKpiWindows(Organization $organization): void
    {
        if ($organization->title !== self::TITLE || ! $organization->is_demo) {
            return;
        }

        $day = now(CompanyWorkCalendar::TIMEZONE)->toDateString();
        $key = 'avayar-kpi-windows:'.$organization->id.':'.$day;

        if (Cache::has($key)) {
            return;
        }

        $this->ensureKpiActivity($organization);
        Cache::put($key, true, now(CompanyWorkCalendar::TIMEZONE)->endOfDay());
    }

    private function ensureKpiActivity(Organization $organization): void
    {
        $connection = OrganizationVoipConnection::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('is_default')
            ->first();

        $filters = $organization->call_intake_filters ?? [];
        $filters[InternalAgentCallsFilter::KEY] = false;
        $organization->forceFill(['call_intake_filters' => $filters])->saveQuietly();
        app(CallIntakeSettings::class)->forget($organization->id);

        $protectedPhones = ['09205173864', '09331450826', '09302261845', '09354471026'];
        $protectedCustomerIds = Customer::query()
            ->where('organization_id', $organization->id)
            ->whereIn('phone_number', $protectedPhones)
            ->pluck('id');

        $this->placeCallsInPreviousPeriod($organization, $protectedCustomerIds);
        $this->markMissedCalls($organization, $protectedCustomerIds);
        $this->ensureInternalCalls($organization, $connection);
        $this->ensureVoipLogs($organization, $connection);
        $this->ensureVoipVerified($connection);
        $this->anchorCrmDates($organization);

        app(DefinedExtensionCallConstraint::class)->refreshOrganization($organization->id);
        $this->forgetChartCaches($organization);
    }

    /** @param  Collection<int, int>  $protectedCustomerIds */
    private function placeCallsInPreviousPeriod(Organization $organization, Collection $protectedCustomerIds): void
    {
        $alreadyPlaced = Call::query()
            ->where('organization_id', $organization->id)
            ->where('provider_code', 'demo')
            ->where('started_at', '<', now()->subDays(30))
            ->count();

        if ($alreadyPlaced >= 12) {
            return;
        }

        $calls = Call::query()
            ->where('organization_id', $organization->id)
            ->where('provider_code', 'demo')
            ->where('started_at', '>=', now()->subDays(27))
            ->where(function ($query) use ($protectedCustomerIds): void {
                $query->whereNull('customer_id')
                    ->orWhereNotIn('customer_id', $protectedCustomerIds);
            })
            ->orderBy('started_at')
            ->limit(18)
            ->get()
            ->filter(function (Call $call): bool {
                $metadata = is_array($call->metadata) ? $call->metadata : [];

                return ($metadata['kpi_window'] ?? null) !== 'previous';
            })
            ->values();

        foreach ($calls as $index => $call) {
            $started = now()->subDays(34 + ($index % 14))->setTime(
                (int) ($call->started_at?->hour ?? 10),
                (int) ($call->started_at?->minute ?? 15),
            );
            $duration = max(120, (int) $call->duration_seconds);
            $metadata = is_array($call->metadata) ? $call->metadata : [];
            $metadata['kpi_window'] = 'previous';

            $call->forceFill([
                'started_at' => $started,
                'ended_at' => $started->copy()->addSeconds($duration),
                'conversation_date' => $started,
                'metadata' => $metadata,
            ])->saveQuietly();

            $analysis = ConversationAnalysis::query()->where('call_id', $call->id)->first();
            if (! $analysis instanceof ConversationAnalysis) {
                continue;
            }

            $score = max(52, (int) $analysis->score - 9);
            $lead = is_array($analysis->lead_quality_json) ? $analysis->lead_quality_json : [];
            if (isset($lead['score']) && is_numeric($lead['score'])) {
                $lead['score'] = max(40, (int) $lead['score'] - 8);
            }

            $analysis->forceFill([
                'score' => $score,
                'sentiment' => $score >= 70 ? AnalysisSentiment::Neutral : AnalysisSentiment::Mixed,
                'lead_quality_json' => $lead === [] ? $analysis->lead_quality_json : $lead,
                'analyzed_at' => $started->copy()->addSeconds($duration + 180),
            ])->saveQuietly();
        }
    }

    /** @param  Collection<int, int>  $protectedCustomerIds */
    private function markMissedCalls(Organization $organization, Collection $protectedCustomerIds): void
    {
        $recentMissed = Call::query()
            ->where('organization_id', $organization->id)
            ->where('status', CallStatus::Missed->value)
            ->where('started_at', '>=', now()->subDays(6)->startOfDay())
            ->count();
        $olderMissed = Call::query()
            ->where('organization_id', $organization->id)
            ->where('status', CallStatus::Missed->value)
            ->where('started_at', '<', now()->subDays(30))
            ->count();

        $recent = $recentMissed >= 4 ? collect() : Call::query()
            ->where('organization_id', $organization->id)
            ->where('provider_code', 'demo')
            ->where('started_at', '>=', now()->subDays(6)->startOfDay())
            ->where(function ($query) use ($protectedCustomerIds): void {
                $query->whereNull('customer_id')
                    ->orWhereNotIn('customer_id', $protectedCustomerIds);
            })
            ->where('status', '!=', CallStatus::Missed->value)
            ->orderByDesc('started_at')
            ->limit(4)
            ->get();

        $older = $olderMissed >= 2 ? collect() : Call::query()
            ->where('organization_id', $organization->id)
            ->where('provider_code', 'demo')
            ->where('started_at', '<', now()->subDays(30))
            ->where('status', '!=', CallStatus::Missed->value)
            ->orderByDesc('started_at')
            ->limit(2)
            ->get();

        foreach ($recent->concat($older) as $call) {
            $call->forceFill([
                'status' => CallStatus::Missed->value,
                'duration_seconds' => 0,
                'ended_at' => $call->started_at,
            ])->saveQuietly();
        }
    }

    private function ensureInternalCalls(Organization $organization, ?OrganizationVoipConnection $connection): void
    {
        $employees = OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($employees->count() < 2) {
            return;
        }

        $pairs = [
            [0, 1, 0, 9, 20],
            [1, 2, 1, 11, 5],
            [3, 4, 3, 16, 40],
            [4, 5, 2, 10, 12],
        ];

        foreach ($pairs as $index => [$from, $to, $daysAgo, $hour, $minute]) {
            $caller = $employees->get($from % $employees->count());
            $receiver = $employees->get($to % $employees->count());
            if (! $caller instanceof OrganizationUser || ! $receiver instanceof OrganizationUser) {
                continue;
            }

            $started = now()->subDays($daysAgo)->setTime($hour, $minute);
            $call = Call::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'provider_code' => 'demo',
                    'external_call_id' => $this->externalCallId($organization->id, 900 + $index),
                ],
                [
                    'organization_user_id' => $caller->id,
                    'organization_voip_connection_id' => $connection?->id,
                    'source' => ConversationSource::Voip,
                    'direction' => 'outbound',
                    'caller_number' => $this->extensionFor($caller),
                    'receiver_number' => $this->extensionFor($receiver),
                    'status' => 'completed',
                    'processing_status' => CallProcessingStatus::Skipped,
                    'started_at' => $started,
                    'ended_at' => $started->copy()->addMinutes(3),
                    'duration_seconds' => 180,
                    'title' => 'هماهنگی شیفت',
                    'category' => 'داخلی',
                    'conversation_date' => $started,
                    'metadata' => ['kpi_window' => 'internal'],
                ],
            );

            CallRecording::query()->updateOrCreate(
                ['call_id' => $call->id],
                [
                    'storage_disk' => 'local',
                    'storage_path' => 'recordings/avayar/'.$call->id.'.wav',
                    'mime_type' => 'audio/wav',
                    'duration_seconds' => 180,
                    'status' => 'completed',
                    'is_expired' => false,
                    'downloaded_at' => $started,
                ],
            );
        }
    }

    private function ensureVoipLogs(Organization $organization, ?OrganizationVoipConnection $connection): void
    {
        if (! $connection instanceof OrganizationVoipConnection) {
            return;
        }

        $extensions = EmployeeIntegrationMeta::query()
            ->where('integratable_type', OrganizationVoipConnection::class)
            ->where('integratable_id', $connection->id)
            ->where('key', 'extension')
            ->pluck('value', 'organization_user_id');

        Call::query()
            ->where('organization_id', $organization->id)
            ->where('source', ConversationSource::Voip)
            ->orderBy('id')
            ->each(function (Call $call) use ($connection, $extensions): void {
                $extension = trim((string) ($extensions[$call->organization_user_id] ?? $call->caller_number));
                $missed = $call->status === CallStatus::Missed->value;
                $outbound = $call->direction === 'outbound';
                $other = $outbound ? (string) $call->receiver_number : (string) $call->caller_number;
                $log = VoipCallLog::query()->updateOrCreate(
                    [
                        'organization_voip_connection_id' => $connection->id,
                        'external_call_id' => (string) $call->external_call_id,
                    ],
                    [
                        'organization_id' => $call->organization_id,
                        'provider_code' => VoipProviderCode::Simotel->value,
                        'direction' => $outbound ? CallDirection::Outbound : CallDirection::Inbound,
                        'source_number' => $outbound ? $extension : $other,
                        'destination_number' => $outbound ? $other : $extension,
                        'status' => $missed ? CallStatus::Missed : CallStatus::Completed,
                        'started_at' => $call->started_at,
                        'ended_at' => $call->ended_at,
                        'duration' => $call->duration_seconds,
                        'raw_payload' => ['extension' => $extension, 'uniqueid' => $call->external_call_id],
                    ],
                );

                $call->forceFill([
                    'organization_voip_connection_id' => $connection->id,
                    'voip_call_log_id' => $log->id,
                ])->saveQuietly();
            });
    }

    private function ensureVoipVerified(?OrganizationVoipConnection $connection): void
    {
        if (! $connection instanceof OrganizationVoipConnection) {
            return;
        }

        if ($connection->syncLogs()->where('operation', VoipOperation::TestConnection)->where('status', VoipLogStatus::Success)->exists()) {
            return;
        }

        VoipSyncLog::query()->create([
            'organization_voip_connection_id' => $connection->id,
            'operation' => VoipOperation::TestConnection,
            'status' => VoipLogStatus::Success,
            'message' => 'اتصال سیموتل دفتر ونک برقرار است.',
            'records_processed' => 0,
            'payload' => ['api_url' => 'https://192.168.10.12/api/v4'],
        ]);
    }

    private function anchorCrmDates(Organization $organization): void
    {
        $won = CrmDeal::query()
            ->where('organization_id', $organization->id)
            ->where('status', DealStatus::Won)
            ->orderBy('id')
            ->get();
        foreach ($won->values() as $index => $deal) {
            $deal->forceFill([
                'closed_at' => now()->subDays([0, 2, 5, 12][$index] ?? 12)->setTime(15, 10),
            ])->saveQuietly();
        }

        $lost = CrmDeal::query()
            ->where('organization_id', $organization->id)
            ->where('status', DealStatus::Lost)
            ->orderBy('id')
            ->get();
        foreach ($lost->values() as $index => $deal) {
            $deal->forceFill([
                'closed_at' => now()->subDays([1, 4, 13][$index] ?? 13)->setTime(17, 40),
            ])->saveQuietly();
        }

        $open = CrmDeal::query()
            ->where('organization_id', $organization->id)
            ->where('status', DealStatus::Open)
            ->orderBy('id')
            ->get();
        foreach ($open->values() as $index => $deal) {
            $created = now()->subDays($index % 7)->setTime(10 + ($index % 6), 20);
            $deal->forceFill([
                'created_at' => $created,
                'updated_at' => $created,
                'last_activity_at' => $created,
                'expected_close_on' => $index === 0
                    ? now()->subDay()->toDateString()
                    : now()->addDays(7 + $index)->toDateString(),
            ])->saveQuietly();
        }

        $issuedInvoices = CrmInvoice::query()
            ->where('organization_id', $organization->id)
            ->where('status', InvoiceStatus::Issued)
            ->orderBy('id')
            ->get();
        $invoiceDay = 0;
        $proformaDay = 0;
        foreach ($issuedInvoices as $invoice) {
            $daysAgo = $invoice->document_type === InvoiceDocumentType::Proforma
                ? [1, 3, 5][$proformaDay++] ?? 5
                : [0, 2, 4, 6, 11][$invoiceDay++] ?? 11;

            $invoice->forceFill([
                'issued_at' => now()->subDays($daysAgo)->setTime(11, 30),
            ])->saveQuietly();
        }

        Customer::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->each(function (Customer $customer, int $index): void {
                $created = now()->subDays($index % 7)->setTime(9, 5 + $index);
                $customer->forceFill([
                    'created_at' => $created,
                    'updated_at' => $created,
                ])->saveQuietly();
            });

        if (! CrmProduct::query()->where('organization_id', $organization->id)->where('type', ProductType::Product)->exists()) {
            $categoryId = CrmProductCategory::query()
                ->where('organization_id', $organization->id)
                ->orderBy('position')
                ->value('id');

            CrmProduct::query()->create([
                'organization_id' => $organization->id,
                'name' => 'هدست اپراتور',
                'description' => 'هدست سیمی برای کارشناسان شیفت صبح و عصر.',
                'type' => ProductType::Product,
                'category_id' => $categoryId,
                'code' => 'HD-OP',
                'price_tomans' => 1_850_000,
                'unit_quantity' => 1,
                'unit_label' => 'عدد',
                'is_active' => true,
                'created_by_user_id' => $organization->user_id,
            ]);
        }
    }

    private function createOrganization(): Organization
    {
        $assigner = new DemoAvatarAssigner;

        $employer = User::query()->create([
            'name' => 'نیما رحیمی',
            'email' => self::EMPLOYER_EMAIL,
            'phone' => '09123061847',
            'password' => Hash::make(self::PASSWORD),
            'role' => UserRole::Employer,
            'email_verified_at' => now(),
            'avatar_path' => $assigner->assign('male'),
        ]);

        $organization = Organization::query()->create([
            'title' => self::TITLE,
            'business_context' => 'آوایار یک مرکز تماس در تهران است که پاسخگویی، فروش تلفنی و پشتیبانی مشتریان را برای شرکت‌های دیگر انجام می‌دهد. تیم فروش پلن‌های ماهانه پاسخگویی، شیفت شب و راه‌اندازی صف تماس می‌فروشد. تیم پشتیبانی تماس‌های ورودی مشتریانِ کارفرماها را جواب می‌دهد. دفتر اصلی در ونک است و پنجشنبه و جمعه تعطیل است.',
            'holiday_weekdays' => [Carbon::THURSDAY, Carbon::FRIDAY],
            'disabled' => false,
            'is_demo' => true,
            'employer_can_manage_integrations' => true,
            'crm_mode' => OrganizationCrmMode::Internal,
            'assign_customer_owner_from_call' => true,
            'user_id' => $employer->id,
        ]);

        return $organization;
    }

    /** @return Collection<int, OrganizationUser> */
    private function ensureEmployees(Organization $organization): Collection
    {
        if ($organization->memberships()->count() >= count($this->employeeProfiles())) {
            return OrganizationUser::query()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();
        }

        $assigner = DemoAvatarAssigner::forOrganization($organization);

        foreach ($this->employeeProfiles() as $profile) {
            $user = User::query()->updateOrCreate(
                ['email' => $profile['email']],
                [
                    'name' => $profile['first_name'].' '.$profile['last_name'],
                    'password' => Hash::make(self::PASSWORD),
                    'role' => UserRole::Employee,
                    'email_verified_at' => now(),
                    'avatar_path' => $assigner->assign($profile['gender']),
                ],
            );

            $membership = OrganizationUser::query()->firstOrCreate([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ]);

            $membership->update([
                'first_name' => $profile['first_name'],
                'last_name' => $profile['last_name'],
                'gender' => $profile['gender'],
                'mobile' => $profile['mobile'],
                'position' => $profile['position'],
                'department' => $profile['department'],
                'is_active' => true,
            ]);
        }

        return OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    /** @param  Collection<int, OrganizationUser>  $employees
     * @return Collection<int, Customer>
     */
    private function ensureCustomers(Organization $organization, Collection $employees): Collection
    {
        $customers = collect();

        foreach ($this->companyProfiles() as $companyProfile) {
            $company = CustomerCompany::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'normalized_name' => CustomerCompany::normalizeName($companyProfile['name']),
                ],
                [
                    'name' => $companyProfile['name'],
                    'industry' => $companyProfile['industry'],
                    'website' => $companyProfile['website'],
                    'phone' => $companyProfile['phone'],
                    'email' => $companyProfile['email'],
                    'address' => $companyProfile['address'],
                    'notes' => $companyProfile['notes'],
                ],
            );

            foreach ($companyProfile['people'] as $person) {
                $owner = $employees->get($person['owner'] % max(1, $employees->count()));
                $customer = Customer::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'normalized_phone' => preg_replace('/\D+/', '', $person['mobile']) ?: $person['mobile'],
                    ],
                    [
                        'customer_company_id' => $company->id,
                        'owner_organization_user_id' => $owner?->id,
                        'lifecycle_stage' => CustomerLifecycleStage::from($person['stage']),
                        'phone_number' => $person['mobile'],
                        'name' => $person['name'],
                        'company_name' => $company->name,
                        'email' => $person['email'],
                        'job_title' => $person['title'],
                        'identity_confidence' => 0.92,
                        'purchase_intent' => $person['intent'],
                        'conversation_trend' => $person['trend'],
                        'recommended_next_action' => $person['next'],
                        'latest_lead_score' => $person['lead'],
                        'latest_lead_level' => $person['lead'] >= 75 ? 'high' : ($person['lead'] >= 45 ? 'medium' : 'low'),
                    ],
                );

                CustomerContactMethod::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'customer_id' => $customer->id,
                        'type' => 'mobile',
                        'normalized_value' => $customer->normalized_phone,
                    ],
                    [
                        'raw_value' => $person['mobile'],
                        'label' => 'موبایل',
                        'is_primary' => true,
                        'is_active' => true,
                        'verification_status' => 'confirmed',
                        'source' => 'manual',
                    ],
                );

                if (filled($person['email'])) {
                    CustomerContactMethod::query()->updateOrCreate(
                        [
                            'organization_id' => $organization->id,
                            'customer_id' => $customer->id,
                            'type' => 'email',
                            'normalized_value' => mb_strtolower($person['email']),
                        ],
                        [
                            'raw_value' => $person['email'],
                            'label' => 'ایمیل کاری',
                            'is_primary' => true,
                            'is_active' => true,
                            'verification_status' => 'confirmed',
                            'source' => 'manual',
                        ],
                    );
                }

                $customers->push($customer);
            }
        }

        return $customers;
    }

    /** @param  Collection<int, OrganizationUser>  $employees
     * @param  Collection<int, Customer>  $customers
     */
    private function ensureCalls(Organization $organization, Collection $employees, Collection $customers): void
    {
        if (Call::query()->where('organization_id', $organization->id)->count() >= self::CALLS) {
            return;
        }

        $model = LlmModel::query()->where('is_active', true)->where('is_default', true)->first()
            ?? LlmModel::query()->where('is_active', true)->first();

        for ($index = 1; $index <= self::CALLS; $index++) {
            $employee = $employees->get(($index - 1) % $employees->count());
            $customer = $customers->get(($index - 1) % $customers->count());

            if (! $employee instanceof OrganizationUser || ! $customer instanceof Customer) {
                continue;
            }

            $faker = fake();
            $faker->seed($organization->id * 17_771 + $index);
            $startedAt = $this->startedAt($index, $faker);
            $duration = $faker->numberBetween(145, 860);
            $endedAt = $startedAt->copy()->addSeconds($duration);
            $direction = $index % 3 === 0 ? 'outbound' : 'inbound';
            $score = max(48, min(97, 58 + ($employee->id % 6) * 5 + ($index % 5) * 3 + $faker->numberBetween(-4, 7)));
            $sentiment = match (true) {
                $score >= 85 => AnalysisSentiment::Positive,
                $score >= 70 => AnalysisSentiment::Neutral,
                $score >= 55 => AnalysisSentiment::Mixed,
                default => AnalysisSentiment::Negative,
            };
            $manual = $index % 11 === 0;
            $content = $this->content->build(
                seed: $organization->id * 17_771 + $index,
                score: $score,
                sentiment: $sentiment,
                employee: $employee,
                customer: $customer,
                direction: $direction,
                durationSeconds: $duration,
                organizationTitle: self::TITLE,
            );

            $extension = $this->extensionFor($employee);
            $call = Call::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'provider_code' => 'demo',
                    'external_call_id' => $this->externalCallId($organization->id, $index),
                ],
                [
                    'organization_user_id' => $employee->id,
                    'customer_id' => $customer->id,
                    'source' => $manual ? ConversationSource::ManualUpload : ConversationSource::Voip,
                    'uploader_id' => $manual ? $organization->user_id : null,
                    'uploader_type' => $manual ? UploaderType::Employer : null,
                    'direction' => $direction,
                    'caller_number' => $direction === 'inbound' ? $customer->phone_number : $extension,
                    'receiver_number' => $direction === 'inbound' ? $extension : $customer->phone_number,
                    'customer_name' => $customer->displayName(),
                    'status' => $content['call_status'],
                    'processing_status' => CallProcessingStatus::Analyzed,
                    'started_at' => $startedAt,
                    'ended_at' => $endedAt,
                    'duration_seconds' => $duration,
                    'title' => $content['call_title'],
                    'category' => $content['call_category'],
                    'notes' => $content['call_notes'],
                    'metadata' => $content['metadata'],
                    'conversation_date' => $startedAt,
                ],
            );

            $analyzedAt = $endedAt->copy()->addMinutes($faker->numberBetween(2, 11));
            $inputTokens = $faker->numberBetween(2800, 7400);
            $outputTokens = $faker->numberBetween(520, 1600);

            ConversationAnalysis::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'call_id' => $call->id,
                ],
                [
                    'organization_user_id' => $employee->id,
                    'source' => $call->source,
                    'llm_provider' => $model?->provider?->code ?? 'openai',
                    'model_name' => $model?->model_key ?? 'gpt-4o-mini',
                    'llm_model_id' => $model?->id,
                    'prompt_version' => 'v1',
                    'score' => $score,
                    'is_evaluable' => true,
                    'is_personal' => false,
                    'summary' => $content['summary'],
                    'transcript' => $content['transcript'],
                    'sentiment' => $sentiment,
                    'overall_evaluation' => $content['overall_evaluation'],
                    'strengths_json' => $content['strengths_json'],
                    'weaknesses_json' => $content['weaknesses_json'],
                    'next_actions_json' => $content['next_actions_json'],
                    'performance_dimensions_json' => $content['performance_dimensions_json'],
                    'customer_insights_json' => $content['customer_insights_json'],
                    'operational_insights_json' => $content['operational_insights_json'],
                    'lead_quality_json' => $content['lead_quality_json'],
                    'concerns_json' => $content['concerns_json'],
                    'needs_attention' => $content['needs_attention'],
                    'attention_json' => $content['attention_json']['needed'] ? $content['attention_json'] : null,
                    'customer_identity_json' => $content['customer_identity_json'],
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'total_tokens' => $inputTokens + $outputTokens,
                    'processing_duration_ms' => $faker->numberBetween(1600, 4200),
                    'analyzed_at' => $analyzedAt,
                ],
            );

            if ($manual) {
                $this->processingJob($organization, $call, $startedAt);
            }
        }

        $this->syncCustomerStats($organization);
        $this->seedActivities($organization);
    }

    private function processingJob(Organization $organization, Call $call, Carbon $started): void
    {
        $failed = $call->id % 17 === 0;
        CallProcessingJob::query()->updateOrCreate(
            [
                'call_id' => $call->id,
                'file_name' => 'rec-'.$started->format('Ymd-Hi').'-'.$call->id.'.wav',
            ],
            [
                'job_uuid' => (string) Str::uuid(),
                'organization_id' => $organization->id,
                'organization_user_id' => $call->organization_user_id,
                'uploader_id' => $organization->user_id,
                'status' => $failed ? ProcessingJobStatus::Failed : ProcessingJobStatus::Completed,
                'stage' => $failed ? ProcessingJobStage::ProcessingResult : ProcessingJobStage::Completed,
                'progress_percentage' => $failed ? 85 : 100,
                'upload_started_at' => $started,
                'upload_completed_at' => $started->copy()->addSeconds(18),
                'queued_at' => $started->copy()->addSeconds(22),
                'processing_started_at' => $started->copy()->addMinute(),
                'completed_at' => $started->copy()->addMinutes(4),
                'waiting_seconds' => 38,
                'processing_duration_seconds' => 96,
                'error_message' => $failed ? 'بخشی از فایل صوتی سکوت بود و متن کامل درنیامد.' : null,
            ],
        );
    }

    private function syncCustomerStats(Organization $organization): void
    {
        $customers = Customer::query()->where('organization_id', $organization->id)->get();

        foreach ($customers as $customer) {
            $calls = Call::query()->where('customer_id', $customer->id)->get();
            $latest = ConversationAnalysis::query()
                ->whereIn('call_id', $calls->pluck('id'))
                ->orderByDesc('analyzed_at')
                ->first();

            $customer->update([
                'total_calls' => $calls->count(),
                'total_answered_calls' => $calls->where('status', 'completed')->count(),
                'first_contact_at' => $calls->min('started_at'),
                'last_contact_at' => $calls->max('started_at'),
                'latest_lead_score' => $latest?->lead_quality_json['score'] ?? $customer->latest_lead_score,
                'latest_lead_level' => $latest?->lead_quality_json['level'] ?? $customer->latest_lead_level,
            ]);
        }

        CustomerCompany::query()
            ->where('organization_id', $organization->id)
            ->get()
            ->each(fn (CustomerCompany $company) => $this->companies->refreshAggregates($company));
    }

    private function seedActivities(Organization $organization): void
    {
        if (OrganizationActivity::query()->where('organization_id', $organization->id)->exists()) {
            return;
        }

        ConversationAnalysis::query()
            ->where('organization_id', $organization->id)
            ->with(['call.customer', 'employee'])
            ->latest('analyzed_at')
            ->limit(8)
            ->get()
            ->each(function (ConversationAnalysis $analysis): void {
                $customer = $analysis->call?->customer?->displayName()
                    ?? $analysis->call?->customer_name
                    ?? 'مشتری';

                $activity = OrganizationActivity::query()->create([
                    'organization_id' => $analysis->organization_id,
                    'user_id' => $analysis->employee?->user_id,
                    'type' => 'analysis_completed',
                    'title' => 'تحلیل تماس تکمیل شد',
                    'description' => "مکالمه با {$customer} با امتیاز {$analysis->score} ثبت شد.",
                    'metadata' => [
                        'source' => 'demo_seeder',
                        'analysis_id' => $analysis->id,
                        'call_id' => $analysis->call_id,
                    ],
                ]);
                $activity->created_at = $analysis->analyzed_at;
                $activity->updated_at = $analysis->analyzed_at;
                $activity->saveQuietly();
            });
    }

    /** @param  Collection<int, OrganizationUser>  $employees */
    private function ensureVoip(Organization $organization, Collection $employees): void
    {
        $provider = VoipProvider::query()->where('code', VoipProviderCode::Simotel->value)->first();

        if (! $provider) {
            return;
        }

        $connection = OrganizationVoipConnection::query()->firstOrNew([
            'organization_id' => $organization->id,
            'name' => 'سیموتل دفتر ونک',
        ]);

        $connection->fill([
            'voip_provider_id' => $provider->id,
            'credentials' => [
                'api_url' => 'https://192.168.10.12/api/v4',
                'api_key' => '7c2e91ab40d64f18a6e0c3b85d19f472',
            ],
            'settings' => ['extension_mapping' => []],
            'is_default' => true,
            'is_active' => true,
            'ingestion_mode' => 'webhook',
            'polling_enabled' => false,
        ]);
        $connection->save();

        $extensions = [];
        foreach ($employees->values() as $employee) {
            $extension = $this->extensionFor($employee);
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

        Call::query()
            ->where('organization_id', $organization->id)
            ->where('source', ConversationSource::Voip)
            ->orderByDesc('started_at')
            ->limit(24)
            ->get()
            ->each(function (Call $call) use ($connection, $extensions): void {
                $extension = $extensions[$call->organization_user_id] ?? '214';
                $outbound = $call->direction === 'outbound';
                $customerNumber = $outbound ? (string) $call->receiver_number : (string) $call->caller_number;
                $log = VoipCallLog::query()->updateOrCreate(
                    [
                        'organization_voip_connection_id' => $connection->id,
                        'external_call_id' => (string) $call->external_call_id,
                    ],
                    [
                        'organization_id' => $call->organization_id,
                        'provider_code' => VoipProviderCode::Simotel->value,
                        'direction' => $outbound ? CallDirection::Outbound : CallDirection::Inbound,
                        'source_number' => $outbound ? $extension : $customerNumber,
                        'destination_number' => $outbound ? $customerNumber : $extension,
                        'status' => CallStatus::Completed,
                        'started_at' => $call->started_at,
                        'ended_at' => $call->ended_at,
                        'duration' => $call->duration_seconds,
                        'raw_payload' => ['extension' => $extension, 'uniqueid' => $call->external_call_id],
                    ],
                );

                $call->organization_voip_connection_id = $connection->id;
                $call->voip_call_log_id = $log->id;
                $call->saveQuietly();
            });
    }

    /** @param  Collection<int, OrganizationUser>  $employees
     * @param  Collection<int, Customer>  $customers
     */
    private function ensureCrm(Organization $organization, Collection $employees, Collection $customers): void
    {
        if (CrmPipeline::query()->where('organization_id', $organization->id)->where('name', self::PIPELINE)->exists()) {
            return;
        }

        DB::transaction(function () use ($organization, $employees, $customers): void {
            $sales = $this->pipeline($organization, self::PIPELINE, 'فروش پلن پاسخگویی و راه‌اندازی مرکز تماس.', true, PipelineStagePalette::DEFAULT_STAGES);
            $renewal = $this->pipeline($organization, 'تمدید قرارداد', 'تمدید قراردادهای در حال اتمام.', false, [
                ['name' => 'بررسی تمدید', 'color' => '#6366F1'],
                ['name' => 'پیشنهاد تمدید', 'color' => '#7C25FD'],
                ['name' => 'مذاکره تمدید', 'color' => '#D97706'],
            ]);
            $salesStages = $sales->stages()->orderBy('position')->get()->values();
            $renewalStages = $renewal->stages()->orderBy('position')->get()->values();
            $deals = [];

            foreach ($this->dealSpecs() as $index => $spec) {
                $customer = $customers->get($spec['person']);
                if (! $customer instanceof Customer) {
                    continue;
                }

                $stages = $spec['pipeline'] === 'renewal' ? $renewalStages : $salesStages;
                $pipeline = $spec['pipeline'] === 'renewal' ? $renewal : $sales;
                $stage = $stages->get($spec['stage']) ?? $stages->first();
                $owner = $spec['owner'] === null ? null : $employees->get($spec['owner'] % max(1, $employees->count()));
                $status = DealStatus::from($spec['status']);

                $deal = CrmDeal::query()->create([
                    'organization_id' => $organization->id,
                    'crm_pipeline_id' => $pipeline->id,
                    'crm_pipeline_stage_id' => $stage->id,
                    'customer_company_id' => $customer->customer_company_id,
                    'owner_organization_user_id' => $owner?->id,
                    'title' => $spec['title'],
                    'description' => $spec['description'],
                    'amount_rials' => $spec['amount'],
                    'currency' => 'IRR',
                    'status' => $status,
                    'source' => DealSource::Manual,
                    'expected_close_on' => $status === DealStatus::Open ? now()->addDays($spec['close_in'])->toDateString() : null,
                    'closed_at' => $status === DealStatus::Open ? null : now()->subDays($spec['closed_days']),
                    'lost_reason' => $status === DealStatus::Lost ? $spec['lost_reason'] : null,
                    'won_reason' => $status === DealStatus::Won ? $spec['won_reason'] : null,
                    'last_activity_at' => now()->subDays($spec['activity_days']),
                    'stage_entered_at' => now()->subDays($spec['activity_days']),
                    'next_action_text' => $status === DealStatus::Open ? $spec['next'] : null,
                    'next_action_due_on' => $status === DealStatus::Open ? now()->addDays(min(3, $spec['close_in']))->toDateString() : null,
                    'version' => 1,
                ]);

                CrmDealContact::query()->create([
                    'organization_id' => $organization->id,
                    'crm_deal_id' => $deal->id,
                    'customer_id' => $customer->id,
                    'is_primary' => true,
                ]);

                $actorId = (int) ($owner?->user_id ?: $organization->user_id);
                $this->dealAudit->stage($deal, null, $stage, null, $status->value, $actorId, DealSource::Manual->value);
                $this->dealAudit->record($deal, DealActivityType::Created, $actorId, DealSource::Manual->value, null, [
                    'title' => $deal->title,
                ]);

                if ($status === DealStatus::Won) {
                    $this->dealAudit->record($deal, DealActivityType::Won, $actorId, DealSource::Manual->value);
                }

                if ($status === DealStatus::Lost) {
                    $this->dealAudit->record($deal, DealActivityType::Lost, $actorId, DealSource::Manual->value);
                }

                $call = Call::query()
                    ->where('organization_id', $organization->id)
                    ->where('customer_id', $customer->id)
                    ->latest('started_at')
                    ->first();

                if ($call instanceof Call) {
                    CrmDealCall::query()->create([
                        'organization_id' => $organization->id,
                        'crm_deal_id' => $deal->id,
                        'call_id' => $call->id,
                        'conversation_analysis_id' => $call->conversationAnalysis?->id,
                        'source' => DealSource::Analysis->value,
                        'evidence_json' => $call->conversationAnalysis
                            ? AnalysisDealEvidence::from($call->conversationAnalysis)
                            : ['summary' => $deal->description],
                        'linked_by_user_id' => $actorId,
                    ]);
                }

                $deals[] = $deal;
            }

            $this->ensureTasks($organization, $employees, $customers, $deals);
            $this->ensureCatalog($organization, $customers);
            $this->ensureSuggestion($organization, $customers);
        });
    }

    /**
     * @param  list<array{name: string, color: string}>  $stages
     */
    private function pipeline(Organization $organization, string $name, string $description, bool $default, array $stages): CrmPipeline
    {
        $pipeline = CrmPipeline::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'description' => $description,
            'is_default' => $default,
            'is_active' => true,
            'show_win_probability' => $default,
        ]);

        foreach (array_values($stages) as $position => $stage) {
            CrmPipelineStage::query()->create([
                'organization_id' => $organization->id,
                'crm_pipeline_id' => $pipeline->id,
                'name' => $stage['name'],
                'color' => $stage['color'],
                'position' => $position + 1,
                'is_active' => true,
            ]);
        }

        return $pipeline;
    }

    /**
     * @param  Collection<int, OrganizationUser>  $employees
     * @param  Collection<int, Customer>  $customers
     * @param  list<CrmDeal>  $deals
     */
    private function ensureTasks(Organization $organization, Collection $employees, Collection $customers, array $deals): void
    {
        foreach ($this->taskSpecs() as $spec) {
            $customer = $customers->get($spec['person']);
            $deal = $deals[$spec['deal']] ?? null;
            $assignee = $employees->get($spec['owner'] % max(1, $employees->count()));
            $status = TaskStatus::from($spec['status']);

            CrmTask::query()->create([
                'organization_id' => $organization->id,
                'title' => $spec['title'],
                'description' => $spec['description'],
                'status' => $status,
                'priority' => TaskPriority::from($spec['priority']),
                'source' => TaskSource::from($spec['source']),
                'assignee_organization_user_id' => $assignee?->id,
                'created_by_user_id' => $organization->user_id,
                'customer_id' => $customer?->id,
                'customer_company_id' => $customer?->customer_company_id,
                'crm_deal_id' => $deal?->id,
                'due_at' => now()->addDays($spec['due_in'])->setTime($spec['hour'], $spec['minute']),
                'due_source' => TaskDueBasis::Human,
                'completed_at' => $status === TaskStatus::Completed ? now()->subDays(abs(min(0, $spec['due_in'])) + 1)->setTime(16, 20) : null,
                'completed_by_user_id' => $status === TaskStatus::Completed ? $organization->user_id : null,
                'completion_outcome' => $status === TaskStatus::Completed ? $spec['outcome'] : null,
                'version' => 1,
            ]);
        }
    }

    /** @param  Collection<int, Customer>  $customers */
    private function ensureCatalog(Organization $organization, Collection $customers): void
    {
        $categories = [
            'plans' => CrmProductCategory::query()->create([
                'organization_id' => $organization->id,
                'name' => 'پلن‌های پاسخگویی',
                'slug' => 'answering-plans',
                'icon' => 'users',
                'tone' => 'violet',
                'position' => 10,
                'is_system' => false,
            ]),
            'setup' => CrmProductCategory::query()->create([
                'organization_id' => $organization->id,
                'name' => 'راه‌اندازی و آموزش',
                'slug' => 'setup-training',
                'icon' => 'gear',
                'tone' => 'amber',
                'position' => 20,
                'is_system' => false,
            ]),
        ];

        $products = [];
        foreach ($this->productSpecs() as $key => $spec) {
            $products[$key] = CrmProduct::query()->create([
                'organization_id' => $organization->id,
                'name' => $spec['name'],
                'description' => $spec['description'],
                'type' => ProductType::from($spec['type']),
                'category_id' => $categories[$spec['category']]->id,
                'code' => $spec['code'],
                'price_tomans' => $spec['tomans'],
                'unit_quantity' => 1,
                'unit_label' => $spec['unit'],
                'is_active' => true,
                'created_by_user_id' => $organization->user_id,
            ]);
        }

        foreach ($this->invoiceSpecs() as $spec) {
            $customer = $customers->get($spec['person']);
            $lines = [];
            $subtotal = 0;

            foreach ($spec['lines'] as $line) {
                $product = $products[$line['product']];
                $unit = (int) $product->price_tomans * 10;
                $amount = $unit * $line['qty'];
                $subtotal += $amount;
                $lines[] = [
                    'product' => $product,
                    'description' => $line['description'] ?? $product->name,
                    'quantity' => $line['qty'],
                    'unit' => $unit,
                    'amount' => $amount,
                    'unit_label' => $product->unit_label,
                ];
            }

            $taxPercent = 10;
            $tax = (int) round($subtotal * $taxPercent / 100);
            $invoice = CrmInvoice::query()->create([
                'organization_id' => $organization->id,
                'document_type' => InvoiceDocumentType::from($spec['type']),
                'status' => InvoiceStatus::from($spec['status']),
                'number' => $spec['number'],
                'issued_at' => now()->subDays($spec['days_ago'])->setTime(11, 30),
                'customer_id' => $customer?->id,
                'buyer_name' => $customer?->name,
                'buyer_company' => $customer?->company_name,
                'buyer_phone' => $customer?->phone_number,
                'buyer_national_id' => $spec['national_id'],
                'buyer_economic_code' => $spec['economic_code'],
                'buyer_address' => $spec['address'],
                'notes' => $spec['notes'],
                'tax_percent' => $taxPercent,
                'subtotal_rials' => $subtotal,
                'tax_rials' => $tax,
                'total_rials' => $subtotal + $tax,
                'notify_sms_on_issue' => false,
                'created_by_user_id' => $organization->user_id,
            ]);

            foreach ($lines as $order => $line) {
                CrmInvoiceLine::query()->create([
                    'organization_id' => $organization->id,
                    'crm_invoice_id' => $invoice->id,
                    'crm_product_id' => $line['product']->id,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_label' => $line['unit_label'],
                    'unit_price_rials' => $line['unit'],
                    'amount_rials' => $line['amount'],
                    'sort_order' => $order + 1,
                ]);
            }
        }
    }

    /** @param  Collection<int, Customer>  $customers */
    private function ensureSuggestion(Organization $organization, Collection $customers): void
    {
        $customer = $customers->first(fn (Customer $customer): bool => ! CrmDealContact::query()
            ->where('customer_id', $customer->id)
            ->exists());

        if (! $customer instanceof Customer) {
            return;
        }

        $analysis = ConversationAnalysis::query()
            ->where('organization_id', $organization->id)
            ->whereHas('call', fn ($query) => $query->where('customer_id', $customer->id))
            ->latest('analyzed_at')
            ->first();

        if (! $analysis instanceof ConversationAnalysis) {
            return;
        }

        CrmDealAiSuggestion::query()->create([
            'organization_id' => $organization->id,
            'conversation_analysis_id' => $analysis->id,
            'customer_id' => $customer->id,
            'call_id' => $analysis->call_id,
            'suggestion_type' => DealSuggestionType::CreateDeal,
            'status' => DealSuggestionStatus::Pending,
            'proposed_title' => 'فرصت فروش پاسخگویی — '.$customer->company_name,
            'payload' => AnalysisDealEvidence::from($analysis) + [
                'auto_create' => false,
                'reason' => 'no_open_deal',
                'candidate_deal_ids' => [],
                'summary' => $analysis->summary,
            ],
        ]);
    }

    private function extensionFor(OrganizationUser $employee): string
    {
        foreach ($this->employeeProfiles() as $profile) {
            if ($employee->first_name === $profile['first_name'] && $employee->last_name === $profile['last_name']) {
                return $profile['extension'];
            }
        }

        return '214';
    }

    private function externalCallId(int $organizationId, int $index): string
    {
        return (1760400000 + ($organizationId * 100) + $index).'.'.(310 + $index);
    }

    private function startedAt(int $index, Generator $faker): Carbon
    {
        if ($index <= self::CALLS_TODAY) {
            $started = now()->copy()->startOfDay()
                ->addHours($faker->numberBetween(8, 17))
                ->addMinutes($faker->numberBetween(0, 59));

            if ($started->greaterThan(now())) {
                $started = now()->copy()->subMinutes(12 + ($index * 18));
            }

            return $started;
        }

        $daysAgo = 1 + (($index - self::CALLS_TODAY - 1) % 27);

        return now()->subDays($daysAgo)->startOfDay()
            ->addHours($faker->numberBetween(8, 18))
            ->addMinutes($faker->numberBetween(0, 59));
    }

    /** @return array<string, mixed> */
    private function summary(Organization $organization, bool $created): array
    {
        $employeeEmail = User::query()
            ->whereIn('id', $organization->memberships()->pluck('user_id'))
            ->orderBy('id')
            ->value('email');

        return [
            'created' => $created,
            'title' => $organization->title,
            'employer_email' => self::EMPLOYER_EMAIL,
            'employee_email' => (string) $employeeEmail,
            'password' => self::PASSWORD,
            'customers' => Customer::query()->where('organization_id', $organization->id)->count(),
            'companies' => CustomerCompany::query()->where('organization_id', $organization->id)->count(),
            'calls' => Call::query()->where('organization_id', $organization->id)->count(),
            'deals' => CrmDeal::query()->where('organization_id', $organization->id)->count(),
            'tasks' => CrmTask::query()->where('organization_id', $organization->id)->count(),
            'invoices' => CrmInvoice::query()->where('organization_id', $organization->id)->count(),
        ];
    }

    /** @return list<array<string, string>> */
    private function employeeProfiles(): array
    {
        return [
            ['first_name' => 'سارا', 'last_name' => 'محمدی', 'email' => 'sara.mohammadi@avayar.org', 'gender' => 'female', 'mobile' => '09124731856', 'position' => 'سرپرست فروش', 'department' => 'فروش', 'extension' => '214'],
            ['first_name' => 'حسین', 'last_name' => 'کریمی', 'email' => 'hossein.karimi@avayar.org', 'gender' => 'male', 'mobile' => '09351284763', 'position' => 'کارشناس فروش', 'department' => 'فروش', 'extension' => '218'],
            ['first_name' => 'مریم', 'last_name' => 'اکبری', 'email' => 'maryam.akbari@avayar.org', 'gender' => 'female', 'mobile' => '09193650218', 'position' => 'کارشناس فروش', 'department' => 'فروش', 'extension' => '221'],
            ['first_name' => 'رضا', 'last_name' => 'نوری', 'email' => 'reza.nouri@avayar.org', 'gender' => 'male', 'mobile' => '09213874615', 'position' => 'کارشناس پشتیبانی', 'department' => 'پشتیبانی', 'extension' => '305'],
            ['first_name' => 'الهام', 'last_name' => 'صادقی', 'email' => 'elham.sadeghi@avayar.org', 'gender' => 'female', 'mobile' => '09367421098', 'position' => 'کارشناس ارشد پشتیبانی', 'department' => 'پشتیبانی', 'extension' => '312'],
            ['first_name' => 'امیرحسین', 'last_name' => 'قاسمی', 'email' => 'amirhossein.ghasemi@avayar.org', 'gender' => 'male', 'mobile' => '09102158347', 'position' => 'سرپرست پشتیبانی', 'department' => 'پشتیبانی', 'extension' => '301'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function companyProfiles(): array
    {
        return [
            $this->company('بازرگانی سپهر تجارت', 'بازرگانی', 'https://sepehrtejarat.com', '02188742615', 'info@sepehrtejarat.com', 'تهران، سعادت‌آباد، میدان کاج، برج تجاری پارس، طبقه ۶', 'sepehrtejarat.com', 'قرارداد پاسخگویی روز از تیر تمدید شده و فروش شب در حال مذاکره است.', [
                ['کامران توکلی', 'مدیرعامل', '09121284736', 'k.tavakoli', 'customer', 0, 'بالا', 'stable', 'هماهنگی جلسه تمدید', 82],
                ['نگار سلیمانی', 'مدیر مالی', '09126551904', 'n.soleimani', 'customer', 1, 'متوسط', 'stable', 'ارسال پیش‌فاکتور شیفت شب', 61],
            ]),
            $this->company('کلینیک دندانپزشکی ونک', 'سلامت', 'https://vanakdental.ir', '02188653420', 'hello@vanakdental.ir', 'تهران، ونک، خیابان خدامی، پلاک ۱۸', 'vanakdental.ir', 'نوبت‌دهی تلفنی را آوایار جواب می‌دهد. صف ظهرها شلوغ است.', [
                ['دکتر شقایق مرادی', 'مدیر کلینیک', '09123374018', 's.moradi', 'customer', 3, 'بالا', 'improving', 'بررسی گزارش تماس‌های بی‌پاسخ', 77],
                ['هستی راد', 'مسئول پذیرش', '09302261845', 'h.rad', 'customer', 4, 'متوسط', 'stable', 'آموزش سناریوی نوبت‌دهی', 54],
            ]),
            $this->company('آموزشگاه زبان گویا', 'آموزش', 'https://gouya-language.ir', '02166918473', 'office@gouya-language.ir', 'تهران، یوسف‌آباد، خیابان شانزدهم، پلاک ۷', 'gouya-language.ir', 'ثبت‌نام ترم پاییز از تماس‌های ورودی می‌آید.', [
                ['بهاره نیک‌فر', 'مدیر آموزش', '09125190638', 'b.nikfar', 'qualified', 1, 'بالا', 'improving', 'ارسال پیشنهاد ترم پاییز', 86],
                ['آرمان خسروی', 'مسئول ثبت‌نام', '09369021457', 'a.khosravi', 'contacted', 2, 'متوسط', 'stable', 'تماس پیگیری شهریه', 58],
            ]),
            $this->company('فروشگاه اینترنتی خانه‌کالا', 'خرده‌فروشی', 'https://khanekala.shop', '02191098421', 'support@khanekala.shop', 'تهران، جردن، خیابان شهید دستگردی، پلاک ۴۲', 'khanekala.shop', 'پشتیبانی سفارش و مرجوعی را شیفت عصر پوشش می‌دهد.', [
                ['پویا اعتمادی', 'مدیر عملیات', '09122048617', 'p.etemadi', 'customer', 5, 'بالا', 'stable', 'بازبینی تماس‌های مرجوعی', 73],
                ['مینا افشار', 'سرپرست پشتیبانی', '09205173864', 'm.afshar', 'customer', 4, 'متوسط', 'declining', 'تماس درباره تأخیر ارسال', 49],
                ['کیانوش جعفری', 'مدیر مالی', '09197731420', 'k.jafari', 'customer', 0, 'متوسط', 'stable', 'تأیید فاکتور مهر', 55],
            ]),
            $this->company('حمل‌ونقل ره‌آورد بار', 'لجستیک', 'https://rahavardbar.com', '02155671840', 'sales@rahavardbar.com', 'تهران، شورآباد، شهرک صنعتی، خیابان چهارم', 'rahavardbar.com', 'پیگیری بار و نارضایتی از تأخیر، موضوع اصلی تماس‌هاست.', [
                ['مجید پناهی', 'مدیر بازرگانی', '09126180453', 'm.panahi', 'qualified', 0, 'بالا', 'improving', 'جلسه معرفی شیفت شب', 81],
                ['سمیرا دولت‌آبادی', 'کارشناس مشتریان', '09354471026', 's.dolatabadi', 'contacted', 3, 'پایین', 'stable', 'ارسال نمونه گزارش', 42],
            ]),
            $this->company('آزمایشگاه تشخیص طبی نیکان', 'سلامت', 'https://nikanlab.ir', '02122614580', 'info@nikanlab.ir', 'تهران، پاسداران، بوستان دوم، پلاک ۱۱', 'nikanlab.ir', 'جواب‌دهی و نوبت نمونه‌گیری تلفنی انجام می‌شود.', [
                ['دکتر رضا کاشانی', 'مسئول فنی', '09123097514', 'r.kashani', 'customer', 5, 'متوسط', 'stable', 'هماهنگی اسکریپت جواب‌دهی', 64],
                ['لیلا برومند', 'مدیر پذیرش', '09194421860', 'l.boroumand', 'customer', 4, 'متوسط', 'improving', 'پیگیری آموزش کارشناسان', 60],
            ]),
            $this->company('آژانس مسافرتی پرواز آبی', 'گردشگری', 'https://parvazabi.com', '02188835619', 'book@parvazabi.com', 'تهران، میرداماد، برج ناهید، واحد ۸۰۴', 'parvazabi.com', 'فروش تور نوروز و پشتیبانی کنسلی.', [
                ['یاسمن حاتمی', 'مدیر فروش', '09126843017', 'y.hatami', 'qualified', 2, 'بالا', 'improving', 'ارسال پیش‌فاکتور نوروز', 88],
                ['سینا مرادیان', 'کارشناس تور', '09336190248', 's.moradian', 'contacted', 1, 'متوسط', 'stable', 'تماس معرفی پلن فصلی', 57],
            ]),
            $this->company('داده‌پردازان شرق', 'نرم‌افزار', 'https://dadehpardazan.net', '05137621490', 'hello@dadehpardazan.net', 'مشهد، احمدآباد، خیابان عارف، پلاک ۲۶', 'dadehpardazan.net', 'پشتیبانی نرم‌افزار حسابداری مشتریان خرد.', [
                ['فرهاد یزدان‌پناه', 'مدیر محصول', '09155120836', 'f.yazdanpanah', 'new_lead', 1, 'متوسط', 'stable', 'تماس نیازسنجی', 63],
                ['حدیثه مظفری', 'مدیر پشتیبانی', '09385541067', 'h.mozaffari', 'contacted', 5, 'بالا', 'improving', 'دمو پنل گزارش', 79],
            ]),
            $this->company('لبنیات سحر', 'صنایع غذایی', 'https://sahardairy.ir', '03135261804', 'crm@sahardairy.ir', 'اصفهان، شهرک صنعتی جی، خیابان هفتم', 'sahardairy.ir', 'پاسخگویی به فروشگاه‌ها درباره سفارش و مرجوعی.', [
                ['ناصر قریشی', 'مدیر فروش', '09133264085', 'n.ghoreishi', 'qualified', 0, 'بالا', 'stable', 'مذاکره قرارداد سالانه', 84],
                ['آزاده کیانی', 'کارشناس پخش', '09367210548', 'a.kiani', 'contacted', 2, 'متوسط', 'stable', 'هماهنگی شیفت صبح', 52],
            ]),
            $this->company('مجموعه ورزشی آسمان', 'ورزش', 'https://asemansport.ir', '02122307641', 'club@asemansport.ir', 'تهران، نیاوران، خیابان باهنر، کوچه صالحی', 'asemansport.ir', 'رزرو سانس و تمدید عضویت.', [
                ['روژان عبدی', 'مدیر مجموعه', '09122176490', 'r.abdi', 'customer', 2, 'متوسط', 'improving', 'بازبینی اسکریپت تمدید', 68],
                ['مهدی سلطانی', 'مسئول پذیرش', '09224761035', 'm.soltani', 'customer', 3, 'پایین', 'stable', 'آموزش ثبت عضویت', 40],
            ]),
            $this->company('پخش مویرگی بهار', 'پخش', 'https://bahardistribution.com', '02155290418', 'info@bahardistribution.com', 'تهران، شوش، خیابان فدائیان اسلام', 'bahardistribution.com', 'سفارش تلفنی سوپرمارکت‌ها.', [
                ['حمیدرضا نعمتی', 'مدیر توسعه', '09126139074', 'h.nemati', 'qualified', 0, 'بالا', 'improving', 'جلسه قیمت‌گذاری پلن', 83],
                ['فاطمه رنجبر', 'سرپرست سفارشات', '09352018643', 'f.ranjbar', 'contacted', 4, 'متوسط', 'stable', 'ارسال نمونه گزارش روزانه', 59],
            ]),
            $this->company('آریا سازه پارس', 'ساختمان', 'https://ariasazehpars.com', '02188021476', 'office@ariasazehpars.com', 'تهران، ملاصدرا، خیابان شیراز جنوبی، پلاک ۳۳', 'ariasazehpars.com', 'پاسخ به تماس خریداران واحدهای پروژه لاله.', [
                ['بهروز کامرانی', 'مدیر فروش پروژه', '09121054683', 'b.kamrani', 'new_lead', 1, 'بالا', 'stable', 'نیازسنجی حجم تماس', 74],
                ['شیرین دادگر', 'کارشناس فروش', '09196624108', 's.dadgar', 'contacted', 2, 'متوسط', 'improving', 'تماس پیگیری پیشنهاد', 66],
            ]),
            $this->company('کلینیک زیبایی رویال', 'سلامت', 'https://royalclinic.ir', '02126401853', 'reserve@royalclinic.ir', 'تهران، فرمانیه، خیابان لواسانی، پلاک ۹', 'royalclinic.ir', 'نوبت‌دهی خدمات زیبایی. حساسیت به لحن کارشناس بالا است.', [
                ['دکتر الناز فروتن', 'مدیر کلینیک', '09122860417', 'e.foroutan', 'qualified', 5, 'بالا', 'stable', 'دمو سناریوی نوبت', 80],
                ['پارمیدا شاهی', 'مسئول هماهنگی', '09331450826', 'p.shahi', 'contacted', 4, 'متوسط', 'declining', 'رسیدگی به شکایت لحن', 46],
            ]),
            $this->company('استودیو معماری خط', 'معماری', 'https://khatstudio.co', '02188719024', 'studio@khatstudio.co', 'تهران، زعفرانیه، خیابان اعجازی، پلاک ۱۴', 'khatstudio.co', 'هماهنگی جلسه طراحی و پیگیری پیشنهاد مالی.', [
                ['سهیل مهاجر', 'مدیر استودیو', '09125318046', 's.mohajer', 'inactive', 1, 'پایین', 'declining', 'یک تماس خداحافظی مودبانه', 28],
                ['ترانه وکیلی', 'مدیر پروژه', '09213890517', 't.vakili', 'inactive', 2, 'پایین', 'declining', 'بستن پرونده پیگیری', 31],
            ]),
        ];
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: int, 6: string, 7: string, 8: string, 9: int}>  $people
     * @return array<string, mixed>
     */
    private function company(
        string $name,
        string $industry,
        string $website,
        string $phone,
        string $email,
        string $address,
        string $domain,
        string $notes,
        array $people,
    ): array {
        return [
            'name' => $name,
            'industry' => $industry,
            'website' => $website,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'notes' => $notes,
            'people' => array_map(fn (array $person): array => [
                'name' => $person[0],
                'title' => $person[1],
                'mobile' => $person[2],
                'email' => $person[3].'@'.$domain,
                'stage' => $person[4],
                'owner' => $person[5],
                'intent' => $person[6],
                'trend' => $person[7],
                'next' => $person[8],
                'lead' => $person[9],
            ], $people),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function dealSpecs(): array
    {
        return [
            ['person' => 0, 'pipeline' => 'sales', 'stage' => 3, 'owner' => 0, 'status' => 'open', 'amount' => 864_000_000, 'title' => 'تمدید پاسخگویی روزانه سپهر تجارت', 'description' => 'قرارداد فعلی تا پایان آبان است. کامران توکلی با ادامه همکاری موافق است و منتظر تأیید هیئت‌مدیره برای اضافه شدن جمعه‌هاست.', 'close_in' => 18, 'closed_days' => 0, 'activity_days' => 1, 'next' => 'ارسال الحاقیه جمعه‌ها', 'lost_reason' => null, 'won_reason' => null],
            ['person' => 1, 'pipeline' => 'sales', 'stage' => 2, 'owner' => 1, 'status' => 'open', 'amount' => 312_000_000, 'title' => 'شیفت شب بازرگانی سپهر تجارت', 'description' => 'نگار سلیمانی قیمت شیفت ۲۲ تا ۸ را با تأمین‌کننده فعلی مقایسه می‌کند. تخفیف قرارداد سالانه را خواسته.', 'close_in' => 12, 'closed_days' => 0, 'activity_days' => 3, 'next' => 'تماس پیگیری پیش‌فاکتور', 'lost_reason' => null, 'won_reason' => null],
            ['person' => 2, 'pipeline' => 'renewal', 'stage' => 1, 'owner' => 5, 'status' => 'open', 'amount' => 540_000_000, 'title' => 'تمدید نوبت‌دهی کلینیک ونک', 'description' => 'حجم تماس ظهرها بالا رفته. دکتر مرادی گزارش تماس بی‌پاسخ هفته گذشته را دیده و خواسته ظرفیت دو کارشناس اضافه شود.', 'close_in' => 21, 'closed_days' => 0, 'activity_days' => 2, 'next' => 'پیشنهاد دو نفر اضافه', 'lost_reason' => null, 'won_reason' => null],
            ['person' => 4, 'pipeline' => 'sales', 'stage' => 2, 'owner' => 1, 'status' => 'open', 'amount' => 276_000_000, 'title' => 'پاسخگویی ثبت‌نام ترم پاییز گویا', 'description' => 'بهاره نیک‌فر برای شش هفته ثبت‌نام، سه کارشناس عصر می‌خواهد. شهریه را هنوز قطعی نکرده‌اند.', 'close_in' => 9, 'closed_days' => 0, 'activity_days' => 1, 'next' => 'هماهنگی شروع ۲۸ مهر', 'lost_reason' => null, 'won_reason' => null],
            ['person' => 6, 'pipeline' => 'renewal', 'stage' => 2, 'owner' => 0, 'status' => 'open', 'amount' => 1_140_000_000, 'title' => 'پشتیبانی سفارش خانه‌کالا', 'description' => 'پویا اعتمادی از کیفیت شیفت عصر راضی است. مذاکره روی جریمه تماس ازدست‌رفته و گزارش روزانه مرجوعی است.', 'close_in' => 14, 'closed_days' => 0, 'activity_days' => 2, 'next' => 'بازبینی بند جریمه', 'lost_reason' => null, 'won_reason' => null],
            ['person' => 9, 'pipeline' => 'sales', 'stage' => 1, 'owner' => 0, 'status' => 'open', 'amount' => 690_000_000, 'title' => 'پوشش پیگیری بار ره‌آورد', 'description' => 'مجید پناهی گفت راننده‌ها بعد از ساعت ۱۸ جواب مشتری را نمی‌دهند. نیازسنجی حجم تماس انجام شده، پیشنهاد هنوز نرفته.', 'close_in' => 20, 'closed_days' => 0, 'activity_days' => 4, 'next' => 'ارسال پیشنهاد شیفت عصر', 'lost_reason' => null, 'won_reason' => null],
            ['person' => 11, 'pipeline' => 'sales', 'stage' => 3, 'owner' => 5, 'status' => 'won', 'amount' => 408_000_000, 'title' => 'جواب‌دهی آزمایشگاه نیکان', 'description' => 'قرارداد شش‌ماهه جواب‌دهی و نوبت نمونه‌گیری امضا شد. شروع از اول آبان.', 'close_in' => 0, 'closed_days' => 6, 'activity_days' => 6, 'next' => null, 'lost_reason' => null, 'won_reason' => 'قیمت و زمان آموزش کارشناسان با برنامه آزمایشگاه جور بود.'],
            ['person' => 13, 'pipeline' => 'sales', 'stage' => 2, 'owner' => 2, 'status' => 'open', 'amount' => 825_000_000, 'title' => 'فروش تلفنی تور نوروز پرواز آبی', 'description' => 'یاسمن حاتمی برای بهمن و اسفند هشت کارشناس می‌خواهد. بودجه را مدیرعامل آژانس باید تأیید کند.', 'close_in' => 16, 'closed_days' => 0, 'activity_days' => 2, 'next' => 'جلسه با مدیرعامل آژانس', 'lost_reason' => null, 'won_reason' => null],
            ['person' => 15, 'pipeline' => 'sales', 'stage' => 0, 'owner' => 1, 'status' => 'open', 'amount' => null, 'title' => 'پشتیبانی نرم‌افزار داده‌پردازان شرق', 'description' => 'فرهاد یزدان‌پناه از نمایشگاه مشهد کارت داده. هنوز حجم تیکت تلفنی را نگفته.', 'close_in' => 30, 'closed_days' => 0, 'activity_days' => 5, 'next' => 'تماس نیازسنجی', 'lost_reason' => null, 'won_reason' => null],
            ['person' => 16, 'pipeline' => 'sales', 'stage' => 1, 'owner' => 5, 'status' => 'open', 'amount' => 360_000_000, 'title' => 'معرفی گزارش کیفیت برای داده‌پردازان', 'description' => 'حدیثه مظفری داشبورد را دیده و گفته برای هیئت‌مدیره جلسه را ضبط کنیم.', 'close_in' => 11, 'closed_days' => 0, 'activity_days' => 16, 'next' => 'هماهنگی جلسه ضبط‌شده', 'lost_reason' => null, 'won_reason' => null],
            ['person' => 17, 'pipeline' => 'sales', 'stage' => 3, 'owner' => 0, 'status' => 'open', 'amount' => 960_000_000, 'title' => 'پاسخگویی فروشگاه‌های لبنیات سحر', 'description' => 'ناصر قریشی تقریباً موافق است. فقط بند آموزش نیروی جایگزین مانده.', 'close_in' => 7, 'closed_days' => 0, 'activity_days' => 1, 'next' => 'نهایی کردن بند آموزش', 'lost_reason' => null, 'won_reason' => null],
            ['person' => 19, 'pipeline' => 'renewal', 'stage' => 0, 'owner' => 2, 'status' => 'open', 'amount' => 198_000_000, 'title' => 'تمدید رزرو سانس مجموعه آسمان', 'description' => 'قرارداد فعلی تا آخر مهر است. روژان عبدی گفته عضویت‌ها افت کرده و اول گزارش را می‌بیند.', 'close_in' => 10, 'closed_days' => 0, 'activity_days' => 3, 'next' => 'ارسال گزارش سه ماه', 'lost_reason' => null, 'won_reason' => null],
            ['person' => 21, 'pipeline' => 'sales', 'stage' => 2, 'owner' => 0, 'status' => 'open', 'amount' => 1_020_000_000, 'title' => 'سفارش تلفنی پخش بهار', 'description' => 'حمیدرضا نعمتی برای پوشش سوپرمارکت‌های جنوب تهران قیمت سالانه خواسته. مقایسه با تیم داخلی‌شان باز است.', 'close_in' => 15, 'closed_days' => 0, 'activity_days' => 2, 'next' => 'مقایسه هزینه تیم داخلی', 'lost_reason' => null, 'won_reason' => null],
            ['person' => 23, 'pipeline' => 'sales', 'stage' => 0, 'owner' => 1, 'status' => 'open', 'amount' => 450_000_000, 'title' => 'پاسخگویی پروژه لاله آریا سازه', 'description' => 'بهروز کامرانی پیش‌فروش فاز دو را از آذر شروع می‌کند. هنوز اسکریپت فروش واحد را نفرستاده.', 'close_in' => 25, 'closed_days' => 0, 'activity_days' => 4, 'next' => 'دریافت اسکریپت فروش', 'lost_reason' => null, 'won_reason' => null],
            ['person' => 25, 'pipeline' => 'sales', 'stage' => 1, 'owner' => 5, 'status' => 'open', 'amount' => 510_000_000, 'title' => 'نوبت‌دهی کلینیک رویال', 'description' => 'دکتر فروتن روی لحن کارشناسان حساس است. یکی از تماس‌های دیروز را گوش داده و دو اصلاح خواسته.', 'close_in' => 13, 'closed_days' => 0, 'activity_days' => 2, 'next' => 'اصلاح سناریو و تماس مجدد', 'lost_reason' => null, 'won_reason' => null],
            ['person' => 27, 'pipeline' => 'sales', 'stage' => 2, 'owner' => 1, 'status' => 'lost', 'amount' => 144_000_000, 'title' => 'هماهنگی جلسات استودیو خط', 'description' => 'سهیل مهاجر گفت حجم تماس‌شان به اندازه یک کارشناس پاره‌وقت هم نیست.', 'close_in' => 0, 'closed_days' => 11, 'activity_days' => 11, 'next' => null, 'lost_reason' => 'حجم تماس برای برون‌سپاری به‌صرفه نبود.', 'won_reason' => null],
            ['person' => 8, 'pipeline' => 'sales', 'stage' => 3, 'owner' => 0, 'status' => 'won', 'amount' => 228_000_000, 'title' => 'آموزش کارشناسان پذیرش خانه‌کالا', 'description' => 'دوره دو روزه برای سرپرست‌های شیفت برگزار شد و صورت‌حساب صادر شده.', 'close_in' => 0, 'closed_days' => 9, 'activity_days' => 9, 'next' => null, 'lost_reason' => null, 'won_reason' => 'آموزش را قبل از شروع کمپین مرجوعی لازم داشتند.'],
            ['person' => 5, 'pipeline' => 'sales', 'stage' => 1, 'owner' => 2, 'status' => 'lost', 'amount' => 96_000_000, 'title' => 'پیگیری شهریه گویا', 'description' => 'آرمان خسروی گفت ثبت‌نام را خودشان داخل پنل آموزشگاه جمع می‌کنند.', 'close_in' => 0, 'closed_days' => 4, 'activity_days' => 4, 'next' => null, 'lost_reason' => 'کار را داخل تیم خودشان نگه داشتند.', 'won_reason' => null],
            ['person' => 20, 'pipeline' => 'sales', 'stage' => 3, 'owner' => 2, 'status' => 'won', 'amount' => 156_000_000, 'title' => 'راه‌اندازی صف عضویت مجموعه آسمان', 'description' => 'صف عضویت و پیامک یادآوری سانس از مهر فعال است.', 'close_in' => 0, 'closed_days' => 15, 'activity_days' => 15, 'next' => null, 'lost_reason' => null, 'won_reason' => 'قبل از شروع فصل استخر باید صف آماده می‌شد.'],
            ['person' => 12, 'pipeline' => 'sales', 'stage' => 2, 'owner' => 3, 'status' => 'lost', 'amount' => 72_000_000, 'title' => 'گزارش تکمیلی آزمایشگاه نیکان', 'description' => 'لیلا برومند گزارش استاندارد را کافی دانست و بسته تکمیلی را نخواست.', 'close_in' => 0, 'closed_days' => 8, 'activity_days' => 8, 'next' => null, 'lost_reason' => 'بسته فعلی نیازشان را پوشش می‌دهد.', 'won_reason' => null],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function taskSpecs(): array
    {
        return [
            ['person' => 0, 'deal' => 0, 'owner' => 0, 'title' => 'ارسال الحاقیه کار جمعه برای سپهر تجارت', 'description' => 'بند جمعه را با همان نرخ شیفت روز بنویس و برای کامران توکلی بفرست.', 'priority' => 'high', 'source' => 'sales_follow_up', 'status' => 'in_progress', 'due_in' => 1, 'hour' => 11, 'minute' => 0, 'outcome' => null],
            ['person' => 1, 'deal' => 1, 'owner' => 1, 'title' => 'پیگیری پیش‌فاکتور شیفت شب', 'description' => 'نگار سلیمانی گفته تا چهارشنبه با مدیرعامل چک می‌کند.', 'priority' => 'high', 'source' => 'sales_follow_up', 'status' => 'pending', 'due_in' => 1, 'hour' => 11, 'minute' => 0, 'outcome' => null],
            ['person' => 2, 'deal' => 2, 'owner' => 5, 'title' => 'تهیه پیشنهاد دو کارشناس اضافه برای ونک', 'description' => 'گزارش تماس بی‌پاسخ ظهر را ضمیمه کن.', 'priority' => 'urgent', 'source' => 'analysis', 'status' => 'pending', 'due_in' => 1, 'hour' => 10, 'minute' => 0, 'outcome' => null],
            ['person' => 4, 'deal' => 3, 'owner' => 1, 'title' => 'تأیید تاریخ شروع ثبت‌نام گویا', 'description' => 'بهاره نیک‌فر ۲۸ مهر را گفته؛ نیروی عصر را با عملیات چک کن.', 'priority' => 'normal', 'source' => 'manual', 'status' => 'pending', 'due_in' => 2, 'hour' => 12, 'minute' => 0, 'outcome' => null],
            ['person' => 6, 'deal' => 4, 'owner' => 0, 'title' => 'بازبینی بند جریمه خانه‌کالا', 'description' => 'نسخه قبلی جریمه را از قرارداد تیر بردار و با پیشنهاد جدید عوض کن.', 'priority' => 'high', 'source' => 'sales_follow_up', 'status' => 'in_progress', 'due_in' => 1, 'hour' => 15, 'minute' => 0, 'outcome' => null],
            ['person' => 7, 'deal' => 4, 'owner' => 4, 'title' => 'تماس درباره تأخیر ارسال‌های شمال تهران', 'description' => 'مینا افشار سه شکایت دیروز را فهرست کرده.', 'priority' => 'urgent', 'source' => 'analysis', 'status' => 'pending', 'due_in' => -1, 'hour' => 13, 'minute' => 0, 'outcome' => null],
            ['person' => 9, 'deal' => 5, 'owner' => 0, 'title' => 'ارسال پیشنهاد شیفت عصر ره‌آورد بار', 'description' => 'حجم تقریبی را از تماس نیازسنجی بردار، حدود چهل تماس در روز.', 'priority' => 'normal', 'source' => 'sales_follow_up', 'status' => 'pending', 'due_in' => 2, 'hour' => 17, 'minute' => 0, 'outcome' => null],
            ['person' => 11, 'deal' => 6, 'owner' => 5, 'title' => 'هماهنگی روز اول جواب‌دهی نیکان', 'description' => 'لیست کارشناسان و شماره داخلی را برای دکتر کاشانی بفرست.', 'priority' => 'high', 'source' => 'manual', 'status' => 'completed', 'due_in' => -3, 'hour' => 9, 'minute' => 30, 'outcome' => 'اسامی و داخلی‌ها ایمیل شد.'],
            ['person' => 13, 'deal' => 7, 'owner' => 2, 'title' => 'ست کردن جلسه با مدیرعامل پرواز آبی', 'description' => 'یاسمن حاتمی پنجشنبه را تعطیل گفت. شنبه یا یکشنبه.', 'priority' => 'high', 'source' => 'sales_follow_up', 'status' => 'pending', 'due_in' => 3, 'hour' => 11, 'minute' => 30, 'outcome' => null],
            ['person' => 15, 'deal' => 8, 'owner' => 1, 'title' => 'تماس نیازسنجی داده‌پردازان شرق', 'description' => 'بپرس چند تماس پشتیبانی در هفته دارند و ساعت اوج چیست.', 'priority' => 'normal', 'source' => 'manual', 'status' => 'pending', 'due_in' => 1, 'hour' => 14, 'minute' => 0, 'outcome' => null],
            ['person' => 17, 'deal' => 10, 'owner' => 0, 'title' => 'نهایی کردن بند آموزش نیروی جایگزین سحر', 'description' => 'ناصر قریشی می‌خواهد جایگزینی بیماری در قرارداد بیاید.', 'priority' => 'urgent', 'source' => 'sales_follow_up', 'status' => 'in_progress', 'due_in' => 1, 'hour' => 17, 'minute' => 30, 'outcome' => null],
            ['person' => 19, 'deal' => 11, 'owner' => 2, 'title' => 'گزارش سه‌ماهه مجموعه آسمان', 'description' => 'تماس ورودی، عضویت تمدیدشده و سانس کنسلی.', 'priority' => 'normal', 'source' => 'manual', 'status' => 'pending', 'due_in' => 2, 'hour' => 16, 'minute' => 0, 'outcome' => null],
            ['person' => 21, 'deal' => 12, 'owner' => 0, 'title' => 'مقایسه هزینه تیم داخلی پخش بهار', 'description' => 'حمیدرضا نعمتی حقوق چهار اپراتور را فرستاده. با پلن سالانه قیاس کن.', 'priority' => 'high', 'source' => 'sales_follow_up', 'status' => 'pending', 'due_in' => 1, 'hour' => 12, 'minute' => 30, 'outcome' => null],
            ['person' => 25, 'deal' => 14, 'owner' => 4, 'title' => 'اصلاح سناریوی نوبت کلینیک رویال', 'description' => 'دو جمله‌ای که دکتر فروتن علامت زده را نرم‌تر کن.', 'priority' => 'high', 'source' => 'analysis', 'status' => 'pending', 'due_in' => -2, 'hour' => 10, 'minute' => 30, 'outcome' => null],
            ['person' => 8, 'deal' => 16, 'owner' => 0, 'title' => 'پیگیری وصول فاکتور آموزش خانه‌کالا', 'description' => 'کیانوش جعفری گفته در سیکل پرداخت بیستم ماه است.', 'priority' => 'normal', 'source' => 'manual', 'status' => 'pending', 'due_in' => 4, 'hour' => 11, 'minute' => 0, 'outcome' => null],
            ['person' => 23, 'deal' => 13, 'owner' => 1, 'title' => 'گرفتن اسکریپت پیش‌فروش پروژه لاله', 'description' => 'بدون اسکریپت نمی‌شود ظرفیت کارشناس را بست.', 'priority' => 'low', 'source' => 'sales_follow_up', 'status' => 'pending', 'due_in' => 5, 'hour' => 15, 'minute' => 30, 'outcome' => null],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function productSpecs(): array
    {
        return [
            'day' => ['name' => 'پاسخگویی روزانه', 'description' => 'پوشش تماس ورودی شنبه تا چهارشنبه، ۹ تا ۱۸.', 'type' => 'service', 'category' => 'plans', 'code' => 'ANS-DAY', 'tomans' => 48_000_000, 'unit' => 'ماه'],
            'night' => ['name' => 'شیفت شب', 'description' => 'پاسخگویی ۲۲ تا ۸، مناسب پیگیری سفارش و نوبت فوری.', 'type' => 'service', 'category' => 'plans', 'code' => 'ANS-NIGHT', 'tomans' => 26_000_000, 'unit' => 'ماه'],
            'season' => ['name' => 'پلن فصلی فروش', 'description' => 'تیم فروش تلفنی برای کمپین محدود، حداقل یک ماه.', 'type' => 'service', 'category' => 'plans', 'code' => 'SALE-SEASON', 'tomans' => 67_500_000, 'unit' => 'ماه'],
            'ivr' => ['name' => 'راه‌اندازی صف و منوی تلفن', 'description' => 'طراحی منو، صف انتظار و انتقال به کارشناس.', 'type' => 'service', 'category' => 'setup', 'code' => 'SET-IVR', 'tomans' => 18_500_000, 'unit' => 'پروژه'],
            'train' => ['name' => 'آموزش کارشناسان', 'description' => 'کارگاه سناریو، لحن و ثبت نتیجه تماس.', 'type' => 'service', 'category' => 'setup', 'code' => 'SET-TRN', 'tomans' => 9_800_000, 'unit' => 'روز'],
            'report' => ['name' => 'گزارش کیفیت ماهانه', 'description' => 'خلاصه امتیاز، تماس بی‌پاسخ و موضوع‌های پرتکرار.', 'type' => 'service', 'category' => 'plans', 'code' => 'RPT-M', 'tomans' => 4_200_000, 'unit' => 'ماه'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function invoiceSpecs(): array
    {
        return [
            ['person' => 0, 'type' => 'invoice', 'status' => 'issued', 'number' => 'INV-1405-1048', 'days_ago' => 20, 'national_id' => $this->nationalId(104812), 'economic_code' => '411258734901', 'address' => 'تهران، سعادت‌آباد، میدان کاج، برج تجاری پارس، طبقه ۶', 'notes' => 'بابت پاسخگویی مهر.', 'lines' => [['product' => 'day', 'qty' => 1]]],
            ['person' => 6, 'type' => 'invoice', 'status' => 'issued', 'number' => 'INV-1405-1062', 'days_ago' => 12, 'national_id' => $this->nationalId(106244), 'economic_code' => '140078523641', 'address' => 'تهران، جردن، خیابان شهید دستگردی، پلاک ۴۲', 'notes' => 'شیفت عصر و گزارش کیفیت.', 'lines' => [['product' => 'day', 'qty' => 1], ['product' => 'report', 'qty' => 1]]],
            ['person' => 8, 'type' => 'invoice', 'status' => 'issued', 'number' => 'INV-1405-1071', 'days_ago' => 9, 'national_id' => $this->nationalId(107133), 'economic_code' => '140078523641', 'address' => 'تهران، جردن، خیابان شهید دستگردی، پلاک ۴۲', 'notes' => 'دو روز آموزش سرپرست‌های شیفت.', 'lines' => [['product' => 'train', 'qty' => 2, 'description' => 'آموزش سرپرست شیفت']]],
            ['person' => 11, 'type' => 'invoice', 'status' => 'issued', 'number' => 'INV-1405-1084', 'days_ago' => 6, 'national_id' => $this->nationalId(108466), 'economic_code' => '411390228175', 'address' => 'تهران، پاسداران، بوستان دوم، پلاک ۱۱', 'notes' => 'پیش‌پرداخت قرارداد شش‌ماهه. شروع اول آبان.', 'lines' => [['product' => 'day', 'qty' => 1], ['product' => 'ivr', 'qty' => 1]]],
            ['person' => 19, 'type' => 'invoice', 'status' => 'issued', 'number' => 'INV-1405-1033', 'days_ago' => 28, 'national_id' => $this->nationalId(103355), 'economic_code' => '411667201984', 'address' => 'تهران، نیاوران، خیابان باهنر، کوچه صالحی', 'notes' => 'راه‌اندازی صف عضویت.', 'lines' => [['product' => 'ivr', 'qty' => 1]]],
            ['person' => 1, 'type' => 'proforma', 'status' => 'issued', 'number' => 'PF-1405-221', 'days_ago' => 3, 'national_id' => $this->nationalId(221904), 'economic_code' => '411258734901', 'address' => 'تهران، سعادت‌آباد، میدان کاج، برج تجاری پارس، طبقه ۶', 'notes' => 'پیش‌فاکتور شیفت شب، معتبر تا دهم آبان.', 'lines' => [['product' => 'night', 'qty' => 3, 'description' => 'شیفت شب، سه ماه']]],
            ['person' => 4, 'type' => 'proforma', 'status' => 'issued', 'number' => 'PF-1405-228', 'days_ago' => 2, 'national_id' => $this->nationalId(228641), 'economic_code' => '411445902318', 'address' => 'تهران، یوسف‌آباد، خیابان شانزدهم، پلاک ۷', 'notes' => 'پوشش ثبت‌نام ترم پاییز، شش هفته.', 'lines' => [['product' => 'season', 'qty' => 1]]],
            ['person' => 13, 'type' => 'proforma', 'status' => 'draft', 'number' => 'PF-1405-234', 'days_ago' => 1, 'national_id' => $this->nationalId(234110), 'economic_code' => '411902775640', 'address' => 'تهران، میرداماد، برج ناهید، واحد ۸۰۴', 'notes' => 'پیش‌نویس تا تأیید مدیرعامل آژانس.', 'lines' => [['product' => 'season', 'qty' => 2, 'description' => 'فروش تلفنی نوروز، دو ماه']]],
            ['person' => 17, 'type' => 'proforma', 'status' => 'issued', 'number' => 'PF-1405-219', 'days_ago' => 4, 'national_id' => $this->nationalId(219508), 'economic_code' => '411573340216', 'address' => 'اصفهان، شهرک صنعتی جی، خیابان هفتم', 'notes' => 'پاسخگویی به فروشگاه‌ها، پیشنهاد سالانه.', 'lines' => [['product' => 'day', 'qty' => 1], ['product' => 'report', 'qty' => 1]]],
            ['person' => 21, 'type' => 'invoice', 'status' => 'draft', 'number' => 'INV-1405-1090', 'days_ago' => 0, 'national_id' => $this->nationalId(109027), 'economic_code' => '140066218437', 'address' => 'تهران، شوش، خیابان فدائیان اسلام', 'notes' => 'هنوز نهایی نشده. منتظر مقایسه با تیم داخلی.', 'lines' => [['product' => 'day', 'qty' => 1], ['product' => 'night', 'qty' => 1]]],
        ];
    }

    private function nationalId(int $seed): string
    {
        $base = str_pad((string) (100_000_000 + ($seed % 800_000_000)), 9, '0', STR_PAD_LEFT);
        $base = substr($base, 0, 9);
        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $base[$i] * (10 - $i);
        }

        $remainder = $sum % 11;
        $check = $remainder < 2 ? $remainder : 11 - $remainder;

        return $base.$check;
    }
}
