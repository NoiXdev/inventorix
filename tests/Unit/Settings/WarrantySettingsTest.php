<?php

namespace Tests\Unit\Settings;

use App\Settings\WarrantySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_and_round_trips_settings(): void
    {
        $settings = app(WarrantySettings::class);
        $settings->enabled = true;
        $settings->recipients = ['ops@example.de', 'it@example.de'];
        $settings->lead_days = [90, 30, 7];
        $settings->save();

        $fresh = app(WarrantySettings::class)->refresh();

        $this->assertTrue($fresh->enabled);
        $this->assertSame(['ops@example.de', 'it@example.de'], $fresh->recipients);
        $this->assertSame([90, 30, 7], $fresh->lead_days);
    }

    public function test_defaults_are_seeded_by_migration(): void
    {
        $fresh = app(WarrantySettings::class);

        $this->assertFalse($fresh->enabled);
        $this->assertSame([], $fresh->recipients);
        $this->assertSame([90, 30, 7], $fresh->lead_days);
    }
}
