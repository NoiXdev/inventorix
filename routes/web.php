<?php

use App\Http\Controllers\Auth\MicrosoftAuthController;
use App\Http\Controllers\QrCodeGeneratorController;
use App\Models\Handover;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', fn () => to_route('filament.app.pages.dashboard'));

Route::get('/gq', [QrCodeGeneratorController::class, 'generate'])->name('qg');

if (config('services.microsoft-azure.enabled')) {
    Route::get('/auth/microsoft/redirect', [MicrosoftAuthController::class, 'redirect'])
        ->name('auth.microsoft.redirect');
    Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback'])
        ->name('auth.microsoft.callback');
}

Route::middleware(['signed'])
    ->get('/handover-pdf/{handover}', function (Handover $handover) {
        abort_unless($handover->pdf_path, 404);

        $disk = config('handover.disk');

        return Storage::disk($disk)->download($handover->pdf_path, "handover-{$handover->id}.pdf", ['content-type' => 'application/pdf']);
    })
    ->name('handover.pdf');
