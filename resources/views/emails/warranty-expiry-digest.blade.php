<x-mail::message>
# {{ trans('warranty.mail.greeting') }}

{{ trans('warranty.mail.intro') }}

@foreach ($sections as $section)
## {{ $section['title'] }}

<x-mail::table>
| {{ trans('warranty.mail.col.owner') }} | {{ trans('warranty.mail.col.model') }} | {{ trans('warranty.mail.col.serial') }} | {{ trans('warranty.mail.col.guarantee_end') }} | {{ trans('warranty.mail.col.days_left') }} |
| :--- | :--- | :--- | :--- | ---: |
@foreach ($section['rows'] as $row)
| {{ $row->asset->owner?->name ?? '—' }} | {{ $row->asset->model?->name ?? '—' }} | {{ $row->asset->serial_number ?? '—' }} | {{ $row->asset->guarantee_end?->format('d.m.Y') }} | {{ $row->daysLeft }} |
@endforeach
</x-mail::table>

@endforeach
{{ trans('warranty.mail.outro') }}
</x-mail::message>
