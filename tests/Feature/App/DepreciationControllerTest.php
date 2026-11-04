<?php

namespace Tests\Feature\App;

use App\Enums\DepreciationType;
use App\Models\Depreciation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DepreciationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'IT-Hardware',
            'months' => 36,
            'depreciation_min' => '50.00',
            'fiscal_year_start_month' => 4,
            'rate_multiplier' => '2.50',
            'depreciation_type' => 'percentage',
            ...$overrides,
        ];
    }

    public function test_index_lists_depreciations(): void
    {
        Depreciation::factory()->count(3)->create();
        $this->actingAs($this->user())->get('/app/depreciations')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('depreciations/index')
                ->has('depreciations.data', 3)
                ->where('depreciations.data.0.depreciation_type_label', 'Betrag'));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/depreciations')->assertRedirect();
    }

    public function test_create_passes_type_options(): void
    {
        $this->actingAs($this->user())->get('/app/depreciations/create')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('depreciations/create')->has('typeOptions', 2));
    }

    public function test_store_creates_a_depreciation_and_stamps_the_creator(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/app/depreciations', $this->payload())
            ->assertRedirect('/app/depreciations');

        $d = Depreciation::sole();
        $this->assertSame('IT-Hardware', $d->name);
        $this->assertSame(36, $d->months);
        $this->assertSame('50.00', $d->depreciation_min);
        $this->assertSame(4, $d->fiscal_year_start_month);
        $this->assertSame('2.50', $d->rate_multiplier);
        $this->assertSame(DepreciationType::PERCENTAGE, $d->depreciation_type);
        $this->assertSame($user->id, $d->created_by);
    }

    public function test_store_validates_input(): void
    {
        $this->actingAs($this->user())->post('/app/depreciations', $this->payload([
            'name' => '', 'months' => 0, 'depreciation_min' => -1,
            'fiscal_year_start_month' => 13, 'rate_multiplier' => 0, 'depreciation_type' => 'bogus',
        ]))->assertSessionHasErrors([
            'name', 'months', 'depreciation_min', 'fiscal_year_start_month', 'rate_multiplier', 'depreciation_type',
        ]);
    }

    public function test_a_percentage_floor_is_capped_at_100_but_an_amount_is_not(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/app/depreciations', $this->payload(['depreciation_min' => 150]))
            ->assertSessionHasErrors('depreciation_min');
        $this->actingAs($user)->post('/app/depreciations', $this->payload(['depreciation_type' => 'amount', 'depreciation_min' => 150]))
            ->assertSessionHasNoErrors();
    }

    public function test_edit_renders_the_depreciation(): void
    {
        $d = Depreciation::factory()->create(['name' => 'Möbel']);
        $this->actingAs($this->user())->get("/app/depreciations/{$d->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('depreciations/edit')
                ->where('depreciation.name', 'Möbel')
                ->where('depreciation.depreciation_type', 'amount'));
    }

    public function test_update_changes_a_depreciation(): void
    {
        $d = Depreciation::factory()->create(['name' => 'Old']);
        $user = $this->user();
        $this->actingAs($user)->put("/app/depreciations/{$d->id}", $this->payload(['name' => 'New']))
            ->assertRedirect('/app/depreciations');
        $this->assertSame('New', $d->fresh()->name);
        $this->assertSame($user->id, $d->fresh()->updated_by);
    }

    public function test_destroy_soft_deletes_and_stamps_the_deleter(): void
    {
        $d = Depreciation::factory()->create();
        $user = $this->user();
        $this->actingAs($user)->delete("/app/depreciations/{$d->id}")
            ->assertRedirect('/app/depreciations');
        $this->assertSoftDeleted($d);
        $this->assertSame($user->id, Depreciation::withTrashed()->find($d->id)->deleted_by);
    }
}
