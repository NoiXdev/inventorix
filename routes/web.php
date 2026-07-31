<?php

use App\Http\Controllers\App\AssetAttachmentController;
use App\Http\Controllers\App\AssetController;
use App\Http\Controllers\App\AssetExportController;
use App\Http\Controllers\App\AssetImportController;
use App\Http\Controllers\App\AssetModelController;
use App\Http\Controllers\App\AssetTypeController;
use App\Http\Controllers\App\AuthController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\GeneratorController;
use App\Http\Controllers\App\HandoverController;
use App\Http\Controllers\App\IncidentController;
use App\Http\Controllers\App\ManufacturerController;
use App\Http\Controllers\App\PasswordResetController;
use App\Http\Controllers\App\PersonController;
use App\Http\Controllers\App\PlaceController;
use App\Http\Controllers\App\ReportController;
use App\Http\Controllers\App\ScanController;
use App\Http\Controllers\App\SettingsController;
use App\Http\Controllers\App\UserController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\MicrosoftAuthController;
use App\Http\Middleware\ApplyRuntimeSettings;
use App\Models\Handover;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', fn () => redirect('/app'));

Route::prefix('app')->name('app.')->group(function () {
    // Guest-only: the Inertia login page and its submit handler.
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'show'])->name('login');
        Route::post('login', [AuthController::class, 'attempt'])->middleware('throttle:6,1')->name('login.attempt');
        Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
    });

    // Authenticated area: the rest of the app behind the web session guard.
    Route::middleware(['auth', ApplyRuntimeSettings::class])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('manufacturers', ManufacturerController::class)->except('show');
        Route::resource('places', PlaceController::class)->except('show');
        Route::resource('asset-types', AssetTypeController::class)->except('show');
        Route::resource('asset-models', AssetModelController::class)->except('show');
        Route::get('assets/export', [AssetExportController::class, 'index'])->name('assets.export');
        Route::post('assets/import', [AssetImportController::class, 'store'])->name('assets.import');
        Route::resource('assets', AssetController::class);
        Route::post('assets/{asset}/attachments', [AssetAttachmentController::class, 'store'])->name('assets.attachments.store');
        Route::delete('assets/{asset}/attachments/{attachment}', [AssetAttachmentController::class, 'destroy'])->name('assets.attachments.destroy');
        Route::post('assets/{asset}/incidents', [IncidentController::class, 'store'])->name('assets.incidents.store');
        Route::put('assets/{asset}/incidents/{incident}', [IncidentController::class, 'update'])->name('assets.incidents.update');
        Route::delete('assets/{asset}/incidents/{incident}', [IncidentController::class, 'destroy'])->name('assets.incidents.destroy');
        Route::post('assets/{asset}/incidents/{incident}/close', [IncidentController::class, 'close'])->name('assets.incidents.close');
        Route::post('assets/{asset}/incidents/{incident}/reopen', [IncidentController::class, 'reopen'])->name('assets.incidents.reopen');
        Route::get('scan/resolve', [ScanController::class, 'resolve'])->name('scan.resolve');
        Route::get('qr-generator', [GeneratorController::class, 'index'])->name('qr-generator.index');
        Route::get('qr-generator/download', [GeneratorController::class, 'download'])->name('qr-generator.download');
        Route::get('qr-generator/codes', [GeneratorController::class, 'codes'])->name('qr-generator.codes');
        Route::post('users/{user}/send-reset', [UserController::class, 'sendReset'])->name('users.send-reset');
        Route::resource('users', UserController::class)->except('show');
        Route::post('people/quick', [PersonController::class, 'quickStore'])
            ->name('people.quick-store');
        Route::resource('people', PersonController::class);
        Route::get('handovers', [HandoverController::class, 'index'])->name('handovers.index');
        Route::get('handovers/create', [HandoverController::class, 'create'])->name('handovers.create');
        Route::post('handovers', [HandoverController::class, 'store'])->name('handovers.store');
        Route::get('handovers/{handover}', [HandoverController::class, 'show'])->name('handovers.show');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
        Route::get('reports/{report}/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general.update');
        Route::put('settings/mail', [SettingsController::class, 'updateMail'])->name('settings.mail.update');
        Route::put('settings/storage', [SettingsController::class, 'updateStorage'])->name('settings.storage.update');
        Route::put('settings/auth', [SettingsController::class, 'updateAuth'])->name('settings.auth.update');
        Route::put('settings/warranty', [SettingsController::class, 'updateWarranty'])->name('settings.warranty.update');
        Route::post('settings/mail/test', [SettingsController::class, 'testMail'])->name('settings.mail.test');
        Route::post('settings/storage/test', [SettingsController::class, 'testStorage'])->name('settings.storage.test');
        Route::post('settings/warranty/test', [SettingsController::class, 'testWarranty'])->name('settings.warranty.test');
    });
});

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
