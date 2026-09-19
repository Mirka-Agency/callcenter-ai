<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Tahoma, sans-serif; font-size: 12px; color: #111; margin: 24px; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .meta { color: #666; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #e4e4e7; padding: 6px 8px; text-align: right; }
        th { background: #fafafa; }
        @media print { body { margin: 12px; } }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="meta">تاریخ خروجی: {{ shamsi(now()) }} — تعداد: {{ count($rows) }}</p>

    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}">موردی برای خروجی وجود ندارد.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
