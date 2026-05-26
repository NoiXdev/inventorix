<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ trans('handover.pdf.title') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #111; }
        h1 { font-size: 18pt; margin: 0 0 4px; }
        h2 { font-size: 12pt; margin: 16px 0 4px; border-bottom: 1px solid #ccc; padding-bottom: 2px; }
        .meta { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
        .terms { background: #f7f7f7; padding: 8px; margin-top: 6px; white-space: pre-wrap; }
        .signature { border: 1px solid #aaa; padding: 6px; margin-top: 6px; text-align: center; }
        .signature img { max-height: 120px; }
    </style>
</head>
<body>
    <h1>{{ trans('handover.pdf.title') }}</h1>
    <div class="meta">
        {{ trans('handover.pdf.type') }}: {{ $handover->type->getLabel() }}
        @if($companyName)
            <br>{{ $companyName }}
        @endif
    </div>

    <h2>{{ trans('handover.pdf.recipient') }}</h2>
    <div>
        {{ $handover->recipient_name }}
        ({{ $handover->recipient_kind->value === 'internal' ? trans('handover.pdf.recipient_internal') : trans('handover.pdf.recipient_external') }})
        @if($handover->recipient_email)
            <br>{{ trans('handover.pdf.email') }}: {{ $handover->recipient_email }}
        @endif
    </div>

    <h2>{{ trans('handover.pdf.assets') }}</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Modell</th>
                <th>Seriennummer</th>
                <th>{{ trans('handover.pdf.state_transition', ['from' => '', 'to' => '']) }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($handover->assets as $i => $asset)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ optional($asset->model)->name }}</td>
                    <td>{{ $asset->serial_number }}</td>
                    <td>
                        {{ trans('handover.pdf.state_transition', [
                            'from' => \App\Enums\AssetState::from($asset->pivot->state_from)->getLabel(),
                            'to'   => \App\Enums\AssetState::from($asset->pivot->state_to)->getLabel(),
                        ]) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($handover->accessories)
        <h2>{{ trans('handover.pdf.accessories') }}</h2>
        <div>{{ $handover->accessories }}</div>
    @endif

    @if($handover->condition_notes)
        <h2>{{ trans('handover.pdf.condition') }}</h2>
        <div>{{ $handover->condition_notes }}</div>
    @endif

    <h2>{{ trans('handover.pdf.terms') }}</h2>
    <div class="terms">{{ $handover->terms_text }}</div>

    <h2>{{ trans('handover.pdf.signature') }}</h2>
    <div class="signature">
        <img src="data:image/png;base64,{{ $signatureBase64 }}" alt="signature">
        <div>{{ $handover->recipient_name }} — {{ $handover->signed_at->format('Y-m-d H:i') }}</div>
    </div>

    <h2>{{ trans('handover.pdf.handed_by') }}</h2>
    <div>
        {{ optional($handover->createdBy)->name }}<br>
        {{ trans('handover.pdf.signed_at') }}: {{ $handover->signed_at->format('Y-m-d H:i') }} UTC
        @if($handover->signature_ip)
            <br>{{ trans('handover.pdf.signed_ip') }}: {{ $handover->signature_ip }}
        @endif
    </div>
</body>
</html>
