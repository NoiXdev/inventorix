<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BulkEnableLoginTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/app/users/bulk-enable-login';

    private function disabledUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['login_enabled' => false], $attributes));
    }

    private function loginLogs(User $user)
    {
        return Activity::query()->where('subject_id', $user->id)->where('subject_type', $user->getMorphClass())->where('event', 'updated')
            ->get()->filter(fn ($a) => $a->attribute_changes?->has('attributes') && array_key_exists('login_enabled', $a->attribute_changes['attributes'] ?? []));
    }

    public function test_enables_login_for_the_selected_users(): void
    {
        $a = $this->disabledUser();
        $b = $this->disabledUser();

        $this->actingAs(User::factory()->superuser()->create())
            ->from('/app/users')
            ->post(self::URL, ['ids' => [$a->id, $b->id]])
            ->assertRedirect('/app/users')
            ->assertSessionHas('success', 'Login enabled for 2 users.');

        $this->assertTrue($a->fresh()->login_enabled);
        $this->assertTrue($b->fresh()->login_enabled);
        $this->assertCount(1, $this->loginLogs($a));
        $this->assertCount(1, $this->loginLogs($b));
    }

    public function test_already_enabled_users_are_left_alone(): void
    {
        $enabled = User::factory()->create(['login_enabled' => true]);
        $disabled = $this->disabledUser();

        $this->actingAs(User::factory()->superuser()->create())
            ->post(self::URL, ['ids' => [$enabled->id, $disabled->id]])
            ->assertSessionHas('success', 'Login enabled for 1 user.');

        $this->assertCount(0, $this->loginLogs($enabled));
        $this->assertCount(1, $this->loginLogs($disabled));
    }

    public function test_all_or_nothing_when_a_user_cannot_be_managed(): void
    {
        $plain = $this->disabledUser();
        $admin = $this->disabledUser(['name' => 'Ada Admin']);
        $admin->permissions = ['admin' => 1];
        $admin->save();

        $actor = User::factory()->withPermissions(['users.view' => 1, 'users.edit' => 1])->create();

        $this->actingAs($actor)
            ->post(self::URL, ['ids' => [$plain->id, $admin->id]])
            ->assertSessionHasErrors('ids');

        $this->assertStringContainsString('Ada Admin', session('errors')->first('ids'));
        $this->assertFalse($plain->fresh()->login_enabled);
        $this->assertFalse($admin->fresh()->login_enabled);
    }

    public function test_refused_when_the_target_holds_a_permission_the_actor_lacks(): void
    {
        $plain = $this->disabledUser();
        $powerful = $this->disabledUser(['name' => 'Pete Powerful']);
        $powerful->permissions = ['assets.delete' => 1];
        $powerful->save();

        $actor = User::factory()->withPermissions(['users.view' => 1, 'users.edit' => 1])->create();

        $this->actingAs($actor)
            ->post(self::URL, ['ids' => [$plain->id, $powerful->id]])
            ->assertSessionHasErrors('ids');

        $this->assertFalse($plain->fresh()->login_enabled);
        $this->assertFalse($powerful->fresh()->login_enabled);
    }

    public function test_needs_users_edit(): void
    {
        $target = $this->disabledUser();

        $this->actingAs(User::factory()->withPermissions(['users.view' => 1])->create())
            ->post(self::URL, ['ids' => [$target->id]])
            ->assertForbidden();
    }

    public function test_does_not_send_password_mails(): void
    {
        Notification::fake();
        $target = $this->disabledUser();
        $hash = $target->password;

        $this->actingAs(User::factory()->superuser()->create())
            ->post(self::URL, ['ids' => [$target->id]]);

        $this->assertTrue($target->fresh()->login_enabled);
        Notification::assertNothingSent();
        $this->assertSame($hash, $target->fresh()->password);
    }
}
