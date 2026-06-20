<?php

namespace Tests\Feature\Warranty;

use App\DataObjects\WarrantyScanResult;
use App\Mail\WarrantyExpiryDigest;
use App\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class WarrantyExpiryDigestTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_grouped_sections(): void
    {
        $expired = Asset::factory()->create(['guarantee_end' => now()->subDay()->toDateString()]);
        $soon = Asset::factory()->create(['guarantee_end' => now()->addDays(7)->toDateString()]);

        $results = new Collection([
            new WarrantyScanResult($expired, 'expired', -1),
            new WarrantyScanResult($soon, '7', 7),
        ]);

        $mailable = new WarrantyExpiryDigest($results);
        $mailable->assertHasSubject(trans('warranty.mail.subject', ['count' => 2]));

        $rendered = $mailable->render();

        $this->assertStringContainsString($expired->serial_number, $rendered);
        $this->assertStringContainsString($soon->serial_number, $rendered);
        $this->assertStringContainsString(trans('warranty.mail.section.expired'), $rendered);
        $this->assertStringContainsString(trans('warranty.mail.section.lead', ['days' => 7]), $rendered);
    }
}
