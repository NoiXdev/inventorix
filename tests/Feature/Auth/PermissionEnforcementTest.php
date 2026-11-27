<?php

namespace Tests\Feature\Auth;

use App\Enums\SystemStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\Depreciation;
use App\Models\Handover;
use App\Models\Incident;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\StatusLabel;
use App\Models\Supplier;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** @return array<string, array{0: string, 1: Closure(): string, 2: array<int, string>}> */
    public static function routes(): array
    {
        $asset = fn () => Asset::factory()->create();
        $incident = function (): string {
            $i = Incident::factory()->create();

            return "/app/assets/{$i->asset_id}/incidents/{$i->id}";
        };

        return [
            // Resource rows: one action each for the simple sections.
            'manufacturers.index' => ['get', fn () => '/app/manufacturers', ['manufacturers.view']],
            'manufacturers.store' => ['post', fn () => '/app/manufacturers', ['manufacturers.create']],
            'locations.edit' => ['get', fn () => '/app/locations/'.Location::factory()->create()->id.'/edit', ['locations.edit']],
            'asset-models.destroy' => ['delete', fn () => '/app/asset-models/'.AssetModel::factory()->create()->id, ['models.delete']],
            'depreciations.create' => ['get', fn () => '/app/depreciations/create', ['depreciations.create']],
            'depreciations.update' => ['put', fn () => '/app/depreciations/'.Depreciation::factory()->create()->id, ['depreciations.edit']],
            'categories.index' => ['get', fn () => '/app/categories', ['categories.view']],
            'suppliers.destroy' => ['delete', fn () => '/app/suppliers/'.Supplier::factory()->create()->id, ['suppliers.delete']],
            'status-labels.index' => ['get', fn () => '/app/status-labels', ['statuslabels.view']],
            'status-labels.edit' => ['get', fn () => '/app/status-labels/'.StatusLabel::factory()->create()->id.'/edit', ['statuslabels.edit']],
            'custom-fields.store' => ['post', fn () => '/app/custom-fields', ['customfields.create']],
            'custom-fields.destroy' => ['delete', fn () => '/app/custom-fields/'.CustomField::factory()->create()->id, ['customfields.delete']],
            'custom-fieldsets.index' => ['get', fn () => '/app/custom-fieldsets', ['customfields.view']],
            'custom-fieldsets.edit' => ['get', fn () => '/app/custom-fieldsets/'.CustomFieldset::factory()->create()->id.'/edit', ['customfields.edit']],
            'accessories.index' => ['get', fn () => '/app/accessories', ['accessories.view']],
            'accessories.update' => ['put', fn () => '/app/accessories/'.Accessory::factory()->create()->id, ['accessories.edit']],
            'categories.destroy' => ['delete', fn () => '/app/categories/'.Category::factory()->create()->id, ['categories.delete']],
            'manufacturers.edit' => ['get', fn () => '/app/manufacturers/'.Manufacturer::factory()->create()->id.'/edit', ['manufacturers.edit']],

            // Every resource action for assets and users.
            'assets.index' => ['get', fn () => '/app/assets', ['assets.view']],
            'assets.show' => ['get', fn () => '/app/assets/'.$asset()->id, ['assets.view']],
            'assets.create' => ['get', fn () => '/app/assets/create', ['assets.create']],
            'assets.store' => ['post', fn () => '/app/assets', ['assets.create']],
            'assets.edit' => ['get', fn () => '/app/assets/'.$asset()->id.'/edit', ['assets.edit']],
            'assets.update' => ['put', fn () => '/app/assets/'.$asset()->id, ['assets.edit']],
            'assets.destroy' => ['delete', fn () => '/app/assets/'.$asset()->id, ['assets.delete']],
            'users.index' => ['get', fn () => '/app/users', ['users.view']],
            'users.show' => ['get', fn () => '/app/users/'.User::factory()->create()->id, ['users.view']],
            'users.create' => ['get', fn () => '/app/users/create', ['users.create']],
            'users.store' => ['post', fn () => '/app/users', ['users.create']],
            'users.edit' => ['get', fn () => '/app/users/'.User::factory()->create()->id.'/edit', ['users.edit']],
            'users.update' => ['put', fn () => '/app/users/'.User::factory()->create()->id, ['users.edit']],
            'users.destroy' => ['delete', fn () => '/app/users/'.User::factory()->create()->id, ['users.delete']],

            // Special mappings.
            'assets.checkout' => ['post', fn () => '/app/assets/'.$asset()->id.'/checkout', ['assets.checkout']],
            'assets.bulk-checkout' => ['post', fn () => '/app/assets/bulk-checkout', ['assets.checkout']],
            'assets.bulk-checkout.create' => ['get', fn () => '/app/assets/bulk-checkout', ['assets.checkout']],
            'assets.checkin' => ['post', fn () => '/app/assets/'.$asset()->id.'/checkin', ['assets.checkin']],
            'assets.attachments.store' => ['post', fn () => '/app/assets/'.$asset()->id.'/attachments', ['assets.files']],
            'assets.attachments.destroy' => ['delete', function (): string {
                $a = Attachment::factory()->create();

                return "/app/assets/{$a->attachable_id}/attachments/{$a->id}";
            }, ['assets.files']],
            'assets.incidents.store' => ['post', fn () => '/app/assets/'.$asset()->id.'/incidents', ['incidents.create']],
            'assets.incidents.update' => ['put', $incident, ['incidents.edit']],
            'assets.incidents.close' => ['post', fn () => $incident().'/close', ['incidents.edit']],
            'assets.incidents.reopen' => ['post', fn () => $incident().'/reopen', ['incidents.edit']],
            'assets.incidents.destroy' => ['delete', $incident, ['incidents.delete']],
            'assets.export' => ['get', fn () => '/app/assets/export', ['assets.view']],
            'scan.resolve' => ['get', fn () => '/app/scan/resolve?code=nothing', ['assets.view']],
            'attachments.open' => ['get', fn () => '/attachments/'.Attachment::factory()->create()->id.'/open', ['assets.view']],
            'assets.import' => ['post', fn () => '/app/assets/import', ['import', 'assets.create']],
            'qr-generator.index' => ['get', fn () => '/app/qr-generator', ['assets.create']],
            'qr-generator.codes' => ['get', fn () => '/app/qr-generator/codes', ['assets.create']],
            'users.quick-store' => ['post', fn () => '/app/users/quick', ['users.create']],
            'users.send-reset' => ['post', fn () => '/app/users/'.User::factory()->create()->id.'/send-reset', ['users.edit']],
            'handovers.index' => ['get', fn () => '/app/handovers', ['handovers.view']],
            'handovers.show' => ['get', fn () => '/app/handovers/'.Handover::factory()->create()->id, ['handovers.view']],
            'handovers.create' => ['get', fn () => '/app/handovers/create', ['handovers.create', 'assets.checkout']],
            'handovers.store' => ['post', fn () => '/app/handovers', ['handovers.create']],
            'reports.index' => ['get', fn () => '/app/reports', ['reports.view']],
            'reports.show' => ['get', fn () => '/app/reports/asset_aging', ['reports.view']],
            'settings.index' => ['get', fn () => '/app/settings', ['superuser']],
            'settings.general.update' => ['put', fn () => '/app/settings/general', ['superuser']],
            'places redirect' => ['get', fn () => '/app/places', ['locations.view']],
            'people redirect' => ['get', fn () => '/app/people', ['users.view']],
        ];
    }

    /** @param array<int, string> $permissions */
    #[DataProvider('routes')]
    public function test_route_requires_its_permission(string $method, Closure $uri, array $permissions): void
    {
        Storage::fake();
        Mail::fake();
        Queue::fake();
        $url = $uri();

        $without = User::factory()->create(['login_enabled' => true]);
        $this->actingAs($without)->json($method, $url)->assertForbidden();

        $with = User::factory()->withPermissions(array_fill_keys($permissions, 1))->create(['login_enabled' => true]);
        $status = $this->actingAs($with)->json($method, $url)->getStatusCode();
        $this->assertNotSame(403, $status, "{$method} {$url} still 403 with ".implode(', ', $permissions));
        $this->assertLessThan(500, $status, "{$method} {$url} errored with ".implode(', ', $permissions));
    }

    public function test_import_needs_both_import_and_assets_create(): void
    {
        foreach (['import', 'assets.create'] as $only) {
            $user = User::factory()->withPermissions([$only => 1])->create(['login_enabled' => true]);
            $this->actingAs($user)->postJson('/app/assets/import')->assertForbidden();
        }
    }

    public function test_admin_is_not_enough_for_settings(): void
    {
        $admin = User::factory()->withPermissions(['admin' => 1])->create(['login_enabled' => true]);

        $this->actingAs($admin)->get('/app/settings')->assertForbidden();
    }

    public function test_handover_type_needs_the_matching_asset_permission(): void
    {
        $user = User::factory()->withPermissions(['handovers.create' => 1, 'assets.checkout' => 1])->create(['login_enabled' => true]);
        $recipient = User::factory()->create();
        $payload = fn (string $type, Asset $asset) => [
            'type' => $type, 'recipient_kind' => 'internal', 'recipient_user_id' => $recipient->id,
            'recipient_name' => 'Jane Recipient', 'asset_ids' => [$asset->id],
            'terms_text' => 'Terms apply.', 'signature_png' => self::PNG,
        ];

        $inUse = Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::IN_USE)->id]);
        $this->actingAs($user)->post('/app/handovers', $payload('return', $inUse))->assertForbidden();
        $this->actingAs($user)->post('/app/handovers', $payload('return_defect', $inUse))->assertForbidden();

        $new = Asset::factory()->create(['status_id' => StatusLabel::system(SystemStatus::NEW)->id, 'assignee_type' => null, 'assignee_id' => null]);
        $status = $this->actingAs($user)->post('/app/handovers', $payload('issue', $new))->getStatusCode();
        $this->assertNotSame(403, $status);
    }

    public function test_handover_wizard_only_offers_allowed_types(): void
    {
        $only = User::factory()->withPermissions(['handovers.create' => 1])->create(['login_enabled' => true]);
        $this->actingAs($only)->get('/app/handovers/create')->assertForbidden();

        $checkin = User::factory()->withPermissions(['handovers.create' => 1, 'assets.checkin' => 1])->create(['login_enabled' => true]);
        $this->actingAs($checkin)->get('/app/handovers/create')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('typeOptions', 2)
                ->where('typeOptions.0.value', 'return')
                ->where('typeOptions.1.value', 'return_defect')
                ->etc());
    }

    public function test_dashboard_is_open_but_hides_what_the_user_may_not_see(): void
    {
        $nobody = User::factory()->create(['login_enabled' => true]);

        $this->actingAs($nobody)->get('/app')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('dashboard')
                ->missing('stats')
                ->missing('warranty')
                ->missing('latestDocuments')
                ->missing('warrantyExpiring')
                ->missing('openIncidents'));

        $viewer = User::factory()->withPermissions(['assets.view' => 1])->create(['login_enabled' => true]);
        $this->actingAs($viewer)->get('/app')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('stats')
                ->has('warranty')
                ->has('latestDocuments')
                ->missing('openIncidents')
                ->etc());
    }

    public function test_asset_show_includes_incidents_only_with_incidents_view(): void
    {
        $asset = Incident::factory()->create()->asset;

        $viewer = User::factory()->withPermissions(['assets.view' => 1])->create(['login_enabled' => true]);
        $this->actingAs($viewer)->get("/app/assets/{$asset->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->missing('incidents')->etc());

        $both = User::factory()->withPermissions(['assets.view' => 1, 'incidents.view' => 1])->create(['login_enabled' => true]);
        $this->actingAs($both)->get("/app/assets/{$asset->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('incidents', 1)->etc());
    }

    public function test_incident_counts_are_hidden_without_incidents_view(): void
    {
        $asset = Incident::factory()->create()->asset;

        $viewer = User::factory()->withPermissions(['assets.view' => 1])->create(['login_enabled' => true]);
        $this->actingAs($viewer)->get('/app/assets')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('assets.data.0', fn (Assert $r) => $r->missing('incidents_count')->etc())->etc());
        $this->actingAs($viewer)->get("/app/assets/{$asset->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('asset', fn (Assert $a) => $a->missing('incidentsCount')->etc())->etc());

        $both = User::factory()->withPermissions(['assets.view' => 1, 'incidents.view' => 1])->create(['login_enabled' => true]);
        $this->actingAs($both)->get('/app/assets')
            ->assertInertia(fn (Assert $p) => $p->where('assets.data.0.incidents_count', 1)->etc());
        $this->actingAs($both)->get("/app/assets/{$asset->id}")
            ->assertInertia(fn (Assert $p) => $p->where('asset.incidentsCount', 1)->etc());
    }

    public function test_forbidden_inertia_requests_render_the_forbidden_page(): void
    {
        $user = User::factory()->withPermissions(['assets.view' => 1])->create(['login_enabled' => true]);

        $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())])
            ->get('/app/users')
            ->assertForbidden()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'errors/forbidden');
    }

    public function test_forbidden_plain_requests_keep_the_default_response(): void
    {
        $user = User::factory()->withPermissions(['assets.view' => 1])->create(['login_enabled' => true]);

        $this->actingAs($user)->get('/app/users')->assertForbidden();
    }
}
