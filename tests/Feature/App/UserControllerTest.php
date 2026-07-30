<?php

// tests/Feature/App/UserControllerTest.php

namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Manufacturer;
use App\Models\Person;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_index_lists_users_with_linked_person_name_and_asset_count(): void
    {
        $person = Person::factory()->create(['name' => 'Grace Hopper']);
        $user = User::factory()->create(['person_id' => $person->id]);

        $manufacturer = Manufacturer::factory()->create(['name' => 'Acme']);
        $assetModel = AssetModel::factory()->create(['name' => 'Widget', 'manufacturer_id' => $manufacturer->id]);
        Asset::factory()->create(['model_id' => $assetModel->id, 'owner_id' => $person->id]);

        $this->actingAs($this->actor())->get('/app/users')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('users/index')
                ->has('users.data')
                ->where('users.data', fn ($rows) => collect($rows)->contains(
                    fn ($row) => $row['id'] === $user->id
                        && $row['name'] === 'Grace Hopper'
                        && $row['email'] === $user->email
                        && $row['assets_count'] === 1
                )));
    }

    public function test_index_row_has_no_person_shows_null_name(): void
    {
        User::factory()->create(['person_id' => null]);

        $this->actingAs($this->actor())->get('/app/users')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('users/index')
                ->has('users.data.0', fn (Assert $r) => $r
                    ->has('id')->has('name')->has('email')->has('login_enabled')->has('assets_count')->etc()));
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/users')->assertRedirect();
    }

    public function test_store_persists_person_id_and_sends_reset_when_login_enabled(): void
    {
        Notification::fake();
        $person = Person::factory()->create();

        $this->actingAs($this->actor())->post('/app/users', [
            'person_id' => $person->id, 'login_enabled' => true, 'email' => 'ada@example.test',
        ])->assertRedirect('/app/users');

        $user = User::where('email', 'ada@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame($person->id, $user->person_id);
        $this->assertNotNull($user->password);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_store_allows_no_login_and_no_email(): void
    {
        Notification::fake();
        $person = Person::factory()->create();

        $this->actingAs($this->actor())->post('/app/users', [
            'person_id' => $person->id, 'login_enabled' => false,
        ])->assertRedirect('/app/users');

        $user = User::where('person_id', $person->id)->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email);
        Notification::assertNothingSent();
    }

    public function test_store_allows_null_person_id(): void
    {
        $this->actingAs($this->actor())->post('/app/users', [
            'login_enabled' => false,
        ])->assertRedirect('/app/users')->assertSessionHasNoErrors();
    }

    public function test_email_required_when_login_enabled(): void
    {
        $this->actingAs($this->actor())->post('/app/users', [
            'login_enabled' => true,
        ])->assertSessionHasErrors('email');
    }

    public function test_person_id_must_exist(): void
    {
        $this->actingAs($this->actor())->post('/app/users', [
            'person_id' => (string) Str::uuid(),
            'login_enabled' => false,
        ])->assertSessionHasErrors('person_id');
    }

    public function test_email_must_be_unique_ignoring_self_on_update(): void
    {
        User::factory()->create(['email' => 'taken@example.test', 'login_enabled' => true]);
        $b = User::factory()->create(['email' => 'b@example.test', 'login_enabled' => true]);

        // duplicate on create
        $this->actingAs($this->actor())->post('/app/users', [
            'login_enabled' => true, 'email' => 'taken@example.test',
        ])->assertSessionHasErrors('email');

        // b keeps its own email on update (no unique error against itself)
        $this->actingAs($this->actor())->put("/app/users/{$b->id}", [
            'login_enabled' => true, 'email' => 'b@example.test',
        ])->assertSessionHasNoErrors();
    }

    public function test_cannot_delete_self(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor)->delete("/app/users/{$actor->id}")->assertForbidden();
        $this->assertNotNull(User::find($actor->id));
    }

    public function test_can_delete_another_user(): void
    {
        $other = User::factory()->create();
        $this->actingAs($this->actor())->delete("/app/users/{$other->id}")->assertRedirect('/app/users');
        $this->assertNull(User::find($other->id));
    }

    public function test_cannot_disable_own_login(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor)->put("/app/users/{$actor->id}", [
            'login_enabled' => false, 'email' => $actor->email,
        ])->assertSessionHasErrors('login_enabled');

        $this->assertTrue($actor->fresh()->login_enabled);
    }

    public function test_send_reset_dispatches_notification(): void
    {
        Notification::fake();
        $target = User::factory()->create(['login_enabled' => true]);
        $this->actingAs($this->actor())->post("/app/users/{$target->id}/send-reset")->assertRedirect();
        Notification::assertSentTo($target, ResetPassword::class);
    }

    public function test_admin_can_disable_another_users_login(): void
    {
        $other = User::factory()->create(['login_enabled' => true]);

        $this->actingAs($this->actor())->put("/app/users/{$other->id}", [
            'login_enabled' => false, 'email' => $other->email,
        ])->assertRedirect('/app/users');

        $this->assertFalse($other->fresh()->login_enabled);
    }

    public function test_update_changes_person_link(): void
    {
        $actor = $this->actor();
        $personA = Person::factory()->create();
        $personB = Person::factory()->create();
        $target = User::factory()->create(['person_id' => $personA->id, 'login_enabled' => true]);

        $this->actingAs($actor)->put("/app/users/{$target->id}", [
            'person_id' => $personB->id, 'login_enabled' => true, 'email' => $target->email,
        ])->assertRedirect('/app/users');

        $this->assertSame($personB->id, $target->fresh()->person_id);
    }

    public function test_create_exposes_person_options(): void
    {
        $person = Person::factory()->create(['name' => 'Ada Lovelace']);

        $this->actingAs($this->actor())->get('/app/users/create')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('users/create')
                ->where('personOptions', fn ($options) => collect($options)->contains(
                    fn ($o) => $o['value'] === $person->id && $o['label'] === 'Ada Lovelace'
                )));
    }

    public function test_edit_returns_self_flag_person_id_and_person_options(): void
    {
        $actor = $this->actor();
        $person = Person::factory()->create(['name' => 'Ada Lovelace']);
        $target = User::factory()->create(['person_id' => $person->id]);

        $this->actingAs($actor)->get("/app/users/{$target->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('users/edit')
                ->where('user.id', $target->id)
                ->where('user.person_id', $person->id)
                ->where('isSelf', false)
                ->where('personOptions', fn ($options) => collect($options)->contains(
                    fn ($o) => $o['value'] === $person->id && $o['label'] === 'Ada Lovelace'
                )));

        $this->actingAs($actor)->get("/app/users/{$actor->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('users/edit')->where('isSelf', true));
    }

    public function test_send_reset_404_when_user_has_no_email(): void
    {
        $target = User::factory()->create(['login_enabled' => false, 'email' => null]);
        $this->actingAs($this->actor())->post("/app/users/{$target->id}/send-reset")->assertNotFound();
    }

    public function test_auth_shared_user_name_uses_linked_person_name(): void
    {
        $person = Person::factory()->create(['name' => 'Ada Lovelace']);
        $user = User::factory()->create(['person_id' => $person->id, 'login_enabled' => true]);

        $this->actingAs($user)->get('/app')
            ->assertInertia(fn (Assert $p) => $p->where('auth.user.name', 'Ada Lovelace')->etc());
    }

    public function test_auth_shared_user_name_falls_back_to_email_when_no_person(): void
    {
        $loginOnly = User::factory()->create(['person_id' => null, 'email' => 'svc@x.de', 'login_enabled' => true]);

        $this->actingAs($loginOnly)->get('/app')
            ->assertInertia(fn (Assert $p) => $p->where('auth.user.name', 'svc@x.de')->etc());
    }
}
