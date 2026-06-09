{{-- resources/views/pdf/reports/layout.blade.php --}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111; margin: 0; }
        .report-header { border-bottom: 2px solid #333; padding-bottom: 6px; margin-bottom: 12px; }
        .report-header h1 { font-size: 18px; margin: 0 0 2px; }
        .report-meta { font-size: 10px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f2f2f2; font-size: 10px; text-transform: uppercase; }
        .empty { margin-top: 20px; color: #777; font-style: italic; }
    </style>
</head>
<body>
    <div class="report-header">
        <h1>{{ $title }}</h1>
        <div class="report-meta">
            {{ $companyName }} &middot; {{ $generatedAt }}
            @if (! empty($filterSummary))
                <br>{{ $filterSummary }}
            @endif
        </div>
    </div>

    @if (count($rows) === 0)
        <p class="empty">{{ __('evaluation.pdf.no_data') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($headings as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
