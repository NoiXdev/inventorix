<?php

use App\Enums\QrCodeGeneratorType;
use App\Models\Asset;
use Ramsey\Uuid\Uuid;

Route::get('/gq', [\App\Http\Controllers\QrCodeGeneratorController::class, 'generate'])->name('qg');
