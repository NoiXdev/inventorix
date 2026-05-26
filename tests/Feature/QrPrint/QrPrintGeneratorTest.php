<?php

namespace Tests\Feature\QrPrint;

use App\Filament\App\Pages\QrCodeGenerator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QrPrintGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_action_dispatches_open_event_with_n_uuids(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(QrCodeGenerator::class)
            ->set('data.amount', 5)
            ->call('print')
            ->assertDispatched('qr-print:open', function (string $name, array $params) {
                $items = $params['items'] ?? null;
                if (! is_array($items) || count($items) !== 5) {
                    return false;
                }
                foreach ($items as $item) {
                    if (! isset($item['uuid']) || ! is_string($item['uuid'])) {
                        return false;
                    }
                }

                return true;
            });
    }

    public function test_existing_txt_download_redirect_still_works(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(QrCodeGenerator::class)
            ->set('data.amount', 3)
            ->call('create')
            ->assertRedirect();
    }
}
