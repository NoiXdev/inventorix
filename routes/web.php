<?php

use App\Http\Controllers\Auth\MicrosoftAuthController;
use App\Http\Controllers\QrCodeGeneratorController;
use Illuminate\Support\Facades\Route;

Route::get('/gq', [QrCodeGeneratorController::class, 'generate'])->name('qg');

if (config('services.microsoft-azure.enabled')) {
    Route::get('/auth/microsoft/redirect', [MicrosoftAuthController::class, 'redirect'])
        ->name('auth.microsoft.redirect');
    Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback'])
        ->name('auth.microsoft.callback');
}
