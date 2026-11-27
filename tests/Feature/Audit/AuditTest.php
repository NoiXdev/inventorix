<?php

namespace Tests\Feature\Audit;

use App\Enums\SystemStatus;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Location;
use App\Models\StatusLabel;
use App\Models\User;
use App\Settings\AlertSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-28 10:00:00');
    }

    private function auditor(): User
    {
        return User::factory()->withPermissions(['assets.view' => 1, 'assets.audit' => 1])->create();
    }

    private function interval(int $months): void
    {
        $settings = app(AlertSettings::class);
        $settings->audit_interval_months = $months;
        $settings->save();
    }

    public function test_audit_sets_dates_moves_the_location_and_records_the_note(): void
    {
        $asset = Asset::factory()->create();
        $room = Location::factory()->create();

        $this->actingAs($this->auditor())->post("/app/assets/{$asset->id}/audit", [
            'location_id' => $room->id, 'next_audit_date' => '2027-06-01', 'note' => 'Under the desk',
        ])->assertRedirect()->assertSessionHas('success');

        $asset->refresh();
        $this->assertSame('2026-11-28 10:00:00', $asset->last_audit_date->toDateTimeString());
        $this->assertSame('2027-06-01', $asset->next_audit_date->toDateString());
        $this->assertSame($room->id, $asset->location_id);

        $entry = Activity::query()->where('subject_id', $asset->id)->where('event', 'audited')->sole();
        $this->assertSame('Under the desk', $entry->getProperty('note'));
    }

    public function test_an_empty_location_keeps_it_and_the_next_audit_defaults_to_the_interval(): void
    {
        $this->interval(6);
        $asset = Asset::factory()->create();
        $location = $asset->location_id;

        $this->actingAs($this->auditor())->post("/app/assets/{$asset->id}/audit", [
            'location_id' => '', 'next_audit_date' => '', 'note' => '',
        ])->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertSame($location, $asset->location_id);
        $this->assertSame('2027-05-28', $asset->next_audit_date->toDateString());
    }

    public function test_without_an_interval_the_next_audit_is_left_empty(): void
    {
        $this->interval(0);
        $asset = Asset::factory()->create(['next_audit_date' => '2026-12-01']);

        $this->actingAs($this->auditor())->post("/app/assets/{$asset->id}/audit", [])->assertSessionHasNoErrors();

        $this->assertNull($asset->refresh()->next_audit_date);
    }

    public function test_validation_and_permission(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->auditor())->post("/app/assets/{$asset->id}/audit", [
            'next_audit_date' => '2026-11-01', 'location_id' => 'nope',
        ])->assertSessionHasErrors(['next_audit_date', 'location_id']);

        $viewer = User::factory()->withPermissions(['assets.view' => 1])->create();
        $this->actingAs($viewer)->post("/app/assets/{$asset->id}/audit", [])->assertForbidden();
        $this->actingAs($viewer)->get('/app/assets/audit')->assertForbidden();
        $this->actingAs($viewer)->post('/app/assets/audit/scan', ['code' => 'x'])->assertForbidden();
        $this->assertNull($asset->refresh()->last_audit_date);
    }

    public function test_scan_audits_by_tag_or_serial(): void
    {
        $this->interval(12);
        $byTag = Asset::factory()->create(['asset_tag' => 'INV-001']);
        $bySerial = Asset::factory()->create(['serial' => 'SN-777']);
        $room = Location::factory()->create(['name' => 'Lager']);

        $this->actingAs($this->auditor())->postJson('/app/assets/audit/scan', ['code' => ' INV-001 ', 'location_id' => $room->id])
            ->assertOk()->assertJson(['id' => $byTag->id, 'next_audit_date' => '2027-11-28', 'location' => 'Lager']);
        $this->actingAs($this->auditor())->postJson('/app/assets/audit/scan', ['code' => 'SN-777'])
            ->assertOk()->assertJson(['id' => $bySerial->id]);

        $this->assertNotNull($byTag->refresh()->last_audit_date);
        $this->assertSame($room->id, $byTag->location_id);
        $this->assertNotNull($bySerial->refresh()->last_audit_date);
    }

    public function test_scan_refuses_unknown_and_ambiguous_codes(): void
    {
        Asset::factory()->create(['asset_tag' => 'DUP']);
        Asset::factory()->create(['serial' => 'DUP']);

        $this->actingAs($this->auditor())->postJson('/app/assets/audit/scan', ['code' => 'NOPE'])
            ->assertStatus(422)->assertJson(['message' => 'No asset with the tag or serial “NOPE”.']);
        $this->actingAs($this->auditor())->postJson('/app/assets/audit/scan', ['code' => 'DUP'])
            ->assertStatus(422)->assertJsonPath('message', fn (string $m) => str_starts_with($m, 'Several assets'));
        $this->assertSame(0, Asset::query()->whereNotNull('last_audit_date')->count());
    }

    public function test_scan_page_proposes_the_next_audit(): void
    {
        $this->interval(12);

        $this->actingAs($this->auditor())->get('/app/assets/audit')
            ->assertInertia(fn (Assert $p) => $p->component('assets/audit-scan')
                ->where('nextAuditDate', '2027-11-28')->has('locationOptions'));
    }

    public function test_a_new_asset_is_first_due_for_audit_one_interval_from_now(): void
    {
        $this->interval(12);
        $creator = User::factory()->superuser()->create();
        $model = AssetModel::factory()->create();

        $this->actingAs($creator)->post('/app/assets', [
            'name' => 'Fresh laptop', 'model_id' => $model->id,
            'status_id' => StatusLabel::system(SystemStatus::NEW)->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('2027-11-28', Asset::query()->where('name', 'Fresh laptop')->sole()->next_audit_date->toDateString());
    }
}
