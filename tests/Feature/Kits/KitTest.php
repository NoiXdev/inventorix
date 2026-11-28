<?php

namespace Tests\Feature\Kits;

use App\Enums\Permission;
use App\Models\Accessory;
use App\Models\AssetModel;
use App\Models\Consumable;
use App\Models\Kit;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KitTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superuser()->create();
    }

    /** @return array{model: AssetModel, license: License, accessory: Accessory, consumable: Consumable} */
    private function items(): array
    {
        return [
            'model' => AssetModel::factory()->create(),
            'license' => License::factory()->create(),
            'accessory' => Accessory::factory()->create(),
            'consumable' => Consumable::factory()->create(),
        ];
    }

    public function test_create_with_a_line_of_each_type(): void
    {
        $i = $this->items();

        $this->actingAs($this->admin())->post('/app/kits', [
            'name' => 'New hire', 'notes' => 'Standard',
            'items' => [
                ['type' => 'model', 'id' => $i['model']->id, 'qty' => 1],
                ['type' => 'license', 'id' => $i['license']->id, 'qty' => 2],
                ['type' => 'accessory', 'id' => $i['accessory']->id, 'qty' => 3],
                ['type' => 'consumable', 'id' => $i['consumable']->id, 'qty' => 4],
            ],
        ])->assertRedirect('/app/kits')->assertSessionHasNoErrors();

        $kit = Kit::query()->sole();
        $this->assertSame('New hire', $kit->name);
        $this->assertCount(4, $kit->items);
        $this->assertSame(AssetModel::class, $kit->items->firstWhere('item_id', $i['model']->id)->item_type);
        $this->assertSame(4, $kit->items->firstWhere('item_id', $i['consumable']->id)->qty);
    }

    public function test_update_replaces_lines(): void
    {
        $i = $this->items();
        $kit = Kit::factory()->create();
        $kit->items()->create(['item_type' => AssetModel::class, 'item_id' => $i['model']->id, 'qty' => 1]);

        $this->actingAs($this->admin())->put("/app/kits/{$kit->id}", [
            'name' => 'Renamed',
            'items' => [['type' => 'accessory', 'id' => $i['accessory']->id, 'qty' => 2]],
        ])->assertRedirect('/app/kits')->assertSessionHasNoErrors();

        $kit->refresh();
        $this->assertSame('Renamed', $kit->name);
        $this->assertCount(1, $kit->items);
        $this->assertSame(Accessory::class, $kit->items->first()->item_type);
    }

    public function test_duplicate_line_is_rejected(): void
    {
        $model = AssetModel::factory()->create();

        $this->actingAs($this->admin())->post('/app/kits', [
            'name' => 'Dup',
            'items' => [
                ['type' => 'model', 'id' => $model->id, 'qty' => 1],
                ['type' => 'model', 'id' => $model->id, 'qty' => 2],
            ],
        ])->assertSessionHasErrors('items.1.id');
        $this->assertSame(0, Kit::count());
    }

    public function test_unknown_type_is_rejected(): void
    {
        $this->actingAs($this->admin())->post('/app/kits', [
            'name' => 'X', 'items' => [['type' => 'asset', 'id' => AssetModel::factory()->create()->id, 'qty' => 1]],
        ])->assertSessionHasErrors('items.0.type');
    }

    public function test_item_must_exist_and_be_live(): void
    {
        $gone = Accessory::factory()->create();
        $gone->delete();

        $this->actingAs($this->admin())->post('/app/kits', [
            'name' => 'X',
            'items' => [
                ['type' => 'accessory', 'id' => $gone->id, 'qty' => 1],
                ['type' => 'license', 'id' => (string) Str::uuid(), 'qty' => 1],
            ],
        ])->assertSessionHasErrors(['items.0.id', 'items.1.id']);
    }

    public function test_qty_is_bounded(): void
    {
        $model = AssetModel::factory()->create();

        $this->actingAs($this->admin())->post('/app/kits', [
            'name' => 'X', 'items' => [['type' => 'model', 'id' => $model->id, 'qty' => 1000]],
        ])->assertSessionHasErrors('items.0.qty');
    }

    public function test_name_is_unique_among_live_kits(): void
    {
        Kit::factory()->create(['name' => 'Same']);

        $this->actingAs($this->admin())->post('/app/kits', ['name' => 'Same'])->assertSessionHasErrors('name');

        Kit::query()->where('name', 'Same')->first()->delete();
        $this->actingAs($this->admin())->post('/app/kits', ['name' => 'Same'])->assertSessionHasNoErrors();
    }

    public function test_a_trashed_line_item_may_stay_on_update(): void
    {
        $accessory = Accessory::factory()->create();
        $kit = Kit::factory()->create();
        $kit->items()->create(['item_type' => Accessory::class, 'item_id' => $accessory->id, 'qty' => 1]);
        $accessory->delete();

        $this->actingAs($this->admin())->put("/app/kits/{$kit->id}", [
            'name' => $kit->name, 'items' => [['type' => 'accessory', 'id' => $accessory->id, 'qty' => 5]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(5, $kit->items()->sole()->qty);
    }

    public function test_show_lists_lines_with_availability(): void
    {
        $model = AssetModel::factory()->create(['name' => 'Laptop']);
        $accessory = Accessory::factory()->create(['name' => 'Mouse']);
        $kit = Kit::factory()->create();
        $kit->items()->create(['item_type' => AssetModel::class, 'item_id' => $model->id, 'qty' => 1]);
        $kit->items()->create(['item_type' => Accessory::class, 'item_id' => $accessory->id, 'qty' => 2]);
        $accessory->delete();

        $this->actingAs($this->admin())->get("/app/kits/{$kit->id}")
            ->assertInertia(fn (Assert $p) => $p->component('kits/show')
                ->where('kit.id', $kit->id)
                ->has('lines', 2)
                ->where('lines.0', fn ($line) => $line['type'] === 'model' && $line['name'] === 'Laptop' && $line['qty'] === 1 && $line['available'] === true)
                ->where('lines.1', fn ($line) => $line['type'] === 'accessory' && $line['name'] === 'Mouse' && $line['qty'] === 2 && $line['available'] === false));
    }

    public function test_edit_offers_the_current_trashed_item(): void
    {
        $accessory = Accessory::factory()->create();
        $kit = Kit::factory()->create();
        $kit->items()->create(['item_type' => Accessory::class, 'item_id' => $accessory->id, 'qty' => 1]);
        $accessory->delete();

        $this->actingAs($this->admin())->get("/app/kits/{$kit->id}/edit")
            ->assertInertia(fn (Assert $p) => $p->component('kits/edit')
                ->where('kit.items.0', fn ($line) => $line['type'] === 'accessory' && $line['id'] === $accessory->id)
                ->where('accessoryOptions.0.value', $accessory->id));
    }

    public function test_index_rows(): void
    {
        $kit = Kit::factory()->create(['name' => 'Starter']);
        $kit->items()->create(['item_type' => AssetModel::class, 'item_id' => AssetModel::factory()->create()->id, 'qty' => 1]);

        $this->actingAs($this->admin())->get('/app/kits')
            ->assertInertia(fn (Assert $p) => $p->component('kits/index')
                ->where('kits.data.0.name', 'Starter')
                ->where('kits.data.0.items_count', 1));
    }

    public function test_needs_view_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/app/kits')->assertForbidden();
    }

    public function test_destroy_soft_deletes(): void
    {
        $kit = Kit::factory()->create();

        $this->actingAs($this->admin())->delete("/app/kits/{$kit->id}")->assertRedirect('/app/kits');
        $this->assertSoftDeleted($kit);
    }

    public function test_kit_item_availability(): void
    {
        $accessory = Accessory::factory()->create();
        $kit = Kit::factory()->create();
        $kit->items()->create(['item_type' => Accessory::class, 'item_id' => $accessory->id, 'qty' => 1]);

        $this->assertTrue($kit->items()->sole()->isAvailable());
        $accessory->delete();
        $this->assertFalse($kit->items()->sole()->isAvailable());
    }

    public function test_catalogue_has_kits_after_components(): void
    {
        $sections = array_column(Permission::catalogue(), 'section');
        $this->assertSame(array_search('components', $sections) + 1, array_search('kits', $sections));
    }
}
