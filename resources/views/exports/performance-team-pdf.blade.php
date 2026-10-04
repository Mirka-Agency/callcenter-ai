@php
    use App\Support\AgentPerformancePresenter;

    $kpis = $dashboard['kpis'];
    $deltas = $dashboard['kpis_delta'];
    $previous = $filter->previousPeriod();

    $score = function (mixed $value): string {
        return ($value === null || $value === '' || (float) $value == 0.0) ? '—' : (string) $value;
    };

    $delta = function (mixed $value): ?string {
        if ($value === null) {
            return null;
        }

        $number = (float) $value;
        $sign = $number > 0 ? '+' : '';

        return $sign.number_format($number, 1).'٪';
    };

    $deltaClass = function (mixed $value): string {
        if ($value === null || (float) $value == 0.0) {
            return 'flat';
        }

        return (float) $value > 0 ? 'up' : 'down';
    };

    $trendLabel = function (?string $trend): string {
        return match ($trend) {
            'improving' => 'رو به بهبود',
            'declining' => 'رو به افت',
            default => 'پایدار',
        };
    };

    $barRows = function (array $rows): array {
        $max = max(1, (int) collect($rows)->max('value'));

        return collect($rows)->map(function (array $row) use ($max) {
            $row['percent'] = (int) round(((float) $row['value'] / $max) * 100);

            return $row;
        })->all();
    };

    $moves = collect($dashboard['quality_trend_insights'] ?? [])
        ->filter(fn (array $insight) => in_array($insight['direction'] ?? '', ['up', 'down'], true))
        ->sortByDesc(fn (array $insight) => abs((float) ($insight['score_delta'] ?? 0)))
        ->values();
    $qualityUp = $moves->first(fn (array $insight) => $insight['direction'] === 'up');
    $qualityDown = $moves->first(fn (array $insight) => $insight['direction'] === 'down');

    $sentimentTotals = [
        ['label' => 'مثبت', 'value' => (int) collect($dashboard['sentiment_trend'] ?? [])->sum('positive'), 'color' => '#059669'],
        ['label' => 'خنثی', 'value' => (int) collect($dashboard['sentiment_trend'] ?? [])->sum('neutral'), 'color' => '#71717a'],
        ['label' => 'منفی', 'value' => (int) collect($dashboard['sentiment_trend'] ?? [])->sum('negative'), 'color' => '#dc2626'],
        ['label' => 'ترکیبی', 'value' => (int) collect($dashboard['sentiment_trend'] ?? [])->sum('mixed'), 'color' => '#d97706'],
    ];

    $lead = $dashboard['lead_distribution'] ?? ['high' => 0, 'medium' => 0, 'low' => 0, 'total' => 0, 'average_score' => 0];
    $leadRows = [
        ['label' => 'لید قوی', 'value' => (int) ($lead['high'] ?? 0), 'color' => '#059669'],
        ['label' => 'لید متوسط', 'value' => (int) ($lead['medium'] ?? 0), 'color' => '#d97706'],
        ['label' => 'لید ضعیف', 'value' => (int) ($lead['low'] ?? 0), 'color' => '#dc2626'],
    ];

    $distributionColors = ['#059669', '#4f46e5', '#d97706', '#dc2626'];
    $distributionRows = collect($dashboard['quality_distribution'] ?? [])->values()->map(function (array $row, int $index) use ($distributionColors) {
        return [
            'label' => $row['label'],
            'value' => (int) $row['count'],
            'color' => $distributionColors[$index] ?? '#4f46e5',
        ];
    })->all();

    $weaknessMax = max(1, (int) collect($dashboard['team_weaknesses'] ?? [])->max('count'));
    $rankings = [
        'best_quality' => ['title' => 'بالاترین امتیاز مکالمه', 'value' => fn (array $row) => $score($row['average_score'] ?? null)],
        'most_improved' => ['title' => 'بیشترین پیشرفت', 'value' => fn (array $row) => $delta($row['improvement_percent'] ?? null) ?? '—'],
        'most_calls' => ['title' => 'بیشترین تماس', 'value' => fn (array $row) => (string) ($row['total_calls'] ?? 0)],
        'best_lead' => ['title' => 'بهترین کیفیت لید', 'value' => fn (array $row) => $score($row['average_lead_score'] ?? null)],
        'best_sentiment' => ['title' => 'بالاترین رضایت مشتری', 'value' => fn (array $row) => $score($row['average_sentiment'] ?? null)],
    ];

