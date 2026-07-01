<?php

namespace Tests\Feature\Filament;

use App\Http\Middleware\ApplyRuntimeSettings;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Tests\TestCase;

class AppPanelMiddlewareTest extends TestCase
{
    /**
     * Regression guard: ApplyRuntimeSettings (which applies ALL runtime settings —
     * General, Mail, Auth and Storage) must be registered as Livewire *persistent*
     * middleware. Only persistent middleware run on Livewire's AJAX requests
     * (/livewire/update and the file-upload endpoint), which is where Filament
     * actually processes file uploads. If it is only in the panel's non-persistent
     * middleware() list it runs solely on the initial page load, so the S3
     * StorageSettings are never applied during an upload and it fails.
     */
    public function test_apply_runtime_settings_is_registered_as_persistent_middleware(): void
    {
        $persistentMiddleware = app(PersistentMiddleware::class)->getPersistentMiddleware();

        $this->assertContains(
            ApplyRuntimeSettings::class,
            $persistentMiddleware,
            'ApplyRuntimeSettings must be persistent so runtime settings are applied on Livewire upload/update requests.',
        );
    }
}
