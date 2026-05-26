<p>{{ trans('handover.mail.intro', ['name' => $handover->recipient_name]) }}</p>

<p>{{ trans('handover.mail.body', ['date' => $handover->signed_at->format('Y-m-d')]) }}</p>

<ul>
@foreach($handover->assets as $asset)
    <li>{{ optional($asset->model)->name }} — {{ $asset->serial_number }}</li>
@endforeach
</ul>

<p>{{ trans('handover.mail.outro') }}</p>
