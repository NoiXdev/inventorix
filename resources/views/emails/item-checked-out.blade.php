<x-mail::message>
# {{ trans('acceptance.mail.greeting', ['name' => $name]) }}

{!! trans('acceptance.mail.checked_out', ['item' => e($itemName)]) !!}

@if ($acceptUrl)
{{ trans('acceptance.mail.please_accept') }}

<x-mail::button :url="$acceptUrl">
{{ trans('acceptance.mail.accept_button') }}
</x-mail::button>

{{ trans('acceptance.mail.link_valid', ['days' => $linkDays]) }}
@endif

@if ($eula)
## {{ trans('acceptance.mail.eula_heading') }}

<x-mail::panel>
{{ $eula }}
</x-mail::panel>
@endif

{{ trans('acceptance.mail.outro') }}
</x-mail::message>
