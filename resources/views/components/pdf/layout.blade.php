@props(['community', 'title', 'subtitle' => ''])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #18181b; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 24px 0 8px; }
        .muted { color: #71717a; }
        .header { width: 100%; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th { text-align: left; border-bottom: 1px solid #a1a1aa; padding: 6px 4px; font-size: 10px; text-transform: uppercase; color: #52525b; }
        table.lines td { border-bottom: 1px solid #e4e4e7; padding: 6px 4px; }
        .right { text-align: right; }
        .total td { font-weight: bold; border-bottom: none; border-top: 1px solid #a1a1aa; }
        .box { border: 1px solid #d4d4d8; padding: 10px; margin-top: 16px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <strong>{{ $community->name }}</strong><br>
                <span class="muted">{{ collect([$community->address_line_1, $community->city, $community->region, $community->postal_code])->filter()->implode(', ') }}</span>
            </td>
            <td class="right">
                <h1>{{ $title }}</h1>
                {{ $subtitle }}
            </td>
        </tr>
    </table>

    {{ $slot }}
</body>
</html>
