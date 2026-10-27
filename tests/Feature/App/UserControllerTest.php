<?php

// tests/Feature/App/UserControllerTest.php

namespace Tests\Feature\App;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['login_enabled' => true]);
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/app/users')->assertRedirect();
    }

    public function test_index_lists_users_with_asset_counts(): void
    {
        $u = User::factory()->withoutLogin()->create();
        Asset::factory()->count(2)->create(['owner_id' => $u->id]);

        $this->actingAs($this->actor())->get('/app/users')
            ->assertOk()
            ->assertInertia(fn (Assert $a) => $a->component('users/index')
                ->has('users.data')
                ->where('users.data', fn ($rows) => collect($rows)->firstWhere('id', $u->id)['assets_count'] === 2)
                ->etc());
    }

    public function test_store_derives_name(): void
    {
        $this->actingAs($this->actor())->post('/app/users', [
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'email' => 'ada@x.de', 'login_enabled' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['name' => 'Ada Lovelace', 'email' => 'ada@x.de', 'login_enabled' => false]);
    }

    public function test_store_validation(): void
    {
        $this->actingAs($this->actor())->post('/app/users', ['firstname' => '', 'lastname' => '', 'email' => 'nope'])
            ->assertSessionHasErrors(['firstname', 'lastname', 'email']);
    }

    public function test_update_derives_name(): void
    {
        $u = User::factory()->withoutLogin()->create();
        $this->actingAs($this->actor())->put('/app/users/'.$u->id, [
            'firstname' => 'Grace', 'lastname' => 'Hopper', 'login_enabled' => false,
        ])->assertRedirect();
        $this->assertSame('Grace Hopper', $u->refresh()->name);
    }

    public function test_destroy_blocked_when_owns_assets(): void
    {
        $u = User::factory()->withoutLogin()->create();
        Asset::factory()->create(['owner_id' => $u->id]);

        $this->actingAs($this->actor())->delete('/app/users/'.$u->id)
            ->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $u->id]);
    }

    public function test_can_delete_another_user_without_assets(): void
    {
        $other = User::factory()->create();
        $this->actingAs($this->actor())->delete("/app/users/{$other->id}")->assertRedirect('/app/users');
        $this->assertNull(User::find($other->id));
    }

    public function test_show_lists_owned_assets(): void
    {
        $u = User::factory()->withoutLogin()->create();
        Asset::factory()->count(3)->create(['owner_id' => $u->id]);

        $this->actingAs($this->actor())->get('/app/users/'.$u->id)
            ->assertInertia(fn (Assert $a) => $a->component('users/show')
                ->where('user.id', $u->id)->has('assets', 3)->etc());
    }

    public function test_quick_store_returns_id_and_name(): void
    {
        $this->actingAs($this->actor())
            ->postJson('/app/users/quick', ['firstname' => 'Ada', 'lastname' => 'Lovelace'])
            ->assertCreated()
            ->assertJson(['name' => 'Ada Lovelace']);
    }

    public function test_quick_store_creates_an_account_less_user(): void
    {
        $this->actingAs($this->actor())
            ->postJson('/app/users/quick', ['firstname' => 'Ada', 'lastname' => 'Lovelace']);

        $this->assertDatabaseHas('users', ['name' => 'Ada Lovelace', 'login_enabled' => false]);
    }

    public function test_quick_store_forces_login_disabled_even_if_requested(): void
    {
        Notification::fake();

        $this->actingAs($this->actor())->postJson('/app/users/quick', [
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'email' => 'ada@x.de', 'login_enabled' => true,
        ])->assertCreated();

        $user = User::where('name', 'Ada Lovelace')->firstOrFail();
        $this->assertFalse($user->login_enabled);
        $this->assertNull($user->password);
        Notification::assertNothingSent();
    }

    public function test_login_enabled_requires_an_email(): void
    {
        $this->actingAs($this->actor())->post('/app/users', [
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'email' => '', 'login_enabled' => true,
        ])->assertSessionHasErrors('email');
    }

    public function test_store_with_login_enabled_sets_password_and_sends_reset_email(): void
    {
        Notification::fake();

        $this->actingAs($this->actor())->post('/app/users', [
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'email' => 'ada@example.test', 'login_enabled' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user = User::where('email', 'ada@example.test')->firstOrFail();
        $this->assertNotNull($user->password);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_a_user_without_an_email_can_be_created_and_own_assets(): void
    {
        Notification::fake();

        $this->actingAs($this->actor())->post('/app/users', [
            'firstname' => 'No', 'lastname' => 'Mail', 'email' => '', 'login_enabled' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $u = User::where('name', 'No Mail')->firstOrFail();
        $this->assertNull($u->email);
        Notification::assertNothingSent();
        Asset::factory()->create(['owner_id' => $u->id]);
        $this->assertCount(1, $u->refresh()->assets);
    }

    public function test_cannot_disable_own_login(): void
    {
        $me = $this->actor();
        $this->actingAs($me)->put('/app/users/'.$me->id, [
            'firstname' => 'Me', 'lastname' => 'Self', 'email' => $me->email, 'login_enabled' => false,
        ])->assertSessionHasErrors('login_enabled');
    }

    public function test_cannot_delete_own_account(): void
    {
        $me = $this->actor();
        $this->actingAs($me)->delete('/app/users/'.$me->id)->assertForbidden();
    }

    public function test_edit_returns_user_fields_and_self_flag(): void
    {
        $actor = $this->actor();
        $target = User::factory()->create([
            'firstname' => 'Grace', 'lastname' => 'Hopper', 'email' => 'grace@example.test', 'login_enabled' => true,
        ]);

        $this->actingAs($actor)->get("/app/users/{$target->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('users/edit')
                ->where('user.id', $target->id)
                ->where('user.firstname', 'Grace')
                ->where('user.lastname', 'Hopper')
                ->where('user.email', 'grace@example.test')
                ->where('user.login_enabled', true)
                ->where('isSelf', false)
                ->missing('user.person_id')
                ->missing('personOptions')
                ->etc());

        $this->actingAs($actor)->get("/app/users/{$actor->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('users/edit')->where('isSelf', true)->etc());
    }

    public function test_email_must_be_unique_ignoring_self_on_update(): void
    {
        User::factory()->create(['email' => 'taken@example.test', 'login_enabled' => true]);
        $b = User::factory()->create(['email' => 'b@example.test', 'login_enabled' => true]);

        // duplicate on create
        $this->actingAs($this->actor())->post('/app/users', [
            'firstname' => 'Dup', 'lastname' => 'User', 'login_enabled' => true, 'email' => 'taken@example.test',
        ])->assertSessionHasErrors('email');

        // b keeps its own email on update (no unique error against itself)
        $this->actingAs($this->actor())->put("/app/users/{$b->id}", [
            'firstname' => $b->firstname, 'lastname' => $b->lastname, 'login_enabled' => true, 'email' => 'b@example.test',
        ])->assertSessionHasNoErrors();
    }

    public function test_admin_can_disable_another_users_login(): void
    {
        $other = User::factory()->create(['login_enabled' => true]);

        $this->actingAs($this->actor())->put("/app/users/{$other->id}", [
            'firstname' => $other->firstname, 'lastname' => $other->lastname, 'login_enabled' => false, 'email' => $other->email,
        ])->assertRedirect('/app/users');

        $this->assertFalse($other->fresh()->login_enabled);
    }

    public function test_send_reset_dispatches_notification(): void
    {
        Notification::fake();
        $target = User::factory()->create(['login_enabled' => true]);
        $this->actingAs($this->actor())->post("/app/users/{$target->id}/send-reset")->assertRedirect();
        Notification::assertSentTo($target, ResetPassword::class);
    }

    public function test_send_reset_404_when_user_has_no_email(): void
    {
        $target = User::factory()->create(['login_enabled' => false, 'email' => null]);
        $this->actingAs($this->actor())->post("/app/users/{$target->id}/send-reset")->assertNotFound();
    }

    public function test_auth_shared_user_name_uses_the_users_own_name(): void
    {
        // Identity lives directly on User now (Task 1); no more live person_id lookup.
        $user = User::factory()->create(['name' => 'Ada Lovelace', 'login_enabled' => true]);

        $this->actingAs($user)->get('/app')
            ->assertInertia(fn (Assert $p) => $p->where('auth.user.name', 'Ada Lovelace')->etc());
    }

    public function test_auth_shared_user_name_falls_back_to_email_when_no_name(): void
    {
        $loginOnly = User::factory()->create(['name' => null, 'email' => 'svc@x.de', 'login_enabled' => true]);

        $this->actingAs($loginOnly)->get('/app')
            ->assertInertia(fn (Assert $p) => $p->where('auth.user.name', 'svc@x.de')->etc());
    }
}
