<?php

namespace Tests\Feature\App;

use App\Enums\CustomFieldElement;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\AssetType;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\User;
use App\Support\Assets\AssetHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetCustomFieldsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AssetModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['login_enabled' => true]);
        $this->model = AssetModel::factory()->create();
    }

    /** Attach fields to the model's fieldset, in order: [field, required]. */
    private function fieldset(array $fields): void
    {
        $set = CustomFieldset::factory()->create();
        $set->fields()->sync(collect($fields)->values()->mapWithKeys(
            fn (array $f, int $i) => [$f[0]->id => ['order' => $i, 'required' => $f[1]]],
        )->all());
        $this->model->update(['fieldset_id' => $set->id]);
    }

    private function payload(array $custom = [], array $overrides = []): array
    {
        return [
            'state' => 'new',
            'asset_type_id' => AssetType::factory()->create()->id,
            'model_id' => $this->model->id,
            'custom_fields' => $custom,
            ...$overrides,
        ];
    }

    public function test_store_writes_values_into_their_columns(): void
    {
        $mac = CustomField::factory()->create(['name' => 'MAC', 'format' => 'MAC']);
        $ports = CustomField::factory()->create(['name' => 'Ports', 'element' => CustomFieldElement::CHECKBOX, 'field_values' => "USB\nHDMI\nLAN"]);
        $this->fieldset([[$mac, true], [$ports, false]]);

        $this->actingAs($this->user)->post('/app/assets', $this->payload([
            'cf_mac' => '00:1A:2B:3C:4D:5E', 'cf_ports' => ['USB', 'LAN'],
        ]))->assertSessionHasNoErrors();

        $row = DB::table('assets')->first();
        $this->assertSame('00:1A:2B:3C:4D:5E', $row->cf_mac);
        $this->assertSame('USB, LAN', $row->cf_ports);
    }

    public function test_required_format_option_and_date_rules_apply(): void
    {
        $mac = CustomField::factory()->create(['name' => 'MAC', 'format' => 'MAC']);
        $size = CustomField::factory()->create(['name' => 'Size', 'element' => CustomFieldElement::SELECT, 'field_values' => "S\nM"]);
        $since = CustomField::factory()->create(['name' => 'Since', 'element' => CustomFieldElement::DATE_PICKER, 'format' => 'DATE']);
        $this->fieldset([[$mac, true], [$size, false], [$since, false]]);

        $this->actingAs($this->user)->post('/app/assets', $this->payload(['cf_mac' => '', 'cf_size' => 'XL', 'cf_since' => '31.12.2026']))
            ->assertSessionHasErrors(['custom_fields.cf_mac' => 'The MAC field is required.', 'custom_fields.cf_size', 'custom_fields.cf_since']);

        $this->actingAs($this->user)->post('/app/assets', $this->payload(['cf_mac' => 'not-a-mac']))
            ->assertSessionHasErrors('custom_fields.cf_mac');
    }

    public function test_unique_fields_reject_a_value_another_asset_has(): void
    {
        $tag = CustomField::factory()->create(['name' => 'Tag', 'is_unique' => true]);
        $this->fieldset([[$tag, false]]);
        $other = Asset::factory()->create();
        DB::table('assets')->where('id', $other->id)->update(['cf_tag' => 'T-1']);

        $this->actingAs($this->user)->post('/app/assets', $this->payload(['cf_tag' => 'T-1']))
            ->assertSessionHasErrors('custom_fields.cf_tag');

        // An asset keeps its own value on update.
        $this->actingAs($this->user)->put("/app/assets/{$other->id}", $this->payload(['cf_tag' => 'T-1']))
            ->assertSessionHasNoErrors();
    }

    public function test_fields_outside_the_fieldset_are_ignored_and_left_alone(): void
    {
        $in = CustomField::factory()->create(['name' => 'In']);
        CustomField::factory()->create(['name' => 'Out']);
        $this->fieldset([[$in, false]]);
        $asset = Asset::factory()->create(['model_id' => $this->model->id]);
        DB::table('assets')->where('id', $asset->id)->update(['cf_out' => 'untouched']);

        $this->actingAs($this->user)->put("/app/assets/{$asset->id}", $this->payload(['cf_in' => 'yes', 'cf_out' => 'hacked']))
            ->assertSessionHasNoErrors();

        $row = DB::table('assets')->where('id', $asset->id)->first();
        $this->assertSame('yes', $row->cf_in);
        $this->assertSame('untouched', $row->cf_out);
    }

    public function test_encrypted_values_are_stored_encrypted_and_shown_decrypted(): void
    {
        $pin = CustomField::factory()->create(['name' => 'PIN', 'field_encrypted' => true]);
        $this->fieldset([[$pin, false]]);

        $this->actingAs($this->user)->post('/app/assets', $this->payload(['cf_pin' => '1234']))->assertSessionHasNoErrors();

        $asset = Asset::sole();
        $stored = DB::table('assets')->value('cf_pin');
        $this->assertNotSame('1234', $stored);
        $this->assertSame('1234', Crypt::decryptString($stored));

        $this->actingAs($this->user)->get("/app/assets/{$asset->id}/edit")
            ->assertInertia(fn (Assert $p) => $p->where('asset.custom_fields.cf_pin', '1234'));
        $this->actingAs($this->user)->get("/app/assets/{$asset->id}")
            ->assertInertia(fn (Assert $p) => $p->where('customFields.0.name', 'PIN')->where('customFields.0.value', '1234'));

        // Saving the same value again must not re-encrypt it (which would log a change).
        $this->actingAs($this->user)->put("/app/assets/{$asset->id}", $this->payload(['cf_pin' => '1234']));
        $this->assertSame($stored, DB::table('assets')->value('cf_pin'));
    }

    public function test_edit_passes_values_and_the_form_gets_fields_per_model(): void
    {
        $ports = CustomField::factory()->create(['name' => 'Ports', 'element' => CustomFieldElement::CHECKBOX, 'field_values' => "USB\nLAN"]);
        $this->fieldset([[$ports, true]]);
        $asset = Asset::factory()->create(['model_id' => $this->model->id]);
        DB::table('assets')->where('id', $asset->id)->update(['cf_ports' => 'USB, LAN']);

        $this->actingAs($this->user)->get("/app/assets/{$asset->id}/edit")
            ->assertInertia(fn (Assert $p) => $p
                ->where('asset.custom_fields.cf_ports', ['USB', 'LAN'])
                ->where("customFieldsByModel.{$this->model->id}.0.db_column", 'cf_ports')
                ->where("customFieldsByModel.{$this->model->id}.0.required", true)
                ->where("customFieldsByModel.{$this->model->id}.0.options", ['USB', 'LAN']));
    }

    public function test_listed_fields_become_sortable_searchable_columns(): void
    {
        CustomField::factory()->create(['name' => 'Room', 'show_in_listview' => true]);
        CustomField::factory()->create(['name' => 'Secret', 'show_in_listview' => true, 'field_encrypted' => true]);
        $a = Asset::factory()->create();
        $b = Asset::factory()->create();
        DB::table('assets')->where('id', $a->id)->update(['cf_room' => 'B-2']);
        DB::table('assets')->where('id', $b->id)->update(['cf_room' => 'A-1']);

        $this->actingAs($this->user)->get('/app/assets?sort=assets.cf_room')
            ->assertInertia(fn (Assert $p) => $p
                ->where('customColumns', [['key' => 'cf_room', 'label' => 'Room']])
                ->where('assets.data.0.custom.cf_room', 'A-1'));

        $this->actingAs($this->user)->get('/app/assets?search=B-2')
            ->assertInertia(fn (Assert $p) => $p->has('assets.data', 1)->where('assets.data.0.id', $a->id));
    }

    public function test_history_labels_custom_fields_and_hides_encrypted_values(): void
    {
        $room = CustomField::factory()->create(['name' => 'Room']);
        $pin = CustomField::factory()->create(['name' => 'PIN', 'field_encrypted' => true]);
        $this->fieldset([[$room, false], [$pin, false]]);
        $asset = Asset::factory()->create(['model_id' => $this->model->id]);

        $this->actingAs($this->user)->put("/app/assets/{$asset->id}", $this->payload(['cf_room' => 'A-1', 'cf_pin' => '9999']));

        $changes = collect(AssetHistory::for($asset->fresh())[0]['changes'])->keyBy('field');
        $this->assertSame('Room', $changes['cf_room']['label']);
        $this->assertSame('A-1', $changes['cf_room']['new']);
        $this->assertSame('(encrypted)', $changes['cf_pin']['new']);
    }
}
