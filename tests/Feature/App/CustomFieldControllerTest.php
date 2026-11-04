<?php

namespace Tests\Feature\App;

use App\Enums\CustomFieldElement;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomFieldControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['login_enabled' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'MAC address',
            'element' => 'text',
            'format' => 'MAC',
            'help_text' => 'Colon-separated.',
            'is_unique' => true,
            'show_in_listview' => true,
            ...$overrides,
        ];
    }

    public function test_index_lists_fields_with_their_fieldset_count(): void
    {
        $field = CustomField::factory()->create();
        CustomFieldset::factory()->create()->fields()->attach($field);

        $this->actingAs($this->user)->get('/app/custom-fields')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('custom-fields/index')
                ->has('customFields.data', 1)
                ->where('customFields.data.0.element_label', 'Text')
                ->where('customFields.data.0.fieldsets_count', 1)
                ->has('elementOptions', 8));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/custom-fields')->assertRedirect();
    }

    public function test_store_creates_a_field_and_stamps_the_creator(): void
    {
        $this->actingAs($this->user)->post('/app/custom-fields', $this->payload())
            ->assertRedirect('/app/custom-fields')
            ->assertSessionHasNoErrors();

        $f = CustomField::sole();
        $this->assertSame('MAC address', $f->name);
        $this->assertSame(CustomFieldElement::TEXT, $f->element);
        $this->assertSame('MAC', $f->format);
        $this->assertTrue($f->is_unique);
        $this->assertTrue($f->show_in_listview);
        $this->assertFalse($f->show_in_email);
        $this->assertSame($this->user->id, $f->created_by);
        $this->assertStringStartsWith('regex:', $f->validationRule());
    }

    public function test_a_custom_regex_is_stored_as_a_rule_and_shown_without_prefix(): void
    {
        $this->actingAs($this->user)->post('/app/custom-fields', $this->payload([
            'format' => 'CUSTOM REGEX', 'custom_regex' => '/^[A-Z]{3}$/',
        ]))->assertSessionHasNoErrors();

        $f = CustomField::sole();
        $this->assertSame('regex:/^[A-Z]{3}$/', $f->format);
        $this->assertSame('CUSTOM REGEX', $f->formatName());
        $this->assertSame('regex:/^[A-Z]{3}$/', $f->validationRule());

        $this->actingAs($this->user)->get("/app/custom-fields/{$f->id}/edit")
            ->assertInertia(fn (Assert $p) => $p->where('customField.format', 'CUSTOM REGEX')
                ->where('customField.custom_regex', '/^[A-Z]{3}$/'));
    }

    public function test_an_invalid_custom_regex_is_rejected(): void
    {
        $this->actingAs($this->user)->post('/app/custom-fields', $this->payload([
            'format' => 'CUSTOM REGEX', 'custom_regex' => '/[unclosed/',
        ]))->assertSessionHasErrors('custom_regex');
    }

    public function test_text_only_formats_are_rejected_for_other_elements(): void
    {
        $this->actingAs($this->user)->post('/app/custom-fields', $this->payload([
            'element' => 'textarea', 'format' => 'EMAIL',
        ]))->assertSessionHasErrors('format');
    }

    public function test_choice_elements_require_options_and_others_drop_them(): void
    {
        $this->actingAs($this->user)->post('/app/custom-fields', $this->payload([
            'element' => 'select', 'format' => 'ANY', 'field_values' => '',
        ]))->assertSessionHasErrors('field_values');

        $this->actingAs($this->user)->post('/app/custom-fields', $this->payload([
            'name' => 'Color', 'element' => 'select', 'format' => 'ANY', 'field_values' => "Red\nGreen\n\n Blue ",
        ]))->assertSessionHasNoErrors();
        $this->assertSame(['Red', 'Green', 'Blue'], CustomField::where('name', 'Color')->sole()->options());

        $this->actingAs($this->user)->post('/app/custom-fields', $this->payload([
            'name' => 'Plain', 'field_values' => 'ignored',
        ]))->assertSessionHasNoErrors();
        $this->assertNull(CustomField::where('name', 'Plain')->sole()->field_values);
    }

    public function test_store_validates_input(): void
    {
        $this->actingAs($this->user)->post('/app/custom-fields', $this->payload([
            'name' => '', 'element' => 'bogus', 'format' => 'NOPE',
        ]))->assertSessionHasErrors(['name', 'element', 'format']);
    }

    public function test_update_changes_a_field(): void
    {
        $f = CustomField::factory()->create(['name' => 'Old']);
        $this->actingAs($this->user)->put("/app/custom-fields/{$f->id}", $this->payload(['name' => 'New']))
            ->assertRedirect('/app/custom-fields');
        $this->assertSame('New', $f->fresh()->name);
        $this->assertSame($this->user->id, $f->fresh()->updated_by);
    }

    public function test_destroy_soft_deletes_and_hides_the_field_from_its_fieldsets(): void
    {
        $f = CustomField::factory()->create();
        $set = CustomFieldset::factory()->create();
        $set->fields()->attach($f);

        $this->actingAs($this->user)->delete("/app/custom-fields/{$f->id}")
            ->assertRedirect('/app/custom-fields');
        $this->assertSoftDeleted($f);
        $this->assertSame($this->user->id, CustomField::withTrashed()->find($f->id)->deleted_by);
        $this->assertCount(0, $set->fresh()->fields);
    }
}
