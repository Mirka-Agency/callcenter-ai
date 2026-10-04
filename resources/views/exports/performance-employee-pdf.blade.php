@php
    use App\Support\AnalysisInsightPresenter;

    $metrics = $profile['metrics'];
    $deltas = $profile['metrics_delta'];
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

    $dimensionRows = collect($profile['dimension_averages'] ?? [])->map(function (float $value, string $key) {
        return [
            'label' => AnalysisInsightPresenter::dimensionLabel($key),
            'value' => $value,
            'percent' => (int) round(min(100, max(0, $value))),
            'color' => '#4f46e5',
        ];
    })->values()->all();
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>گزارش عملکرد {{ $employee->full_name }}</title>
    <style>
        body { font-family: vazirmatn, sans-serif; font-size: 11px; color: #18181b; }
        h1 { font-size: 20px; margin: 0; color: #1e1b4b; }
        h2 { font-size: 14px; margin: 16px 0 8px; color: #312e81; border-bottom: 2px solid #c7d2fe; padding-bottom: 4px; }
        h3 { font-size: 12px; margin: 0 0 6px; color: #1e1b4b; }
        .eyebrow { font-size: 9px; color: #4f46e5; font-weight: bold; }
        .muted { color: #71717a; font-size: 9px; line-height: 1.7; }
        .summary { background: #eef2ff; border: 1px solid #c7d2fe; padding: 10px 12px; line-height: 1.9; }
        table { border-collapse: collapse; }
        .kpis td { width: 33.33%; background: #fafafa; border: 1px solid #e4e4e7; padding: 8px; vertical-align: top; }
        .kpi-label { color: #52525b; font-size: 8.5px; }
        .kpi-value { font-size: 16px; font-weight: bold; margin-top: 3px; }
        .up { color: #047857; font-size: 8.5px; }
        .down { color: #b91c1c; font-size: 8.5px; }
        .flat { color: #71717a; font-size: 8.5px; }
        .panel { border: 1px solid #e4e4e7; padding: 8px; vertical-align: top; }
        .keep { page-break-inside: avoid; }
        .data { width: 100%; }
        .data th { background: #312e81; color: #ffffff; font-size: 8.5px; padding: 5px; text-align: right; }
        .data td { border-bottom: 1px solid #e4e4e7; padding: 4px; font-size: 9px; vertical-align: top; }
        .alt td { background: #fafafa; }
        ul { margin: 4px 0 0; padding: 0 16px 0 0; }
        li { margin-bottom: 3px; line-height: 1.7; }
    </style>
</head>
<body>
    <table width="100%">
        <tr>
            <td>
                <div class="eyebrow">پروفایل عملکرد کارشناس</div>
                <h1>{{ $employee->full_name }}</h1>
                <div class="muted">{{ collect([$employee->position, $employee->department])->filter()->implode(' — ') ?: 'بدون سمت ثبت‌شده' }}</div>
            </td>
            <td style="text-align: left;">
                <div><strong>{{ $filter->preset->label() }}</strong></div>
                <div class="muted">{{ shamsi($filter->from) }} تا {{ shamsi($filter->to) }}</div>
                <div class="muted">مقایسه با {{ shamsi($previous->from) }} تا {{ shamsi($previous->to) }}</div>
                <div class="muted">تاریخ تهیه: {{ shamsi(now(), 'datetime') }}</div>
            </td>
        </tr>
    </table>

    <h2>جمع‌بندی</h2>
    <div class="summary">{{ $profile['executive_summary'] }}</div>
    @if (! empty($profile['progress_insights']))
        <ul>
            @foreach ($profile['progress_insights'] as $insight)
                <li>{{ $insight }}</li>
            @endforeach
        </ul>
    @endif

    <h2>شاخص‌ها</h2>
    <table class="kpis" width="100%">
        <tr>
            @foreach ([
                ['تماس‌های برقرارشده', $metrics['total_calls'], null],
                ['مکالمات تحلیل‌شده', $metrics['total_analyzed'], null],
                ['میانگین امتیاز مکالمه', $score($metrics['average_quality_score']), $deltas['quality_improvement_percent'] ?? null],
                ['میانگین کیفیت لید', $score($metrics['average_lead_score']), $deltas['lead_improvement_percent'] ?? null],
                ['شاخص رضایت مشتری', $score($metrics['average_sentiment']), $deltas['sentiment_improvement_percent'] ?? null],
                ['امتیاز اثربخشی', $score($metrics['effectiveness_score']), null],
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
        پاسخ‌داده‌شده: {{ $metrics['answered_calls'] }} —
        بی‌پاسخ: {{ $metrics['missed_calls'] }} —
        میانگین مدت: {{ $metrics['average_duration_label'] }}
    </p>

    <div class="keep">
        <h2>روند کیفیت مکالمه</h2>
        <p class="muted">محور افقی بازه زمانی است و محور عمودی امتیاز از ۰ تا ۱۰۰.</p>
        @if (! empty($charts['quality']))
            <img src="var:quality" style="width: 100%;">
        @else
            <p class="muted">امتیازی برای رسم نمودار نیست.</p>
        @endif
    </div>
    @include('exports.partials.pdf-trend-table', [
        'rows' => $profile['quality_trend'] ?? [],
        'columns' => [
            'بازه' => fn (array $row) => $row['label'] ?? '—',
            'امتیاز مکالمه' => fn (array $row) => $row['avg_score'] ?? '—',
            'تعداد' => fn (array $row) => $row['count'] ?? 0,
        ],
    ])

    <div class="keep">
        <h2>حجم تماس</h2>
        @if (! empty($charts['volume']))
            <img src="var:volume" style="width: 100%;">
        @else
            <p class="muted">تماسی برای نمودار نیست.</p>
        @endif
    </div>

    <div class="keep">
        <h2>کیفیت لید</h2>
        @if (! empty($charts['lead']))
            <img src="var:lead" style="width: 100%;">
        @else
            <p class="muted">امتیاز لید برای نمودار نیست.</p>
        @endif
    </div>

    @if ($dimensionRows !== [])
        <h2>ابعاد عملکرد</h2>
        @include('exports.partials.pdf-bars', ['rows' => $dimensionRows, 'suffix' => ''])
    @endif

    <h2>قوت‌ها، ضعف‌ها و مربیگری</h2>
    <table width="100%">
        <tr>
            <td class="panel" width="33%">
                <h3>نقاط قوت</h3>
                <ul>
                    @forelse ($profile['strengths'] as $item)
                        <li>{{ $item }}</li>
                    @empty
                        <li class="muted">موردی ثبت نشده است.</li>
                    @endforelse
                </ul>
            </td>
            <td class="panel" width="33%">
                <h3>نقاط ضعف</h3>
                <ul>
                    @forelse ($profile['weaknesses'] as $item)
                        <li>{{ $item }}</li>
                    @empty
                        <li class="muted">موردی ثبت نشده است.</li>
                    @endforelse
                </ul>
            </td>
            <td class="panel" width="34%">
                <h3>برنامه مربیگری</h3>
                <ul>
                    @foreach ($profile['coaching']['coaching_plan'] ?? [] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </td>
        </tr>
    </table>

    <h2>آخرین مکالمات تحلیل‌شده</h2>
    <table class="data" width="100%">
        <thead>
            <tr>
                <th>تاریخ</th>
                <th>مشتری</th>
                <th>مدت</th>
                <th>امتیاز مکالمه</th>
                <th>امتیاز لید</th>
                <th>احساس</th>
                <th>خلاصه</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($profile['recent_calls'] as $index => $call)
                <tr @class(['alt' => $index % 2 === 1])>
                    <td>{{ $call['date'] }}</td>
                    <td>{{ $call['customer'] }}</td>
                    <td>{{ $call['duration_label'] }}</td>
                    <td>{{ $call['quality_score'] ?? '—' }}</td>
                    <td>{{ $call['lead_score'] ?? '—' }}</td>
                    <td>{{ $call['sentiment'] ?? '—' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($call['summary'] ?? '—', 140) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">مکالمه تحلیل‌شده‌ای در این بازه نیست.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
