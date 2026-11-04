<?php

namespace Tests\Feature\App;

use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomFieldsetControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['login_enabled' => true]);
    }

    public function test_index_lists_fieldsets_with_their_field_count(): void
    {
        CustomFieldset::factory()->create()->fields()->attach(CustomField::factory()->count(2)->create());

        $this->actingAs($this->user)->get('/app/custom-fieldsets')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('custom-fieldsets/index')
                ->has('customFieldsets.data', 1)
                ->where('customFieldsets.data.0.fields_count', 2));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/custom-fieldsets')->assertRedirect();
    }

    public function test_create_preselects_auto_add_fields(): void
    {
        $auto = CustomField::factory()->create(['auto_add_to_fieldsets' => true]);
        CustomField::factory()->create();

        $this->actingAs($this->user)->get('/app/custom-fieldsets/create')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('custom-fieldsets/create')
                ->has('availableFields', 2)
                ->has('initialFields', 1)
                ->where('initialFields.0.id', $auto->id));
    }

    public function test_store_creates_a_fieldset_with_ordered_fields(): void
    {
        [$a, $b] = CustomField::factory()->count(2)->create();

        $this->actingAs($this->user)->post('/app/custom-fieldsets', [
            'name' => 'Laptop',
            'fields' => [['id' => $b->id, 'required' => true], ['id' => $a->id, 'required' => false]],
        ])->assertRedirect('/app/custom-fieldsets')->assertSessionHasNoErrors();

        $set = CustomFieldset::sole();
        $this->assertSame($this->user->id, $set->created_by);
        $this->assertSame([$b->id, $a->id], $set->fields->pluck('id')->all());
        $this->assertTrue((bool) $set->fields[0]->pivot->required);
        $this->assertFalse((bool) $set->fields[1]->pivot->required);
        $this->assertSame([$set->id], $a->fieldsets->pluck('id')->all());
    }

    public function test_store_rejects_unknown_deleted_and_duplicate_fields(): void
    {
        $field = CustomField::factory()->create();
        $trashed = CustomField::factory()->create();
        $trashed->delete();

        $this->actingAs($this->user)->post('/app/custom-fieldsets', [
            'name' => 'X',
            'fields' => [['id' => 'nope'], ['id' => $trashed->id], ['id' => $field->id], ['id' => $field->id]],
        ])->assertSessionHasErrors(['fields.0.id', 'fields.1.id', 'fields.2.id', 'fields.3.id']);
    }

    public function test_edit_renders_fields_in_order(): void
    {
        [$a, $b] = CustomField::factory()->count(2)->create();
        $set = CustomFieldset::factory()->create(['name' => 'Phones']);
        $set->fields()->sync([$a->id => ['order' => 1, 'required' => false], $b->id => ['order' => 0, 'required' => true]]);

        $this->actingAs($this->user)->get("/app/custom-fieldsets/{$set->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('custom-fieldsets/edit')
                ->where('customFieldset.name', 'Phones')
                ->where('customFieldset.fields.0.id', $b->id)
                ->where('customFieldset.fields.0.required', true)
                ->where('customFieldset.fields.1.id', $a->id));
    }

    public function test_update_resyncs_fields(): void
    {
        [$a, $b, $c] = CustomField::factory()->count(3)->create();
        $set = CustomFieldset::factory()->create();
        $set->fields()->attach([$a->id, $b->id]);

        $this->actingAs($this->user)->put("/app/custom-fieldsets/{$set->id}", [
            'name' => 'Renamed',
            'fields' => [['id' => $c->id, 'required' => true], ['id' => $a->id]],
        ])->assertRedirect('/app/custom-fieldsets');

        $set->refresh();
        $this->assertSame('Renamed', $set->name);
        $this->assertSame([$c->id, $a->id], $set->fields->pluck('id')->all());
    }

    public function test_destroy_soft_deletes_and_stamps_the_deleter(): void
    {
        $set = CustomFieldset::factory()->create();
        $this->actingAs($this->user)->delete("/app/custom-fieldsets/{$set->id}")
            ->assertRedirect('/app/custom-fieldsets');
        $this->assertSoftDeleted($set);
        $this->assertSame($this->user->id, CustomFieldset::withTrashed()->find($set->id)->deleted_by);
    }
}
