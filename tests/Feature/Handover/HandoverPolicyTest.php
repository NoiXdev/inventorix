<?php

namespace Tests\Feature\Handover;

use App\Models\Handover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandoverPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_user_can_view_any_and_view_single(): void
    {
        $user = User::factory()->create(['login_enabled' => true]);
        $handover = Handover::factory()->create();

        $this->assertTrue($user->can('viewAny', Handover::class));
        $this->assertTrue($user->can('view', $handover));
    }

    public function test_create_update_delete_are_always_denied_from_ui(): void
    {
        $user = User::factory()->create(['login_enabled' => true]);
        $handover = Handover::factory()->create();

        $this->assertFalse($user->can('create', Handover::class));
        $this->assertFalse($user->can('update', $handover));
        $this->assertFalse($user->can('delete', $handover));
    }
}
