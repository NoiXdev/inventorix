<?php

namespace Tests\Feature\SelfService;

use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MyAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private function acceptance(User $user, array $attrs = []): CheckoutAcceptance
    {
        $a = CheckoutAcceptance::create([
            'user_id' => $user->id, 'item_type' => 'asset',
            'item_id' => Asset::factory()->create()->id, 'item_name' => 'ThinkPad 7', 'sent_at' => now(),
        ]);
        $a->forceFill($attrs)->save();

        return $a;
    }

    public function test_the_owner_sees_their_acceptance(): void
    {
        $user = User::factory()->create();
        $a = $this->acceptance($user);

        $this->actingAs($user)->get("/app/my-items/acceptances/{$a->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('my-items/acceptance')
                ->where('acceptance.item_name', 'ThinkPad 7')
                ->where('acceptance.status', 'pending')
                ->where('submitUrl', route('app.my-items.acceptances.respond', $a)));
    }

    public function test_someone_elses_acceptance_is_not_found(): void
    {
        $a = $this->acceptance(User::factory()->create());
        $other = User::factory()->create();

        $this->actingAs($other)->get("/app/my-items/acceptances/{$a->id}")->assertNotFound();
        $this->actingAs($other)->post("/app/my-items/acceptances/{$a->id}", ['decision' => 'accept'])->assertNotFound();

        $this->assertSame('pending', $a->fresh()->status());
    }

    public function test_accepting_redirects_to_my_items(): void
    {
        $user = User::factory()->create();
        $a = $this->acceptance($user);

        $this->actingAs($user)->post("/app/my-items/acceptances/{$a->id}", ['decision' => 'accept'])
            ->assertRedirect('/app/my-items')
            ->assertSessionHas('success', 'Thank you, your answer was saved.');

        $a->refresh();
        $this->assertNotNull($a->accepted_at);
        $this->assertSame('127.0.0.1', $a->response_ip);
    }

    public function test_declining_with_a_note(): void
    {
        $user = User::factory()->create();
        $a = $this->acceptance($user);

        $this->actingAs($user)->post("/app/my-items/acceptances/{$a->id}", ['decision' => 'decline', 'note' => 'Wrong device'])
            ->assertRedirect('/app/my-items');

        $this->assertNotNull($a->fresh()->declined_at);
    }

    public function test_an_answered_acceptance_shows_an_error(): void
    {
        $user = User::factory()->create();
        $a = $this->acceptance($user, ['accepted_at' => now()]);

        $this->actingAs($user)->from('/app/my-items/acceptances/'.$a->id)
            ->post("/app/my-items/acceptances/{$a->id}", ['decision' => 'decline'])
            ->assertRedirect('/app/my-items/acceptances/'.$a->id)
            ->assertSessionHasErrors('decision');

        $a->refresh();
        $this->assertNotNull($a->accepted_at);
        $this->assertNull($a->declined_at);
    }

    public function test_invalid_decision_is_rejected(): void
    {
        $user = User::factory()->create();
        $a = $this->acceptance($user);

        $this->actingAs($user)->post("/app/my-items/acceptances/{$a->id}", ['decision' => 'maybe'])
            ->assertSessionHasErrors('decision');
    }
}
