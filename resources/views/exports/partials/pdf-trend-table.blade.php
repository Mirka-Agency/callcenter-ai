@if (! empty($rows))
    <table class="data" width="100%">
        <thead>
            <tr>
                @foreach (array_keys($columns) as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $index => $row)
                <tr @class(['alt' => $index % 2 === 1])>
                    @foreach ($columns as $column)
                        <td>{{ $column($row) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
