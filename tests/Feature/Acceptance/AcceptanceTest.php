<?php

namespace Tests\Feature\Acceptance;

use App\Enums\CategoryType;
use App\Enums\SystemStatus;
use App\Mail\AcceptanceResponse;
use App\Mail\AlertsDigest;
use App\Mail\ItemCheckedIn;
use App\Mail\ItemCheckedOut;
use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\CheckoutAcceptance;
use App\Models\Consumable;
use App\Models\License;
use App\Models\StatusLabel;
use App\Models\User;
use App\Services\AccessoryService;
use App\Services\CheckoutService;
use App\Services\ConsumableService;
use App\Services\LicenseService;
use App\Settings\AlertSettings;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function asset(array $category): Asset
    {
        $cat = Category::factory()->create(['category_type' => CategoryType::ASSET, ...$category]);
        $model = AssetModel::factory()->create(['category_id' => $cat->id]);

        return Asset::factory()->unassigned()->create(['model_id' => $model->id, 'name' => 'ThinkPad 7', 'status_id' => StatusLabel::system(SystemStatus::STORAGE)->id]);
    }

    private function checkOut(Asset $asset, User $user): void
    {
        app(CheckoutService::class)->checkOut($asset, $user);
    }

    public function test_a_category_that_requires_acceptance_sends_a_link(): void
    {
        $asset = $this->asset(['require_acceptance' => true, 'eula_text' => 'Handle with care.']);
        $user = User::factory()->withoutLogin()->create(['email' => 'ada@example.test']);

        $this->checkOut($asset, $user);

        $acceptance = CheckoutAcceptance::query()->sole();
        $this->assertTrue($acceptance->isPending());
        $this->assertSame('ThinkPad 7', $acceptance->item_name);
        $this->assertSame('Handle with care.', $acceptance->eula_text);
        $this->assertSame('pending', $asset->refresh()->accepted);
        Mail::assertQueued(ItemCheckedOut::class, fn (ItemCheckedOut $m) => $m->hasTo('ada@example.test')
            && str_contains((string) $m->acceptUrl, "/acceptance/{$acceptance->id}") && str_contains((string) $m->acceptUrl, 'signature=')
            && $m->eula === 'Handle with care.');
    }

    public function test_no_flags_no_mail_and_check_in_mail_when_asked(): void
    {
        $plain = $this->asset([]);
        $this->checkOut($plain, User::factory()->create());
        Mail::assertNothingQueued();
        $this->assertSame(0, CheckoutAcceptance::count());

        $notify = $this->asset(['checkin_email' => true]);
        $user = User::factory()->create();
        $this->checkOut($notify, $user);
        Mail::assertQueued(ItemCheckedOut::class, fn (ItemCheckedOut $m) => $m->acceptUrl === null);
        app(CheckoutService::class)->checkIn($notify->refresh());
        Mail::assertQueued(ItemCheckedIn::class, fn (ItemCheckedIn $m) => $m->hasTo($user->email));
    }

    public function test_the_default_eula_is_used_when_the_category_says_so(): void
    {
        $general = app(GeneralSettings::class);
        $general->default_eula = 'Company rules.';
        $general->save();
        $asset = $this->asset(['use_default_eula' => true, 'eula_text' => 'ignored']);

        $this->checkOut($asset, User::factory()->create());

        Mail::assertQueued(ItemCheckedOut::class, fn (ItemCheckedOut $m) => $m->eula === 'Company rules.');
    }

    public function test_accepting_through_the_signed_link_without_login(): void
    {
        Storage::fake();
        $settings = app(AlertSettings::class);
        $settings->recipients = ['it@example.test'];
        $settings->save();
        $asset = $this->asset(['require_acceptance' => true, 'alert_on_response' => true]);
        $user = User::factory()->withoutLogin()->create(['name' => 'Ada']);
        $this->checkOut($asset, $user);
        $acceptance = CheckoutAcceptance::query()->sole();
        $link = URL::temporarySignedRoute('acceptances.show', now()->addDays(30), ['acceptance' => $acceptance->id]);

        $this->get("/acceptance/{$acceptance->id}")->assertForbidden();
        $page = $this->get($link)->assertOk();
        $page->assertInertia(fn (Assert $p) => $p->component('acceptance/respond')->where('acceptance.status', 'pending')->where('acceptance.item_name', 'ThinkPad 7'));
        $submitUrl = $page->viewData('page')['props']['submitUrl'];

        $this->from($link)->post($submitUrl, ['decision' => 'accept', 'signature_png' => self::PNG, 'note' => 'Got it'])->assertRedirect($link);

        $acceptance->refresh();
        $this->assertNotNull($acceptance->accepted_at);
        $this->assertSame('Got it', $acceptance->note);
        Storage::assertExists($acceptance->signature_path);
        $this->assertSame('accepted', $asset->refresh()->accepted);
        $entry = Activity::query()->where('subject_id', $asset->id)->where('event', 'accepted')->sole();
        $this->assertSame($user->id, $entry->causer_id);
        Mail::assertQueued(AcceptanceResponse::class, fn (AcceptanceResponse $m) => $m->hasTo('it@example.test'));

        $this->from($link)->post($submitUrl, ['decision' => 'decline'])->assertSessionHasErrors('decision');
        $this->get($link)->assertInertia(fn (Assert $p) => $p->where('acceptance.status', 'accepted'));
    }

    public function test_declining_and_an_expired_link(): void
    {
        $asset = $this->asset(['require_acceptance' => true]);
        $this->checkOut($asset, User::factory()->create());
        $acceptance = CheckoutAcceptance::query()->sole();

        $expired = URL::temporarySignedRoute('acceptances.show', now()->subMinute(), ['acceptance' => $acceptance->id]);
        $this->get($expired)->assertForbidden();

        $post = URL::temporarySignedRoute('acceptances.respond', now()->addDay(), ['acceptance' => $acceptance->id]);
        $this->post($post, ['decision' => 'decline', 'note' => 'Wrong model'])->assertRedirect();
        $this->assertNotNull($acceptance->refresh()->declined_at);
        $this->assertSame('declined', $asset->refresh()->accepted);
        $this->post($post, ['decision' => 'accept', 'signature_png' => 'not-a-png'])->assertSessionHasErrors('decision');
    }

    public function test_check_in_drops_an_open_acceptance(): void
    {
        $asset = $this->asset(['require_acceptance' => true]);
        $this->checkOut($asset, User::factory()->create());

        app(CheckoutService::class)->checkIn($asset->refresh());

        $this->assertSame(0, CheckoutAcceptance::count());
        $this->assertNull($asset->refresh()->accepted);
    }

    public function test_a_signed_handover_is_the_acceptance(): void
    {
        $asset = $this->asset(['require_acceptance' => true]);
        $recipient = User::factory()->create();
        $actor = User::factory()->superuser()->create();

        $this->actingAs($actor)->post('/app/handovers', [
            'type' => 'issue', 'recipient_kind' => 'internal', 'recipient_user_id' => $recipient->id,
            'recipient_name' => 'R', 'recipient_email' => null, 'asset_ids' => [$asset->id],
            'terms_text' => 'T', 'signature_png' => self::PNG,
        ])->assertRedirect();

        $acceptance = CheckoutAcceptance::query()->sole();
        $this->assertNotNull($acceptance->accepted_at);
        $this->assertStringStartsWith('Signed handover', $acceptance->note);
        $this->assertSame('accepted', $asset->refresh()->accepted);
        Mail::assertNotQueued(ItemCheckedOut::class);
    }

    public function test_licenses_accessories_and_consumables_ask_too(): void
    {
        $user = User::factory()->create();
        $licenseCat = Category::factory()->create(['category_type' => CategoryType::LICENSE, 'require_acceptance' => true]);
        $accessoryCat = Category::factory()->create(['category_type' => CategoryType::ACCESSORY, 'require_acceptance' => true]);
        $consumableCat = Category::factory()->create(['category_type' => CategoryType::CONSUMABLE, 'require_acceptance' => true]);

        $seat = app(LicenseService::class)->checkOut(License::factory()->seats(1)->create(['category_id' => $licenseCat->id]), $user);
        app(AccessoryService::class)->checkOut(Accessory::factory()->create(['qty' => 2, 'category_id' => $accessoryCat->id]), $user, 2);
        app(ConsumableService::class)->checkOut(Consumable::factory()->create(['qty' => 5, 'category_id' => $consumableCat->id]), $user);

        $this->assertEqualsCanonicalizing(['license', 'accessory', 'consumable'], CheckoutAcceptance::query()->pluck('item_type')->all());
        Mail::assertQueued(ItemCheckedOut::class, 3);

        app(LicenseService::class)->checkIn($seat);
        $this->assertSame(0, CheckoutAcceptance::query()->where('item_type', 'license')->count());
    }

    public function test_the_list_and_sending_the_link_again(): void
    {
        $asset = $this->asset(['require_acceptance' => true]);
        $user = User::factory()->create();
        $this->checkOut($asset, $user);
        $acceptance = CheckoutAcceptance::query()->sole();
        $viewer = User::factory()->withPermissions(['reports.view' => 1])->create();

        $this->actingAs($viewer)->get('/app/acceptances')
            ->assertInertia(fn (Assert $p) => $p->component('acceptances/index')->where('acceptances.data.0.id', $acceptance->id)->where('acceptances.data.0.can_remind', true));
        $this->actingAs($viewer)->post("/app/acceptances/{$acceptance->id}/remind")->assertSessionHas('success');
        Mail::assertQueued(ItemCheckedOut::class, fn (ItemCheckedOut $m) => $m->reminder);
        $this->assertNotNull($acceptance->refresh()->reminded_at);

        $this->actingAs(User::factory()->create())->get('/app/acceptances')->assertForbidden();
    }

    public function test_the_overview_lists_acceptances_still_open_after_three_days(): void
    {
        $settings = app(AlertSettings::class);
        $settings->enabled = true;
        $settings->recipients = ['it@example.test'];
        $settings->save();
        $old = $this->asset(['require_acceptance' => true]);
        $this->checkOut($old, User::factory()->create());
        CheckoutAcceptance::query()->update(['sent_at' => now()->subDays(4)]);
        $fresh = $this->asset(['require_acceptance' => true]);
        $this->checkOut($fresh, User::factory()->create());

        $this->artisan('alerts:send')->assertSuccessful();

        Mail::assertSent(AlertsDigest::class, fn (AlertsDigest $m) => $m->licenseSections['acceptance_pending']->count() === 1);
    }

    public function test_an_accessory_acceptance_lapses_only_when_all_units_are_back(): void
    {
        $category = Category::factory()->create(['category_type' => CategoryType::ACCESSORY, 'require_acceptance' => true]);
        $accessory = Accessory::factory()->create(['qty' => 2, 'category_id' => $category->id]);
        $user = User::factory()->create();
        app(AccessoryService::class)->checkOut($accessory, $user, 2);
        [$first, $second] = $accessory->checkouts()->orderBy('id')->get()->all();

        app(AccessoryService::class)->checkIn($second);
        $this->assertSame(1, CheckoutAcceptance::query()->pending()->count());

        app(AccessoryService::class)->checkIn($first);
        $this->assertSame(0, CheckoutAcceptance::query()->pending()->count());
    }

    public function test_pending_rows_offer_a_link_to_hand_over_also_for_users_without_email(): void
    {
        $asset = $this->asset(['require_acceptance' => true]);
        $this->checkOut($asset, User::factory()->withoutLogin()->create(['email' => null]));
        Mail::assertNothingQueued();

        $this->actingAs(User::factory()->withPermissions(['reports.view' => 1])->create())->get('/app/acceptances')
            ->assertInertia(fn (Assert $p) => $p->where('acceptances.data.0.can_remind', false)
                ->where('acceptances.data.0.link', fn (string $link) => str_contains($link, '/acceptance/') && str_contains($link, 'signature=')));
    }

    public function test_markdown_in_a_note_does_not_become_a_link_in_the_it_mail(): void
    {
        $acceptance = CheckoutAcceptance::create(['user_id' => User::factory()->create()->id, 'item_type' => 'asset', 'item_id' => (string) Str::uuid(), 'item_name' => 'X']);
        $acceptance->forceFill(['declined_at' => now(), 'note' => '[click here](https://evil.example) ![img](https://evil.example/x.png)'])->save();

        $html = (new AcceptanceResponse($acceptance->fresh('user')))->render();

        $this->assertStringNotContainsString('href="https://evil.example"', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_a_link_signed_for_one_acceptance_does_not_open_another(): void
    {
        $a = $this->asset(['require_acceptance' => true]);
        $b = $this->asset(['require_acceptance' => true]);
        $this->checkOut($a, User::factory()->create());
        $this->checkOut($b, User::factory()->create());
        [$first, $second] = CheckoutAcceptance::query()->orderBy('created_at')->get()->all();

        $link = URL::temporarySignedRoute('acceptances.show', now()->addDay(), ['acceptance' => $first->id]);
        $this->get(str_replace($first->id, $second->id, $link))->assertForbidden();
        $post = URL::temporarySignedRoute('acceptances.respond', now()->addDay(), ['acceptance' => $first->id]);
        $this->post(str_replace($first->id, $second->id, $post), ['decision' => 'accept'])->assertForbidden();
        $this->assertTrue($second->refresh()->isPending());
    }
}
