@php
    use SimpleSoftwareIO\QrCode\Facades\QrCode;

    $id = $getRecord()->id ?? null;
@endphp

@if ($id)
    <div class="p-4 flex justify-end">
        {!! QrCode::size(50)->generate($id) !!}
    </div>
@else
    <p class="text-sm text-gray-500">QR-Code wird angezeigt, sobald das Modell gespeichert wurde.</p>
@endif
