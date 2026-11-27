<x-mail::message>
# {{ trans('acceptance.mail.greeting', ['name' => $name]) }}

{!! trans('acceptance.mail.checked_in', ['item' => e($itemName)]) !!}

{{ trans('acceptance.mail.outro') }}
</x-mail::message>
