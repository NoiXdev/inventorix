<?php

namespace Tests\Feature\Kits;

use App\Enums\SystemStatus;
use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Consumable;
use App\Models\ConsumableAssignment;
use App\Models\Kit;
use App\Models\KitItem;
use App\Models\License;
use App\Models\LicenseSeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class KitCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Kit $kit;

    private AssetModel $laptop;

    private License $license;

    private Accessory $mouse;

    private Consumable $toner;

    /** @var list<Asset> */
    private array $laptops;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->user = User::factory()->create(['name' => 'Nina New']);
        $this->laptop = AssetModel::factory()->create(['name' => 'ThinkPad']);
        $this->license = License::factory()->seats(2)->create(['name' => 'Office']);
        $this->mouse = Accessory::factory()->create(['name' => 'Mouse', 'qty' => 5]);
        $this->toner = Consumable::factory()->create(['name' => 'Toner', 'qty' => 10]);
        $this->laptops = [
            $this->asset(['asset_tag' => 'INV-1']),
            $this->asset(['asset_tag' => 'INV-2']),
            $this->asset(['asset_tag' => 'INV-3']),
        ];

        $this->kit = Kit::factory()->create(['name' => 'New hire']);
        $this->line(AssetModel::class, $this->laptop->id, 2);
        $this->line(License::class, $this->license->id, 1);
        $this->line(Accessory::class, $this->mouse->id, 2);
        $this->line(Consumable::class, $this->toner->id, 3);
    }

    private function asset(array $attributes = [], ?AssetModel $model = null): Asset
    {
        return Asset::factory()->unassigned()->inStatus(SystemStatus::NEW)
            ->create(['model_id' => ($model ?? $this->laptop)->id, ...$attributes]);
    }

    private function line(string $type, string $id, int $qty): KitItem
    {
        return $this->kit->items()->create(['item_type' => $type, 'item_id' => $id, 'qty' => $qty]);
    }

    private function modelLine(): KitItem
    {
        return $this->kit->items()->where('item_type', AssetModel::class)->where('item_id', $this->laptop->id)->sole();
    }

    private function admin(): User
    {
        return User::factory()->superuser()->create();
    }

    private function url(): string
    {
        return "/app/kits/{$this->kit->id}/checkout";
    }

    /** @param list<string>|null $assetIds */
    private function payload(?array $assetIds = null, array $extra = []): array
    {
        return [
            'user_id' => $this->user->id,
            'assets' => [$this->modelLine()->id => $assetIds ?? [$this->laptops[0]->id, $this->laptops[1]->id]],
            ...$extra,
        ];
    }

    private function assertNothingChanged(): void
    {
        foreach ($this->laptops as $asset) {
            $this->assertNotSame($this->user->id, $asset->refresh()->assignee_id);
        }
        $this->assertSame(2, $this->license->freeSeats()->count());
        $this->assertSame(0, AccessoryCheckout::query()->count());
        $this->assertSame(0, ConsumableAssignment::query()->count());
    }

    public function test_checks_every_line_out_to_the_user(): void
    {
        $this->actingAs($this->admin())->post($this->url(), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect("/app/users/{$this->user->id}")
            ->assertSessionHas('success', 'Kit checked out: 2 assets, 1 license, 2 accessories, 3 consumables.');

        $this->assertSame($this->user->id, $this->laptops[0]->refresh()->assignee_id);
        $this->assertSame(User::class, $this->laptops[0]->assignee_type);
        $this->assertSame($this->user->id, $this->laptops[1]->refresh()->assignee_id);
        $this->assertNull($this->laptops[2]->refresh()->assignee_id);
        $this->assertSame(1, LicenseSeat::query()->where('assigned_user_id', $this->user->id)->count());
        $this->assertSame(2, AccessoryCheckout::query()->where('assigned_type', User::class)->where('assigned_id', $this->user->id)->count());
        $this->assertSame(3, (int) ConsumableAssignment::query()->where('user_id', $this->user->id)->sum('qty'));

        $notes = Activity::query()->where('event', 'checked_out')->get()->map(fn (Activity $a) => $a->getProperty('note'));
        $this->assertCount(5, $notes); // 2 assets, license, accessory, consumable
        foreach ($notes as $note) {
            $this->assertSame('Kit: New hire', $note);
        }
    }

    public function test_an_archived_asset_is_rejected(): void
    {
        $this->laptops[1]->update(['archived' => true]);

        $this->actingAs($this->admin())->post($this->url(), $this->payload())
            ->assertSessionHasErrors('assets');
        $this->assertNothingChanged();
    }

    public function test_the_note_is_appended(): void
    {
        $this->actingAs($this->admin())->post($this->url(), $this->payload(extra: ['note' => 'Welcome']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Kit: New hire — Welcome', Activity::query()->where('subject_id', $this->laptops[0]->id)->where('event', 'checked_out')->sole()->getProperty('note'));
    }

    public function test_an_asset_taken_meanwhile_fails_the_whole_checkout(): void
    {
        $this->laptops[1]->forceFill(['assignee_type' => User::class, 'assignee_id' => User::factory()->create()->id])->save();

        $this->actingAs($this->admin())->post($this->url(), $this->payload())
            ->assertSessionHasErrors('kit');

        $this->assertNull($this->laptops[0]->refresh()->assignee_id);
        $this->assertSame(2, $this->license->freeSeats()->count());
        $this->assertSame(5, $this->mouse->refresh()->remaining());
        $this->assertSame(0, ConsumableAssignment::query()->count());
    }

    public function test_an_asset_of_another_model_is_rejected(): void
    {
        $other = $this->asset(model: AssetModel::factory()->create());

        $this->actingAs($this->admin())->post($this->url(), $this->payload([$this->laptops[0]->id, $other->id]))
            ->assertSessionHasErrors('assets');

        $this->assertNothingChanged();
    }

    public function test_each_model_line_needs_exactly_qty_assets(): void
    {
        $this->actingAs($this->admin())->post($this->url(), $this->payload([$this->laptops[0]->id]))
            ->assertSessionHasErrors('assets');
        $this->actingAs($this->admin())->post($this->url(), ['user_id' => $this->user->id, 'assets' => []])
            ->assertSessionHasErrors('assets');

        $this->assertNothingChanged();
    }

    public function test_the_same_asset_twice_is_rejected(): void
    {
        $this->actingAs($this->admin())->post($this->url(), $this->payload([$this->laptops[0]->id, $this->laptops[0]->id]))
            ->assertSessionHasErrors('assets');

        // Across lines (it is also of the wrong model there).
        $second = $this->line(AssetModel::class, AssetModel::factory()->create()->id, 1);
        $this->actingAs($this->admin())->post($this->url(), [
            'user_id' => $this->user->id,
            'assets' => [
                $this->modelLine()->id => [$this->laptops[0]->id, $this->laptops[1]->id],
                $second->id => [$this->laptops[0]->id],
            ],
        ])->assertSessionHasErrors('assets');

        $this->assertNothingChanged();
    }

    public function test_asset_ids_for_unknown_lines_are_rejected(): void
    {
        $licenseLine = $this->kit->items()->where('item_type', License::class)->sole();

        $this->actingAs($this->admin())->post($this->url(), [
            'user_id' => $this->user->id,
            'assets' => [
                $this->modelLine()->id => [$this->laptops[0]->id, $this->laptops[1]->id],
                $licenseLine->id => [$this->laptops[2]->id],
            ],
        ])->assertSessionHasErrors('assets');

        $this->actingAs($this->admin())->post($this->url(), [
            'user_id' => $this->user->id,
            'assets' => [
                $this->modelLine()->id => [$this->laptops[0]->id, $this->laptops[1]->id],
                (string) Str::uuid() => [$this->laptops[2]->id],
            ],
        ])->assertSessionHasErrors('assets');

        $this->assertNothingChanged();
    }

    public function test_each_line_type_needs_its_checkout_permission(): void
    {
        $actor = User::factory()->withPermissions([
            'kits.view' => 1, 'kits.checkout' => 1,
            'assets.checkout' => 1, 'licenses.checkout' => 0, 'accessories.checkout' => 1, 'consumables.checkout' => 1,
        ])->create();

        $this->actingAs($actor)->post($this->url(), $this->payload())
            ->assertSessionHasErrors(['kit' => 'You may not check out licenses.']);

        $this->assertNothingChanged();
    }

    public function test_a_deleted_item_fails_the_checkout(): void
    {
        $this->mouse->delete();

        $this->actingAs($this->admin())->post($this->url(), $this->payload())
            ->assertSessionHasErrors(['kit' => 'Mouse is no longer available.']);

        $this->assertNull($this->laptops[0]->refresh()->assignee_id);
        $this->assertSame(2, $this->license->freeSeats()->count());
        $this->assertSame(0, ConsumableAssignment::query()->count());
    }

    public function test_not_enough_stock_fails_the_checkout(): void
    {
        $this->toner->update(['qty' => 2]);

        $this->actingAs($this->admin())->post($this->url(), $this->payload())
            ->assertSessionHasErrors('kit');

        $this->assertNull($this->laptops[0]->refresh()->assignee_id);
        $this->assertSame(0, AccessoryCheckout::query()->count());
    }

    public function test_page_preselects_candidates_per_model_line(): void
    {
        Asset::factory()->assignedTo(User::factory()->create())->create(['model_id' => $this->laptop->id, 'asset_tag' => 'INV-0']);
        $this->asset(['asset_tag' => 'INV-9', 'archived' => true]);
        Asset::factory()->unassigned()->inStatus(SystemStatus::DEFECT)->create(['model_id' => $this->laptop->id, 'asset_tag' => 'INV-8']);
        $this->asset(['asset_tag' => 'INV-7'])->delete();
        $this->laptops[0]->update(['name' => 'Anna laptop', 'serial' => 'SN-A']);

        $this->actingAs($this->admin())->get($this->url())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('kits/checkout')
                ->where('kit.name', 'New hire')
                ->has('userOptions')
                ->has('lines', 4)
                ->where('lines.0.type', 'model')
                ->where('lines.0.name', 'ThinkPad')
                ->where('lines.0.qty', 2)
                ->where('lines.0.available', true)
                ->where('lines.0.stock', 3)
                ->has('lines.0.candidates', 3)
                ->where('lines.0.candidates.0', ['value' => $this->laptops[0]->id, 'label' => 'INV-1 – Anna laptop – SN-A'])
                ->where('lines.0.preselected', [$this->laptops[0]->id, $this->laptops[1]->id])
                ->where('lines.1.type', 'license')
                ->where('lines.1.stock', 2)
                ->missing('lines.1.candidates')
                ->where('lines.2.type', 'accessory')
                ->where('lines.2.stock', 5)
                ->where('lines.3.type', 'consumable')
                ->where('lines.3.stock', 10)
                ->where('blocked', []));
    }

    public function test_page_lists_blockers(): void
    {
        $this->toner->update(['qty' => 1]);
        $this->mouse->delete();
        $actor = User::factory()->withPermissions([
            'kits.view' => 1, 'kits.checkout' => 1,
            'assets.checkout' => 1, 'licenses.checkout' => 0, 'accessories.checkout' => 1, 'consumables.checkout' => 1,
        ])->create();

        $this->actingAs($actor)->get($this->url())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lines.2.available', false)
                ->where('lines.3.stock', 1)
                ->where('blocked', [
                    'You may not check out licenses.',
                    'Mouse is no longer available.',
                    'Only 1 of Toner available; the kit needs 3.',
                ]));
    }

    public function test_needs_the_kit_checkout_permission(): void
    {
        $actor = User::factory()->withPermissions(['kits.view' => 1])->create();

        $this->actingAs($actor)->get($this->url())->assertForbidden();
        $this->actingAs($actor)->post($this->url(), $this->payload())->assertForbidden();
        $this->assertNothingChanged();
    }
}
