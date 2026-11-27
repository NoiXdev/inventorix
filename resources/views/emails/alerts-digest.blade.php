<x-mail::message>
# {{ trans('alerts.digest.greeting') }}

@foreach ($sections as $key => $assets)
## {{ trans('alerts.digest.section.'.$key) }}

<x-mail::table>
| {{ trans('alerts.digest.col.asset') }} | {{ trans('alerts.digest.col.tag') }} | {{ trans('alerts.digest.col.assignee') }} | {{ trans('alerts.digest.col.location') }} | {{ trans('alerts.digest.col.date') }} |
| :--- | :--- | :--- | :--- | :--- |
@foreach ($assets as $asset)
| {{ str_replace('|', '\\|', $asset->displayName()) }} | {{ $asset->asset_tag ?? '—' }} | {{ str_replace('|', '\\|', $asset->assigneeName() ?? '—') }} | {{ str_replace('|', '\\|', $asset->location?->name ?? '—') }} | {{ (str_starts_with($key, 'audit') ? $asset->next_audit_date : $asset->expected_checkin)?->format('d.m.Y') }} |
@endforeach
</x-mail::table>

@endforeach
@foreach ($licenseSections as $key => $licenses)
## {{ trans('alerts.digest.section.'.$key) }}

@if ($key === 'accessory_low')
<x-mail::table>
| {{ trans('alerts.digest.col.accessory') }} | {{ trans('alerts.digest.col.remaining') }} |
| :--- | :--- |
@foreach ($licenses as $accessory)
| {{ str_replace('|', '\\|', (string) $accessory->name) }} | {{ $accessory->remaining() }} / {{ $accessory->qty }} (min. {{ $accessory->min_amt }}) |
@endforeach
</x-mail::table>

@continue
@endif

<x-mail::table>
| {{ trans('alerts.digest.col.license') }} | {{ trans('alerts.digest.col.seats') }} | {{ trans('alerts.digest.col.expires') }} |
| :--- | :--- | :--- |
@foreach ($licenses as $license)
| {{ str_replace('|', '\\|', $license->name) }} | {{ $key === 'license_low' ? $license->free_seats_count.' / '.$license->seats.' (min. '.$license->min_amt.')' : $license->seats }} | {{ $license->expiration_date?->format('d.m.Y') ?? '—' }} |
@endforeach
</x-mail::table>

@endforeach
{{ trans('alerts.digest.outro') }}
</x-mail::message>
