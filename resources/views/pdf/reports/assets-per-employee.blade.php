{{-- resources/views/pdf/reports/assets-per-employee.blade.php --}}
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
        h2.employee { font-size: 14px; margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f2f2f2; font-size: 10px; text-transform: uppercase; }
        .empty { margin-top: 20px; color: #777; font-style: italic; }
    </style>
</head>
<body>
    @forelse ($groups as $index => $group)
        {{-- Every group after the first starts on a new page. --}}
        <div @if ($index > 0) style="page-break-before: always;" @endif>
            <div class="report-header">
                <h1>{{ $title }}</h1>
                <div class="report-meta">{{ $companyName }} &middot; {{ $generatedAt }}</div>
            </div>

            <h2 class="employee">{{ $group['employee'] }}</h2>

            <table>
                <thead>
                    <tr>
                        @foreach ($headings as $heading)
                            <th>{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group['rows'] as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="report-header">
            <h1>{{ $title }}</h1>
            <div class="report-meta">
                {{ $companyName }} &middot; {{ $generatedAt }}
                @if (! empty($filterSummary))
                    <br>{{ $filterSummary }}
                @endif
            </div>
        </div>
        <p class="empty">{{ __('evaluation.pdf.no_data') }}</p>
    @endforelse
</body>
</html>
