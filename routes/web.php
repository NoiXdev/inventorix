<?php

use App\Enums\QrCodeGeneratorType;
use App\Http\Controllers\Auth\MicrosoftAuthController;
use App\Models\Asset;
use Ramsey\Uuid\Uuid;

Route::get('/gq', [\App\Http\Controllers\QrCodeGeneratorController::class, 'generate'])->name('qg');

if (config('services.microsoft-azure.enabled')) {
    Route::get('/auth/microsoft/redirect', [MicrosoftAuthController::class, 'redirect'])
        ->name('auth.microsoft.redirect');
    Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback'])
        ->name('auth.microsoft.callback');
}
