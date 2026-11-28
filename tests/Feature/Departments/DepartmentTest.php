<?php

namespace Tests\Feature\Departments;

use App\Enums\Permission;
use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superuser()->create(['login_enabled' => true]);
    }

    public function test_superuser_can_create_and_update(): void
    {
        $manager = User::factory()->create();
        $location = Location::factory()->create();

        $this->actingAs($this->admin)->post('/app/departments', [
            'name' => 'Finance', 'manager_id' => $manager->id, 'location_id' => $location->id, 'notes' => 'Money',
        ])->assertRedirect('/app/departments');

        $department = Department::where('name', 'Finance')->firstOrFail();
        $this->assertSame($manager->id, $department->manager_id);
        $this->assertSame($location->id, $department->location_id);

        $this->actingAs($this->admin)->put("/app/departments/{$department->id}", ['name' => 'Finance & Legal', 'manager_id' => null])
            ->assertRedirect('/app/departments');
        $this->assertSame('Finance & Legal', $department->fresh()->name);
        $this->assertNull($department->fresh()->manager_id);
    }

    public function test_name_is_unique_among_live_departments(): void
    {
        $existing = Department::factory()->create(['name' => 'Sales']);

        $this->actingAs($this->admin)->post('/app/departments', ['name' => 'Sales'])->assertSessionHasErrors('name');

        $existing->delete();
        $this->actingAs($this->admin)->post('/app/departments', ['name' => 'Sales'])->assertSessionDoesntHaveErrors();
    }

    public function test_index_requires_view_permission(): void
    {
        $user = User::factory()->withPermissions([Permission::USERS_VIEW->value])->create();
        $this->actingAs($user)->get('/app/departments')->assertForbidden();
    }

    public function test_index_rows(): void
    {
        $manager = User::factory()->create(['name' => 'Mia Manager']);
        $location = Location::factory()->create(['name' => 'HQ']);
        $department = Department::factory()->create(['name' => 'IT', 'manager_id' => $manager->id, 'location_id' => $location->id]);
        User::factory()->create()->forceFill(['department_id' => $department->id])->save();

        $this->actingAs($this->admin)->get('/app/departments')->assertInertia(fn (Assert $page) => $page
            ->component('departments/index')
            ->where('departments.data.0.name', 'IT')
            ->where('departments.data.0.manager_name', 'Mia Manager')
            ->where('departments.data.0.location_name', 'HQ')
            ->where('departments.data.0.users_count', 1));
    }

    public function test_destroy_soft_deletes(): void
    {
        $department = Department::factory()->create();

        $this->actingAs($this->admin)->delete("/app/departments/{$department->id}")->assertRedirect('/app/departments');
        $this->assertSoftDeleted($department);
    }

    public function test_destroy_is_refused_while_users_belong_to_it(): void
    {
        $department = Department::factory()->create();
        User::factory()->create()->forceFill(['department_id' => $department->id])->save();

        $this->actingAs($this->admin)->from('/app/departments')->delete("/app/departments/{$department->id}")
            ->assertRedirect('/app/departments')
            ->assertSessionHas('error', "Move this department's users first.");
        $this->assertNotSoftDeleted($department);
    }

    public function test_catalogue_has_departments_section(): void
    {
        $this->assertContains('departments', array_column(Permission::catalogue(), 'section'));
    }
}
