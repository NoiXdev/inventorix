<x-mail::message>
{!! trans($acceptance->accepted_at ? 'acceptance.mail.response_accepted' : 'acceptance.mail.response_declined', ['user' => e($acceptance->user?->name ?? '—'), 'item' => e($acceptance->item_name)]) !!}

@if ($acceptance->note)
{{-- The note comes from the public page: Markdown in it must not turn into links or images. --}}
{{ trans('acceptance.mail.response_note', ['note' => preg_replace('/([\\\\`*_{}\[\]()#+\-.!|<>])/', '\\\\$1', $acceptance->note)]) }}
@endif

{{ trans('acceptance.mail.outro') }}
</x-mail::message>
