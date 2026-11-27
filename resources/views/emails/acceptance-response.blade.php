<x-mail::message>
{!! trans($acceptance->accepted_at ? 'acceptance.mail.response_accepted' : 'acceptance.mail.response_declined', ['user' => e($acceptance->user?->name ?? '—'), 'item' => e($acceptance->item_name)]) !!}

@if ($acceptance->note)
{{ trans('acceptance.mail.response_note', ['note' => $acceptance->note]) }}
@endif

{{ trans('acceptance.mail.outro') }}
</x-mail::message>
