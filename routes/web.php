<?php

use App\Http\Controllers\App\AuthController;
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
    // Guest-only: the Inertia login page and its submit handler.
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'show'])->name('login');
        Route::post('login', [AuthController::class, 'attempt'])->middleware('throttle:6,1')->name('login.attempt');
    });

    // Authenticated: everything else in the app shares the web session guard
    // with Filament, so logging in on either authenticates both.
    Route::middleware(['auth', ApplyRuntimeSettings::class])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', fn () => Inertia::render('dashboard'))->name('dashboard');
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
