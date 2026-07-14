<?php

use App\Http\Controllers\App\ManufacturerController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\MicrosoftAuthController;
use App\Http\Controllers\QrCodeGeneratorController;
use App\Http\Middleware\ApplyRuntimeSettings;
use App\Models\Handover;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

Route::get('/', fn () => to_route('filament.app.pages.dashboard'));

Route::prefix('app')->name('app.')->group(function () {
    Route::get('/', fn () => Inertia::render('dashboard'))->name('dashboard');

    // Resource routes are guarded individually for now; the whole /app group
    // will get the `auth` middleware in Task 10.
    Route::middleware('auth')->group(function () {
        Route::resource('manufacturers', ManufacturerController::class)->except('show');
    });
});

Route::get('/gq', [QrCodeGeneratorController::class, 'generate'])->name('qg');

Route::middleware(ApplyRuntimeSettings::class)->group(function () {
    Route::get('/auth/microsoft/redirect', [MicrosoftAuthController::class, 'redirect'])
        ->name('auth.microsoft.redirect');
    Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback'])
        ->name('auth.microsoft.callback');
});

Route::middleware(['auth', ApplyRuntimeSettings::class])
    ->get('/attachments/{attachment}/open', [AttachmentController::class, 'show'])
    ->name('attachments.open');

Route::middleware(['signed'])
    ->get('/handover-pdf/{handover}', function (Handover $handover) {
        abort_unless($handover->pdf_path, 404);

        $disk = config('handover.disk');

        return Storage::disk($disk)->download($handover->pdf_path, "handover-{$handover->id}.pdf", ['content-type' => 'application/pdf']);
    })
    ->name('handover.pdf');
