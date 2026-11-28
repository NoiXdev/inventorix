<?php

namespace Tests\Feature\Users;

use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserProfileFieldsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superuser()->create(['login_enabled' => true]);
    }

    private function profile(array $overrides = []): array
    {
        return array_merge([
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'email' => 'ada@example.test',
            'employee_num' => 'E-100', 'jobtitle' => 'Engineer',
            'phone' => '+49 30 1234', 'mobile' => '+49 170 1234',
            'start_date' => '2024-01-15', 'end_date' => '2026-12-31', 'notes' => 'Remote',
        ], $overrides);
    }

    public function test_store_persists_all_profile_fields(): void
    {
        $department = Department::factory()->create();
        $manager = User::factory()->create();
        $location = Location::factory()->create();

        $this->actingAs($this->admin)->post('/app/users', $this->profile([
            'department_id' => $department->id, 'manager_id' => $manager->id, 'location_id' => $location->id,
        ]))->assertRedirect('/app/users');

        $user = User::where('email', 'ada@example.test')->firstOrFail();
        $this->assertSame('E-100', $user->employee_num);
        $this->assertSame('Engineer', $user->jobtitle);
        $this->assertSame($department->id, $user->department_id);
        $this->assertSame($manager->id, $user->manager_id);
        $this->assertSame($location->id, $user->location_id);
        $this->assertSame('+49 30 1234', $user->phone);
        $this->assertSame('+49 170 1234', $user->mobile);
        $this->assertSame('2024-01-15', $user->start_date->toDateString());
        $this->assertSame('2026-12-31', $user->end_date->toDateString());
        $this->assertSame('Remote', $user->notes);
    }

    public function test_update_persists_all_profile_fields(): void
    {
        $user = User::factory()->create();
        $department = Department::factory()->create();
        $manager = User::factory()->create();
        $location = Location::factory()->create();

        $this->actingAs($this->admin)->put("/app/users/{$user->id}", $this->profile([
            'department_id' => $department->id, 'manager_id' => $manager->id, 'location_id' => $location->id,
        ]))->assertRedirect('/app/users');

        $user->refresh();
        $this->assertSame('E-100', $user->employee_num);
        $this->assertSame($department->id, $user->department_id);
        $this->assertSame($manager->id, $user->manager_id);
        $this->assertSame($location->id, $user->location_id);
        $this->assertSame('Remote', $user->notes);
    }

    public function test_user_cannot_be_their_own_manager(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->put("/app/users/{$user->id}", $this->profile(['manager_id' => $user->id]))
            ->assertSessionHasErrors('manager_id');
    }

    public function test_end_date_must_not_precede_start_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->put("/app/users/{$user->id}", $this->profile(['start_date' => '2025-05-01', 'end_date' => '2025-04-01']))
            ->assertSessionHasErrors('end_date');
    }

    public function test_deleted_department_is_rejected_unless_it_is_the_users_current_one(): void
    {
        $gone = Department::factory()->create();
        $user = User::factory()->create(['department_id' => $gone->id]);
        $gone->delete();

        $this->actingAs($this->admin)->put("/app/users/{$user->id}", $this->profile(['department_id' => $gone->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($gone->id, $user->fresh()->department_id);

        $other = User::factory()->create();
        $this->actingAs($this->admin)->put("/app/users/{$other->id}", $this->profile(['department_id' => $gone->id]))
            ->assertSessionHasErrors('department_id');
    }

    public function test_quick_store_still_works_with_only_names(): void
    {
        $this->actingAs($this->admin)->postJson('/app/users/quick', ['firstname' => 'Quick', 'lastname' => 'User'])
            ->assertCreated();
    }

    public function test_show_payload_includes_profile_fields(): void
    {
        $department = Department::factory()->create(['name' => 'Finance']);
        $manager = User::factory()->create(['name' => 'Boss Person']);
        $location = Location::factory()->create(['name' => 'Berlin']);
        $user = User::factory()->create($this->profile(['department_id' => $department->id, 'manager_id' => $manager->id, 'location_id' => $location->id]) + ['name' => 'Ada Lovelace']);

        $this->actingAs($this->admin)->get("/app/users/{$user->id}")->assertInertia(fn (Assert $page) => $page
            ->component('users/show')
            ->where('user.employee_num', 'E-100')
            ->where('user.jobtitle', 'Engineer')
            ->where('user.department_name', 'Finance')
            ->where('user.department_id', $department->id)
            ->where('user.manager', ['id' => $manager->id, 'name' => 'Boss Person'])
            ->where('user.location_name', 'Berlin')
            ->where('user.phone', '+49 30 1234')
            ->where('user.mobile', '+49 170 1234')
            ->where('user.start_date', '2024-01-15')
            ->where('user.end_date', '2026-12-31')
            ->where('user.notes', 'Remote'));
    }

    public function test_index_filters_by_department_and_searches_employee_number(): void
    {
        $finance = Department::factory()->create();
        $sales = Department::factory()->create();
        $inFinance = User::factory()->create(['department_id' => $finance->id, 'employee_num' => 'F-1', 'name' => 'Fin User']);
        User::factory()->create(['department_id' => $sales->id, 'employee_num' => 'S-1', 'name' => 'Sales User']);

        $this->actingAs($this->admin)->get("/app/users?filter[department_id]={$finance->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $inFinance->id)
                ->where('users.data.0.employee_num', 'F-1')
                ->where('users.data.0.department_name', $finance->name));

        $this->actingAs($this->admin)->get('/app/users?search=S-1')
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.name', 'Sales User'));
    }

    public function test_deleting_a_manager_clears_manager_on_reports(): void
    {
        $manager = User::factory()->create();
        $report = User::factory()->create(['manager_id' => $manager->id]);

        $this->assertTrue($manager->reports->contains($report));
        $manager->delete();

        $this->assertNull($report->fresh()->manager_id);
    }

    public function test_edit_offers_options_without_the_user_as_manager(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->get("/app/users/{$user->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->has('departmentOptions')->has('locationOptions')
            ->where('managerOptions', fn ($options) => ! collect($options)->contains('value', $user->id)));
    }
}
