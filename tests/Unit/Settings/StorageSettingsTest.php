<?php

namespace Tests\Unit\Settings;

use App\Settings\StorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_values_and_round_trips_the_secret(): void
    {
        $settings = app(StorageSettings::class);
        $settings->key = 'AKIA-test';
        $settings->secret = 'super-secret';
        $settings->region = 'eu-central-1';
        $settings->bucket = 'inventorix';
        $settings->endpoint = 'https://minio.example.test';
        $settings->use_path_style_endpoint = true;
        $settings->url = 'https://cdn.example.test';
        $settings->save();

        $fresh = app(StorageSettings::class)->refresh();
        $this->assertSame('AKIA-test', $fresh->key);
        $this->assertSame('super-secret', $fresh->secret);
        $this->assertSame('eu-central-1', $fresh->region);
        $this->assertSame('inventorix', $fresh->bucket);
        $this->assertSame('https://minio.example.test', $fresh->endpoint);
        $this->assertTrue($fresh->use_path_style_endpoint);
        $this->assertSame('https://cdn.example.test', $fresh->url);
    }

    public function test_is_configured_requires_key_secret_and_bucket(): void
    {
        $settings = app(StorageSettings::class);
        $settings->key = 'AKIA-test';
        $settings->secret = 'super-secret';
        $settings->bucket = null;
        $settings->save();
        $this->assertFalse($settings->isConfigured());

        $settings->bucket = 'inventorix';
        $settings->save();
        $this->assertTrue($settings->isConfigured());
    }
}
