@php
    $rows = $rows ?? [];
    $suffix = $suffix ?? '';
@endphp
<table width="100%">
    @forelse ($rows as $row)
        <tr>
            <td width="34%" style="font-size: 9px; padding: 3px 0;">{{ $row['label'] }}</td>
            <td>
                <table width="100%"><tr>
                    <td width="{{ max(0, min(100, (int) ($row['percent'] ?? 0))) }}%" bgcolor="{{ $row['color'] }}" style="font-size: 1px; height: 8px;">&nbsp;</td>
                    <td></td>
                </tr></table>
            </td>
            <td width="18%" style="font-size: 9px; text-align: left; padding: 3px 0;">{{ $row['value'] }} {{ $suffix }}</td>
        </tr>
    @empty
        <tr><td class="muted">داده‌ای برای نمایش نیست.</td></tr>
    @endforelse
</table>
