<?php

namespace Tests\Feature\App;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GeneratorControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_download_returns_n_unique_uuids(): void
    {
        $res = $this->actingAs($this->actor())->get('/app/qr-generator/download?amount=5');
        $res->assertOk();
        $this->assertStringContainsString('text/plain', strtolower($res->headers->get('content-type') ?? ''));
        $lines = array_filter(explode("\n", $res->getContent()));
        $this->assertCount(5, $lines);
        $this->assertCount(5, array_unique($lines));
        foreach ($lines as $line) {
            $this->assertTrue(Str::isUuid(trim($line)));
        }
    }

    public function test_codes_returns_json_uuids(): void
    {
        $this->actingAs($this->actor())->getJson('/app/qr-generator/codes?amount=3')
            ->assertOk()
            ->assertJsonCount(3, 'uuids');
    }

    public function test_amount_clamped(): void
    {
        $this->actingAs($this->actor())->getJson('/app/qr-generator/codes?amount=99999')
            ->assertJsonCount(1000, 'uuids');
    }

    public function test_requires_auth(): void
    {
        $this->get('/app/qr-generator')->assertRedirect();
    }
}
