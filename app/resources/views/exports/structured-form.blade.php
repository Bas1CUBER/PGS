<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} — Record #{{ $recordId }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; color: #172033; font-size: 10pt; margin: 28px; }
        .header { border-bottom: 3px solid #0b4aa2; padding-bottom: 12px; margin-bottom: 20px; }
        .brand { color: #0b4aa2; font-size: 18pt; font-weight: bold; }
        .sub, .meta { color: #64748b; font-size: 8pt; }
        h1 { font-size: 15pt; margin: 0 0 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; vertical-align: top; }
        th { width: 32%; background: #eef2f7; }
        .footer { margin-top: 24px; border-top: 1px solid #cbd5e1; padding-top: 8px; color: #64748b; font-size: 8pt; }
    </style>
</head>
<body>
    <div class="header"><div class="brand">PGS</div><div class="sub">Performance Governance System — TRC DOH</div></div>
    <h1>{{ $title }} — Record #{{ $recordId }}</h1>
    <table>
        @foreach ($data as $key => $value)
            @if (is_scalar($value) && (string) $value !== '')
                <tr><th>{{ str($key)->replace('_', ' ')->title() }}</th><td>{{ $value }}</td></tr>
            @endif
        @endforeach
    </table>
    <div class="footer">Generated {{ date('Y-m-d H:i') }} by {{ $generatedBy }}. This document reflects the saved system record.</div>
</body>
</html>
