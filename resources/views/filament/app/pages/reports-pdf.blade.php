<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Carbay+ operational report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #173c32; font-size: 9px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .muted { color: #65756d; margin-bottom: 18px; }
        .summary { margin: 14px 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d7ded8; padding: 5px; text-align: left; }
        th { background: #f0f3ed; }
        td.number { text-align: right; }
    </style>
</head>
<body>
    <h1>Carbay+ operational report</h1>
    <p class="muted">{{ $start->toDateString() }} to {{ $end->toDateString() }}</p>
    <p class="summary">Income: GH₵ {{ number_format($summary['income'], 2) }} · Expenses and payouts: GH₵ {{ number_format($summary['expenses'], 2) }}</p>
    <table>
        <thead><tr>@foreach ($rows[0] ?? [] as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach (array_slice($rows, 1) as $row)
            <tr>@foreach ($row as $index => $value)<td class="{{ in_array($index, [8, 9], true) ? 'number' : '' }}">{{ is_numeric($value) && in_array($index, [8, 9], true) ? number_format((float) $value, 2) : $value }}</td>@endforeach</tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
