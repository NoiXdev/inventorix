<?php

namespace Tests\Feature;

use App\Livewire\Scanner;
use App\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScannerRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_to_edit_for_existing_asset(): void
    {
        $asset = Asset::factory()->create();

        Livewire::test(Scanner::class)
            ->set('serialNumber', $asset->getKey())
            ->call('change')
            ->assertRedirect(route('filament.app.resources.assets.edit', $asset));
    }

    public function test_redirects_to_create_for_unknown_uuid(): void
    {
        $uuid = '00000000-0000-4000-8000-000000000000';

        Livewire::test(Scanner::class)
            ->set('serialNumber', $uuid)
            ->call('change')
            ->assertRedirect(route('filament.app.resources.assets.create', ['forceId' => $uuid]));
    }
}
