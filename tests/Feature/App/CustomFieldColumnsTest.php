<?php

namespace Tests\Feature\App;

use App\Enums\CustomFieldElement;
use App\Models\Asset;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class CustomFieldColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_field_adds_a_named_column_to_assets(): void
    {
        $field = CustomField::factory()->create(['name' => 'MAC-Adresse (LAN)']);

        $this->assertSame('cf_mac_adresse_lan', $field->db_column);
        $this->assertTrue(Schema::hasColumn('assets', 'cf_mac_adresse_lan'));
    }

    public function test_umlauts_are_transliterated(): void
    {
        $this->assertSame('cf_groesse', CustomField::factory()->create(['name' => 'Größe'])->db_column);
    }

    public function test_clashing_names_get_a_number(): void
    {
        $a = CustomField::factory()->create(['name' => 'Color']);
        $b = CustomField::factory()->create(['name' => 'color!']);
        $c = CustomField::factory()->create(['name' => 'COLOR']);

        $this->assertSame(['cf_color', 'cf_color_2', 'cf_color_3'], [$a->db_column, $b->db_column, $c->db_column]);
    }

    public function test_long_names_are_cut_to_stay_under_the_identifier_limit(): void
    {
        $field = CustomField::factory()->create(['name' => str_repeat('very long name ', 10)]);

        $this->assertLessThanOrEqual(53, strlen($field->db_column));
        $this->assertTrue(Schema::hasColumn('assets', $field->db_column));
    }

    public function test_date_formats_get_date_columns(): void
    {
        $date = CustomField::factory()->create(['name' => 'Purchased', 'element' => CustomFieldElement::DATE_PICKER, 'format' => 'DATE']);
        $text = CustomField::factory()->create(['name' => 'Note']);

        $this->assertSame('date', Schema::getColumnType('assets', $date->db_column));
        $this->assertSame('text', Schema::getColumnType('assets', $text->db_column));
    }

    public function test_renaming_a_field_renames_its_column_and_keeps_the_values(): void
    {
        $field = CustomField::factory()->create(['name' => 'Old name']);
        $asset = Asset::factory()->create();
        DB::table('assets')->where('id', $asset->id)->update(['cf_old_name' => 'kept']);

        $field->update(['name' => 'New name']);

        $this->assertSame('cf_new_name', $field->fresh()->db_column);
        $this->assertFalse(Schema::hasColumn('assets', 'cf_old_name'));
        $this->assertSame('kept', DB::table('assets')->where('id', $asset->id)->value('cf_new_name'));
    }

    public function test_renaming_to_a_name_with_the_same_slug_keeps_the_column(): void
    {
        $field = CustomField::factory()->create(['name' => 'Color']);
        $field->update(['name' => 'COLOR']);

        $this->assertSame('cf_color', $field->fresh()->db_column);
    }

    public function test_a_soft_delete_keeps_the_column_and_a_force_delete_drops_it(): void
    {
        $field = CustomField::factory()->create(['name' => 'Gone']);

        $field->delete();
        $this->assertTrue(Schema::hasColumn('assets', 'cf_gone'));

        $field->restore();
        $field->forceDelete();
        $this->assertFalse(Schema::hasColumn('assets', 'cf_gone'));
    }

    public function test_changing_to_or_from_a_date_format_is_refused(): void
    {
        $field = CustomField::factory()->create(['name' => 'Text']);

        $this->expectException(LogicException::class);
        $field->update(['format' => 'DATE', 'element' => CustomFieldElement::DATE_PICKER]);
    }

    public function test_db_column_cannot_be_mass_assigned(): void
    {
        $field = CustomField::factory()->create(['name' => 'Safe', 'db_column' => 'serial_number']);

        $this->assertSame('cf_safe', $field->db_column);
    }

    public function test_the_request_refuses_date_format_switches_and_encryption_conflicts(): void
    {
        $user = User::factory()->create(['login_enabled' => true]);
        $field = CustomField::factory()->create(['name' => 'Plain']);
        $base = ['name' => 'Plain', 'element' => 'text', 'format' => 'ANY'];

        $this->actingAs($user)->put("/app/custom-fields/{$field->id}", [...$base, 'element' => 'date_picker', 'format' => 'DATE'])
            ->assertSessionHasErrors('format');

        $this->actingAs($user)->post('/app/custom-fields', [...$base, 'name' => 'D', 'element' => 'text', 'format' => 'DATE'])
            ->assertSessionHasErrors('format');

        $this->actingAs($user)->post('/app/custom-fields', [...$base, 'name' => 'E', 'field_encrypted' => true, 'is_unique' => true])
            ->assertSessionHasErrors('field_encrypted');

        $this->actingAs($user)->post('/app/custom-fields', ['name' => 'R', 'element' => 'radio', 'format' => 'ANY', 'field_values' => "a\nb", 'field_encrypted' => true])
            ->assertSessionHasErrors('field_encrypted');

        // Switching encryption is fine while no asset has a value…
        $this->actingAs($user)->put("/app/custom-fields/{$field->id}", [...$base, 'field_encrypted' => true])
            ->assertSessionHasNoErrors();

        // …but not once one does.
        DB::table('assets')->where('id', Asset::factory()->create()->id)->update(['cf_plain' => 'x']);
        $this->actingAs($user)->put("/app/custom-fields/{$field->id}", [...$base, 'field_encrypted' => false])
            ->assertSessionHasErrors('field_encrypted');
    }
}
