<?php

namespace Tests\Feature\Filament;

use App\Filament\App\Clusters\Settings\Pages\ManageStorageSettings;
use App\Models\User;
use App\Settings\StorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ManageStorageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_s3_settings(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageStorageSettings::class)
            ->fillForm([
                'key' => 'AKIA-test',
                'secret' => 'super-secret',
                'region' => 'eu-central-1',
                'bucket' => 'inventorix',
                'endpoint' => 'https://minio.example.test',
                'use_path_style_endpoint' => true,
                'url' => 'https://cdn.example.test',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(StorageSettings::class)->refresh();
        $this->assertSame('AKIA-test', $settings->key);
        $this->assertSame('super-secret', $settings->secret);
        $this->assertSame('inventorix', $settings->bucket);
        $this->assertTrue($settings->use_path_style_endpoint);
    }

    public function test_blank_secret_keeps_the_existing_value(): void
    {
        $existing = app(StorageSettings::class);
        $existing->key = 'AKIA-test';
        $existing->secret = 'original-secret';
        $existing->region = 'eu-central-1';
        $existing->bucket = 'inventorix';
        $existing->endpoint = null;
        $existing->use_path_style_endpoint = false;
        $existing->url = null;
        $existing->save();

        $this->actingAs(User::factory()->create());

        Livewire::test(ManageStorageSettings::class)
            ->fillForm([
                'bucket' => 'changed-bucket',
                'secret' => '', // left blank on purpose
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(StorageSettings::class)->refresh();
        $this->assertSame('changed-bucket', $settings->bucket);
        $this->assertSame('original-secret', $settings->secret);
    }

    public function test_it_does_not_expose_the_secret_to_the_form(): void
    {
        $existing = app(StorageSettings::class);
        $existing->key = 'AKIA-test';
        $existing->secret = 'original-secret';
        $existing->region = 'eu-central-1';
        $existing->bucket = 'inventorix';
        $existing->endpoint = null;
        $existing->use_path_style_endpoint = false;
        $existing->url = null;
        $existing->save();

        $this->actingAs(User::factory()->create());

        Livewire::test(ManageStorageSettings::class)
            ->assertFormSet([
                'key' => 'AKIA-test',
                'secret' => null,
            ]);
    }

    public function test_connection_test_action_succeeds(): void
    {
        Storage::fake('s3');

        $this->actingAs(User::factory()->create());

        Livewire::test(ManageStorageSettings::class)
            ->fillForm([
                'key' => 'AKIA-test',
                'secret' => 'super-secret',
                'region' => 'eu-central-1',
                'bucket' => 'inventorix',
            ])
            ->call('save')
            ->callAction('testConnection')
            ->assertNotified(trans('settings.storage.test.success_title'));
    }

    public function test_connection_test_action_notifies_on_failure(): void
    {
        Storage::shouldReceive('disk')
            ->with('s3')
            ->andThrow(new \RuntimeException('Could not reach endpoint'));

        $this->actingAs(User::factory()->create());

        Livewire::test(ManageStorageSettings::class)
            ->fillForm([
                'key' => 'AKIA-test',
                'secret' => 'super-secret',
                'region' => 'eu-central-1',
                'bucket' => 'inventorix',
            ])
            ->call('save')
            ->callAction('testConnection')
            ->assertNotified(trans('settings.storage.test.failure_title'));
    }
}
