<x-mail::message>
# {{ trans('alerts.digest.greeting') }}

@foreach ($sections as $key => $assets)
## {{ trans('alerts.digest.section.'.$key) }}

<x-mail::table>
| {{ trans('alerts.digest.col.asset') }} | {{ trans('alerts.digest.col.tag') }} | {{ trans('alerts.digest.col.assignee') }} | {{ trans('alerts.digest.col.location') }} | {{ trans('alerts.digest.col.date') }} |
| :--- | :--- | :--- | :--- | :--- |
@foreach ($assets as $asset)
| {{ $asset->displayName() }} | {{ $asset->asset_tag ?? '—' }} | {{ $asset->assigneeName() ?? '—' }} | {{ $asset->location?->name ?? '—' }} | {{ (str_starts_with($key, 'audit') ? $asset->next_audit_date : $asset->expected_checkin)?->format('d.m.Y') }} |
@endforeach
</x-mail::table>

@endforeach
{{ trans('alerts.digest.outro') }}
</x-mail::message>
