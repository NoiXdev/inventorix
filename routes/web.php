<?php

use App\Http\Controllers\App\AccessoryController;
use App\Http\Controllers\App\AssetAttachmentController;
use App\Http\Controllers\App\AssetCheckoutController;
use App\Http\Controllers\App\AssetController;
use App\Http\Controllers\App\AssetExportController;
use App\Http\Controllers\App\AssetImportController;
use App\Http\Controllers\App\AssetModelController;
use App\Http\Controllers\App\AuthController;
use App\Http\Controllers\App\CategoryController;
use App\Http\Controllers\App\CustomFieldController;
use App\Http\Controllers\App\CustomFieldsetController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\DepreciationController;
use App\Http\Controllers\App\GeneratorController;
use App\Http\Controllers\App\HandoverController;
use App\Http\Controllers\App\IncidentController;
use App\Http\Controllers\App\LocationController;
use App\Http\Controllers\App\ManufacturerController;
use App\Http\Controllers\App\PasswordResetController;
use App\Http\Controllers\App\ReportController;
use App\Http\Controllers\App\ScanController;
use App\Http\Controllers\App\SettingsController;
use App\Http\Controllers\App\StatusLabelController;
use App\Http\Controllers\App\SupplierController;
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
    // Every route carries a `can:` check (see RouteCoverageTest); only logout,
    // the dashboard (widgets are filtered) and the `*/image` routes are open
    // to any logged-in user.
    Route::middleware(['auth', ApplyRuntimeSettings::class])->group(function () {
        // Resource routes mapped onto the section's view/create/edit/delete permissions.
        $guarded = fn (string $name, string $controller, string $section, array $except = []) => Route::resource($name, $controller)
            ->except($except)
            ->middlewareFor(['index', 'show'], "can:{$section}.view")
            ->middlewareFor(['create', 'store'], "can:{$section}.create")
            ->middlewareFor(['edit', 'update'], "can:{$section}.edit")
            ->middlewareFor('destroy', "can:{$section}.delete");

        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('manufacturers/{manufacturer}/image', [ManufacturerController::class, 'image'])
            ->name('manufacturers.image');
        $guarded('manufacturers', ManufacturerController::class, 'manufacturers', ['show']);
        Route::get('locations/{location}/image', [LocationController::class, 'image'])
            ->name('locations.image');
        $guarded('locations', LocationController::class, 'locations', ['show']);
        // Places were replaced by locations.
        Route::permanentRedirect('places', '/app/locations')->middleware('can:locations.view');
        Route::permanentRedirect('asset-types', '/app/categories?filter[category_type]=asset')->middleware('can:categories.view');
        Route::get('asset-models/{asset_model}/image', [AssetModelController::class, 'image'])
            ->name('asset-models.image');
        $guarded('asset-models', AssetModelController::class, 'models', ['show']);
        $guarded('depreciations', DepreciationController::class, 'depreciations', ['show']);
        Route::get('categories/{category}/image', [CategoryController::class, 'image'])
            ->name('categories.image');
        $guarded('categories', CategoryController::class, 'categories', ['show']);
        Route::get('suppliers/{supplier}/image', [SupplierController::class, 'image'])
            ->name('suppliers.image');
        $guarded('suppliers', SupplierController::class, 'suppliers', ['show']);
        $guarded('status-labels', StatusLabelController::class, 'statuslabels', ['show']);
        $guarded('custom-fields', CustomFieldController::class, 'customfields', ['show']);
        $guarded('custom-fieldsets', CustomFieldsetController::class, 'customfields', ['show']);
        Route::get('accessories/{accessory}/image', [AccessoryController::class, 'image'])
            ->name('accessories.image');
        $guarded('accessories', AccessoryController::class, 'accessories', ['show']);
        Route::get('assets/export', [AssetExportController::class, 'index'])->middleware('can:assets.view')->name('assets.export');
        Route::post('assets/import', [AssetImportController::class, 'store'])->middleware(['can:import', 'can:assets.create'])->name('assets.import');
        Route::get('assets/bulk-checkout', [AssetCheckoutController::class, 'bulkCreate'])->middleware('can:assets.checkout')->name('assets.bulk-checkout.create');
        Route::post('assets/bulk-checkout', [AssetCheckoutController::class, 'bulkCheckout'])->middleware('can:assets.checkout')->name('assets.bulk-checkout');
        Route::get('assets/{asset}/image', [AssetController::class, 'image'])->name('assets.image');
        Route::post('assets/{asset}/checkout', [AssetCheckoutController::class, 'checkout'])->middleware('can:assets.checkout')->name('assets.checkout');
        Route::post('assets/{asset}/checkin', [AssetCheckoutController::class, 'checkin'])->middleware('can:assets.checkin')->name('assets.checkin');
        $guarded('assets', AssetController::class, 'assets');
        Route::post('assets/{asset}/attachments', [AssetAttachmentController::class, 'store'])->middleware('can:assets.files')->name('assets.attachments.store');
        Route::delete('assets/{asset}/attachments/{attachment}', [AssetAttachmentController::class, 'destroy'])->middleware('can:assets.files')->name('assets.attachments.destroy');
        Route::post('assets/{asset}/incidents', [IncidentController::class, 'store'])->middleware('can:incidents.create')->name('assets.incidents.store');
        Route::put('assets/{asset}/incidents/{incident}', [IncidentController::class, 'update'])->middleware('can:incidents.edit')->name('assets.incidents.update');
        Route::delete('assets/{asset}/incidents/{incident}', [IncidentController::class, 'destroy'])->middleware('can:incidents.delete')->name('assets.incidents.destroy');
        Route::post('assets/{asset}/incidents/{incident}/close', [IncidentController::class, 'close'])->middleware('can:incidents.edit')->name('assets.incidents.close');
        Route::post('assets/{asset}/incidents/{incident}/reopen', [IncidentController::class, 'reopen'])->middleware('can:incidents.edit')->name('assets.incidents.reopen');
        Route::get('scan/resolve', [ScanController::class, 'resolve'])->middleware('can:assets.view')->name('scan.resolve');
        Route::middleware('can:assets.create')->group(function () {
            Route::get('qr-generator', [GeneratorController::class, 'index'])->name('qr-generator.index');
            Route::get('qr-generator/download', [GeneratorController::class, 'download'])->name('qr-generator.download');
            Route::get('qr-generator/codes', [GeneratorController::class, 'codes'])->name('qr-generator.codes');
        });
        // `manage` (UserPolicy): only a superuser may change a user who holds admin or superuser.
        Route::post('users/{user}/send-reset', [UserController::class, 'sendReset'])->middleware(['can:users.edit', 'can:manage,user'])->name('users.send-reset');
        Route::post('users/quick', [UserController::class, 'quickStore'])->middleware('can:users.create')->name('users.quick-store');
        $guarded('users', UserController::class, 'users')
            ->middlewareFor(['edit', 'update'], ['can:users.edit', 'can:manage,user'])
            ->middlewareFor('destroy', ['can:users.delete', 'can:manage,user']);
        // Deprecated People namespace (merged into Users in v3). Keep for the v3 line.
        Route::permanentRedirect('people', '/app/users')->middleware('can:users.view');
        Route::permanentRedirect('people/{person}', '/app/users/{person}')->middleware('can:users.view');
        Route::get('handovers', [HandoverController::class, 'index'])->middleware('can:handovers.view')->name('handovers.index');
        Route::get('handovers/create', [HandoverController::class, 'create'])->middleware('can:handovers.create')->name('handovers.create');
        Route::post('handovers', [HandoverController::class, 'store'])->middleware('can:handovers.create')->name('handovers.store');
        Route::get('handovers/{handover}', [HandoverController::class, 'show'])->middleware('can:handovers.view')->name('handovers.show');
        Route::middleware('can:reports.view')->group(function () {
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/{report}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
            Route::get('reports/{report}/export', [ReportController::class, 'export'])->name('reports.export');
            Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        });
        Route::middleware('can:superuser')->group(function () {
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
});

Route::middleware(ApplyRuntimeSettings::class)->group(function () {
    Route::get('/auth/microsoft/redirect', [MicrosoftAuthController::class, 'redirect'])
        ->name('auth.microsoft.redirect');
    Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback'])
        ->name('auth.microsoft.callback');
});

Route::middleware(['auth', ApplyRuntimeSettings::class, 'can:assets.view'])
    ->get('/attachments/{attachment}/open', [AttachmentController::class, 'show'])
    ->name('attachments.open');

Route::middleware(['signed'])
    ->get('/handover-pdf/{handover}', function (Handover $handover) {
        abort_unless($handover->pdf_path, 404);

        $disk = config('handover.disk');

        return Storage::disk($disk)->download($handover->pdf_path, "handover-{$handover->id}.pdf", ['content-type' => 'application/pdf']);
    })
    ->name('handover.pdf');
