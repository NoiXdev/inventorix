<?php

namespace Tests\Feature\Filament;

use App\Filament\App\Clusters\Settings\Pages\ManageGeneralSettings;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageGeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_the_app_name(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageGeneralSettings::class)
            ->fillForm(['app_name' => 'My Inventory'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('My Inventory', app(GeneralSettings::class)->refresh()->app_name);
    }
}