@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>گزارش عملکرد کارشناسان</title>
    <style>
        body { font-family: vazirmatn, sans-serif; font-size: 11px; color: #18181b; }
        h1 { font-size: 22px; margin: 0; color: #1e1b4b; }
        h2 { font-size: 14px; margin: 18px 0 8px; color: #312e81; border-bottom: 2px solid #c7d2fe; padding-bottom: 4px; }
        h3 { font-size: 12px; margin: 0 0 6px; color: #1e1b4b; }
        .eyebrow { font-size: 9px; letter-spacing: 0; color: #4f46e5; font-weight: bold; margin-bottom: 2px; }
        .muted { color: #71717a; font-size: 9px; line-height: 1.7; }
        .summary { background: #eef2ff; border: 1px solid #c7d2fe; padding: 10px 12px; line-height: 1.9; }
        table { border-collapse: collapse; }
        .meta td { vertical-align: top; }
        .kpis td { width: 16.66%; background: #fafafa; border: 1px solid #e4e4e7; padding: 8px; vertical-align: top; }
        .kpi-label { color: #52525b; font-size: 8.5px; }
        .kpi-value { font-size: 16px; font-weight: bold; margin-top: 3px; color: #18181b; }
        .up { color: #047857; font-size: 8.5px; }
        .down { color: #b91c1c; font-size: 8.5px; }
        .flat { color: #71717a; font-size: 8.5px; }
        .panel { border: 1px solid #e4e4e7; padding: 8px; vertical-align: top; }
        .keep { page-break-inside: avoid; }
        .data { width: 100%; }
        .data th { background: #312e81; color: #ffffff; font-size: 8px; padding: 5px 4px; text-align: right; font-weight: bold; }
        .data td { border-bottom: 1px solid #e4e4e7; padding: 4px; font-size: 8.5px; vertical-align: top; }
        .alt td { background: #fafafa; }
        .note { background: #fafafa; border: 1px solid #e4e4e7; padding: 8px 10px; line-height: 1.8; }
        ul { margin: 4px 0 0; padding: 0 16px 0 0; }
        li { margin-bottom: 3px; line-height: 1.7; }
    </style>
</head>
<body>
    <table class="meta" width="100%">
        <tr>
            <td>
                <div class="eyebrow">گزارش مدیریتی عملکرد تیم</div>
                <h1>عملکرد کارشناسان</h1>
                <div class="muted">{{ $organizationTitle }}</div>
            </td>
            <td style="text-align: left;">
                <div><strong>{{ $filter->preset->label() }}</strong></div>
                <div class="muted">{{ shamsi($filter->from) }} تا {{ shamsi($filter->to) }}</div>
                <div class="muted">مقایسه با {{ shamsi($previous->from) }} تا {{ shamsi($previous->to) }}</div>
                <div class="muted">تاریخ تهیه: {{ shamsi(now(), 'datetime') }}</div>
                @if ($filter->employeeIds !== [])
                    <div class="muted">محدود به {{ count($filter->employeeIds) }} کارشناس انتخاب‌شده</div>
                @endif
            </td>
        </tr>
    </table>

    <h2>جمع‌بندی</h2>
    <div class="summary">{{ $dashboard['executive_summary'] }}</div>

    @if (! empty($dashboard['progress_insights']))
        <ul>
            @foreach ($dashboard['progress_insights'] as $insight)
                <li>{{ $insight }}</li>
            @endforeach
        </ul>
    @endif

    <h2>شاخص‌های اصلی</h2>
    <table class="kpis" width="100%">
        <tr>
            @foreach ([
                ['کارشناسان فعال', $kpis['active_employees'].' از '.$kpis['total_employees'], null],
                ['تماس‌های برقرارشده', $kpis['total_calls'], $deltas['total_calls'] ?? null],
                ['مکالمات تحلیل‌شده', $kpis['total_analyzed'], $deltas['total_analyzed'] ?? null],
                ['میانگین امتیاز مکالمه', $score($kpis['average_quality_score']), $deltas['average_quality_score'] ?? null],
                ['میانگین کیفیت لید', $score($kpis['average_lead_score']), $deltas['average_lead_score'] ?? null],
                ['شاخص رضایت مشتری', $score($kpis['average_sentiment']), $deltas['average_sentiment'] ?? null],
            ] as [$label, $value, $change])
                <td>
                    <div class="kpi-label">{{ $label }}</div>
                    <div class="kpi-value">{{ $value }}</div>
                    @if ($delta($change))
                        <div class="{{ $deltaClass($change) }}">{{ $delta($change) }} نسبت به دوره قبل</div>
                    @endif
                </td>
            @endforeach
        </tr>
    </table>
    <p class="muted">
        امتیاز مکالمه از {{ $kpis['quality_sample_count'] }} مکالمه قابل ارزیابی،
        کیفیت لید از {{ $kpis['lead_sample_count'] }} مکالمه دارای امتیاز لید،
        و رضایت از {{ $kpis['sentiment_sample_count'] }} مکالمه دارای احساس مشتری محاسبه شده است.
        تماس‌های تعطیل در روند نمودارها کنار گذاشته شده‌اند.
    </p>

    <div class="keep">
        <h2>روند کیفیت مکالمه</h2>
        <p class="muted">محور افقی بازه زمانی است و محور عمودی امتیاز مکالمه از ۰ تا ۱۰۰. هر نقطه مقدار خودش را نشان می‌دهد. بازه بدون امتیاز روی محور می‌ماند ولی به خط وصل نمی‌شود.</p>
        @if (! empty($charts['quality']))
            <img src="var:quality" style="width: 100%;">
        @else
            <p class="muted">در این بازه امتیاز قابل ترسیم وجود ندارد.</p>
        @endif
    </div>
    @include('exports.partials.pdf-trend-table', [
        'rows' => $dashboard['quality_trend'] ?? [],
        'columns' => [
            'بازه' => fn (array $row) => $row['label'] ?? '—',
            'امتیاز مکالمه' => fn (array $row) => $row['avg_score'] ?? '—',
            'تعداد تحلیل' => fn (array $row) => $row['count'] ?? 0,
        ],
    ])

    <div class="keep">
        <h2>حجم تماس</h2>
        <p class="muted">محور افقی بازه زمانی است و محور عمودی تعداد تماس. ارتفاع هر ستون همان تعداد آن بازه است.</p>
        @if (! empty($charts['volume']))
            <img src="var:volume" style="width: 100%;">
        @else
            <p class="muted">در این بازه تماسی برای نمودار ثبت نشده است.</p>
        @endif
    </div>
    @include('exports.partials.pdf-trend-table', [
        'rows' => $dashboard['volume_trend'] ?? [],
        'columns' => [
            'بازه' => fn (array $row) => $row['label'] ?? '—',
            'تعداد تماس' => fn (array $row) => $row['count'] ?? 0,
        ],
    ])

    <div class="keep">
        <h2>روند کیفیت لید</h2>
        <p class="muted">محور عمودی امتیاز لید از ۰ تا ۱۰۰ است. بازه‌ای که امتیاز لید ندارد در جدول با خط تیره آمده و خط نمودار آنجا قطع می‌شود.</p>
        @if (! empty($charts['lead']))
            <img src="var:lead" style="width: 100%;">
        @else
            <p class="muted">امتیاز لید کافی برای رسم روند وجود ندارد.</p>
        @endif
    </div>
    @include('exports.partials.pdf-trend-table', [
        'rows' => $dashboard['lead_trend'] ?? [],
        'columns' => [
            'بازه' => fn (array $row) => $row['label'] ?? '—',
            'امتیاز لید' => fn (array $row) => ((float) ($row['avg_score'] ?? 0)) > 0 ? $row['avg_score'] : '—',
            'تعداد تحلیل' => fn (array $row) => $row['count'] ?? 0,
        ],
    ])

    <div class="keep">
        <h2>ترکیب احساس مشتری</h2>
        <p class="muted">محور عمودی تعداد مکالمه است. هر ستون جمع احساس‌های همان بازه را نشان می‌دهد: مثبت، خنثی، ترکیبی و منفی.</p>
        @if (! empty($charts['sentiment']))
            <img src="var:sentiment" style="width: 100%;">
        @else
            <p class="muted">احساس مشتری کافی برای رسم نمودار وجود ندارد.</p>
        @endif
    </div>
    @include('exports.partials.pdf-trend-table', [
        'rows' => $dashboard['sentiment_trend'] ?? [],
        'columns' => [
            'بازه' => fn (array $row) => $row['label'] ?? '—',
            'مثبت' => fn (array $row) => $row['positive'] ?? 0,
            'خنثی' => fn (array $row) => $row['neutral'] ?? 0,
            'ترکیبی' => fn (array $row) => $row['mixed'] ?? 0,
            'منفی' => fn (array $row) => $row['negative'] ?? 0,
        ],
    ])

    @if ($qualityUp || $qualityDown)
        <h2>خوانش نمودار کیفیت</h2>
        <table width="100%">
            <tr>
                @foreach (array_filter([$qualityUp, $qualityDown]) as $insight)
                    <td class="note" width="50%" style="vertical-align: top;">
                        <strong>{{ $insight['headline'] }} — {{ $insight['label'] }}</strong>
                        <div>{{ $insight['reason'] }}</div>
                        <div class="muted">
                            امتیاز {{ $insight['current_score'] }}
                            @if ($insight['score_delta'] !== null)
                                @php
                                    $pointDelta = (float) $insight['score_delta'];
                                    $pointLabel = ($pointDelta > 0 ? '+' : '').number_format($pointDelta, 1);
                                @endphp
                                ({{ $pointLabel }} امتیاز نسبت به نقطه قبل)
                            @endif
                            — {{ $insight['analyzed_count'] }} مکالمه
                        </div>
                        @if (! empty($insight['agents']))
                            <div class="muted">
                                کارشناسان مؤثر:
                                {{ collect($insight['agents'])->take(3)->pluck('name')->implode('، ') }}
                            </div>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <h2>توزیع کیفیت و لید</h2>
    <table width="100%">
        <tr>
            <td class="panel" width="50%">
                <h3>توزیع امتیاز مکالمه</h3>
                @include('exports.partials.pdf-bars', ['rows' => $barRows($distributionRows), 'suffix' => 'مکالمه'])
            </td>
            <td class="panel" width="50%">
                <h3>توزیع کیفیت لید</h3>
                <div class="muted">میانگین {{ $score($lead['average_score'] ?? 0) }} از {{ (int) ($lead['total'] ?? 0) }} لید</div>
                @include('exports.partials.pdf-bars', ['rows' => $barRows($leadRows), 'suffix' => 'لید'])
            </td>
        </tr>
        <tr>
            <td class="panel" colspan="2">
                <h3>ترکیب احساس مشتری در کل بازه</h3>
                @include('exports.partials.pdf-bars', ['rows' => $barRows($sentimentTotals), 'suffix' => 'مکالمه'])
            </td>
        </tr>
    </table>

    <h2>رتبه‌بندی</h2>
    <table width="100%">
        <tr>
            @foreach ($rankings as $key => $meta)
                <td class="panel" width="20%" style="vertical-align: top;">
                    <h3>{{ $meta['title'] }}</h3>
                    <table class="data" width="100%">
                        @forelse (array_slice($dashboard['rankings'][$key] ?? [], 0, 5) as $index => $row)
                            <tr @class(['alt' => $index % 2 === 1])>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ ($meta['value'])($row) }}</td>
                            </tr>
                        @empty
                            <tr><td class="muted">داده کافی نیست.</td></tr>
                        @endforelse
                    </table>
                </td>
            @endforeach
        </tr>
    </table>

    <h2>کارشناسان نیازمند توجه</h2>
    @if (empty($dashboard['attention_employees']))
        <p class="muted">کارشناسی با ضعف تکرارشونده در این بازه شناسایی نشد.</p>
    @else
        <table class="data" width="100%">
            <thead>
                <tr>
                    <th>کارشناس</th>
                    <th>امتیاز مکالمه</th>
                    <th>مکالمات</th>
                    <th>نرخ تکرار ضعف</th>
                    <th>ضعف‌های تکرارشده</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($dashboard['attention_employees'] as $index => $row)
                    <tr @class(['alt' => $index % 2 === 1])>
                        <td>{{ $row['name'] }}@if (! empty($row['department']))<div class="muted">{{ $row['department'] }}</div>@endif</td>
                        <td>{{ $score($row['average_score'] ?? null) }}</td>
                        <td>{{ $row['total_analyzed'] }}</td>
                        <td>{{ (int) round(((float) ($row['weakness_rate'] ?? 0)) * 100) }}٪</td>
                        <td>
                            {{ collect($row['repeated_weaknesses'] ?? [])->map(fn (array $item) => $item['item'].' ('.$item['count'].')')->implode('، ') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>ضعف‌های پرتکرار تیم</h2>
    @if (empty($dashboard['team_weaknesses']))
        <p class="muted">ضعف تکرارشونده‌ای برای تیم ثبت نشده است.</p>
    @else
        <table class="data" width="100%">
            <thead>
                <tr>
                    <th>ضعف</th>
                    <th>تکرار</th>
                    <th style="width: 34%;">سهم نسبی</th>
                    <th>تغییر نسبت به دوره قبل</th>
                </tr>
            </thead>
            <tbody>
                @foreach (array_slice($dashboard['team_weaknesses'], 0, 8) as $index => $row)
                    <tr @class(['alt' => $index % 2 === 1])>
                        <td>{{ $row['item'] }}</td>
                        <td>{{ $row['count'] }}</td>
                        <td>
                            <table width="100%"><tr>
                                <td width="{{ (int) round(($row['count'] / $weaknessMax) * 100) }}%" bgcolor="#f59e0b" style="font-size: 1px; height: 8px;">&nbsp;</td>
                                <td></td>
                            </tr></table>
                        </td>
                        <td>{{ $delta($row['trend'] ?? null) ?? 'داده دوره قبل نیست' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>جدول عملکرد کارشناسان</h2>
    @if (empty($dashboard['employees']))
        <p class="muted">در این بازه فعالیتی برای کارشناسان ثبت نشده است.</p>
    @else
        <table class="data" width="100%">
            <thead>
                <tr>
                    <th>رتبه</th>
                    <th>کارشناس</th>
                    <th>بخش</th>
                    <th>تماس</th>
                    <th>تحلیل</th>
                    <th>پاسخ</th>
                    <th>مدت</th>
                    <th>مکالمه</th>
                    <th>تغییر</th>
                    <th>لید</th>
                    <th>رضایت</th>
                    <th>اثربخشی</th>
                    <th>روند</th>
                    <th>نقطه قوت</th>
                    <th>نقطه ضعف</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($dashboard['employees'] as $index => $row)
                    <tr @class(['alt' => $index % 2 === 1])>
                        <td>{{ $row['rank'] }}</td>
                        <td>
                            {{ $row['name'] }}
                            <div class="muted">{{ AgentPerformancePresenter::tierLabel($row['tier'] ?? '') ?: 'معمول' }}</div>
                        </td>
                        <td>{{ $row['department'] ?? '—' }}</td>
                        <td>{{ $row['total_calls'] }}</td>
                        <td>{{ $row['total_analyzed'] }}</td>
                        <td>{{ $row['answer_rate'] !== null ? $row['answer_rate'].'٪' : '—' }}</td>
                        <td>{{ $row['average_duration_label'] ?? '—' }}</td>
                        <td>{{ $score($row['average_score'] ?? null) }}</td>
                        <td>{{ $delta($row['improvement_percent'] ?? null) ?? '—' }}</td>
                        <td>{{ $score($row['average_lead_score'] ?? null) }}</td>
                        <td>{{ $score($row['average_sentiment'] ?? null) }}</td>
                        <td>{{ $score($row['effectiveness_score'] ?? null) }}</td>
                        <td>{{ $trendLabel($row['trend'] ?? null) }}</td>
                        <td>{{ $row['top_strength'] ?? '—' }}</td>
                        <td>{{ $row['top_weakness'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
