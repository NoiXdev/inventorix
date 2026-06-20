<?php

namespace Tests\Feature\Warranty;

use App\Filament\App\Clusters\Settings\Pages\ManageWarrantySettings;
use App\Models\User;
use App\Settings\WarrantySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageWarrantySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_settings(): void
    {
        $this->actingAs(User::factory()->create(['login_enabled' => true]));

        Livewire::test(ManageWarrantySettings::class)
            ->fillForm([
                'enabled' => true,
                'recipients' => ['ops@example.de'],
                'lead_days' => ['90', '30', '7'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = app(WarrantySettings::class)->refresh();
        $this->assertTrue($fresh->enabled);
        $this->assertSame(['ops@example.de'], $fresh->recipients);
        $this->assertSame([90, 30, 7], $fresh->lead_days);
    }
}
