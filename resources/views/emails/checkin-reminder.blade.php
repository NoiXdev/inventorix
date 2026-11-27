<x-mail::message>
# {{ trans('alerts.reminder.greeting', ['name' => $name]) }}

{{ trans('alerts.reminder.intro') }}

<x-mail::table>
| {{ trans('alerts.reminder.col.asset') }} | {{ trans('alerts.reminder.col.tag') }} | {{ trans('alerts.reminder.col.serial') }} | {{ trans('alerts.reminder.col.due') }} |
| :--- | :--- | :--- | :--- |
@foreach ($assets as $asset)
| {{ $asset->displayName() }} | {{ $asset->asset_tag ?? '—' }} | {{ $asset->serial ?? '—' }} | {{ $asset->expected_checkin?->format('d.m.Y') }}@if ($asset->expected_checkin?->isBefore(today())) ({{ trans('alerts.reminder.overdue') }})@endif |
@endforeach
</x-mail::table>

{{ trans('alerts.reminder.outro') }}
</x-mail::message>
