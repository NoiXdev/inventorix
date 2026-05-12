<?php

namespace Tests\Feature\QrPrint;

use App\Filament\App\Resources\Assets\Pages\EditAsset;
use App\Filament\App\Resources\Assets\Pages\ListAssets;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QrPrintAssetActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_print_action_dispatches_event_with_metadata(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $model = AssetModel::factory()->create(['name' => 'MacBook Pro 14"']);
        $asset = Asset::factory()->create([
            'serial_number' => 'SN-123',
            'model_id' => $model->id,
        ]);

        Livewire::test(EditAsset::class, ['record' => $asset->id])
            ->callAction('print_qr_single')
            ->assertDispatched('qr-print:open', function (string $name, array $params) use ($asset) {
                $items = $params['items'] ?? null;
                if (! is_array($items) || count($items) !== 1) return false;
                $item = $items[0];
                return $item['uuid'] === $asset->id
                    && ($item['metadata']['modelName'] ?? null) === 'MacBook Pro 14"'
                    && ($item['metadata']['serial'] ?? null) === 'SN-123';
            });
    }

    public function test_bulk_print_action_dispatches_event_with_selected_assets(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $model = AssetModel::factory()->create(['name' => 'Dell XPS']);
        $assets = Asset::factory()->count(3)->create(['model_id' => $model->id]);

        Livewire::test(ListAssets::class)
            ->callTableBulkAction('print_qr_bulk', $assets->pluck('id')->all())
            ->assertDispatched('qr-print:open', function (string $name, array $params) {
                $items = $params['items'] ?? null;
                return is_array($items) && count($items) === 3;
            });
    }
}
