<?php

namespace Tests\Feature\Licenses;

use App\Exceptions\LicenseSeatException;
use App\Models\Asset;
use App\Models\License;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class LicenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): LicenseService
    {
        return app(LicenseService::class);
    }

    public function test_seats_are_added_and_free_ones_removed(): void
    {
        $license = License::factory()->seats(3)->create();
        $this->assertSame(3, $license->licenseSeats()->count());

        $this->service()->syncSeats($license, 5);
        $this->assertSame(5, $license->refresh()->seats);
        $this->assertSame(5, $license->licenseSeats()->count());

        $this->service()->checkOut($license, User::factory()->create());
        $this->service()->syncSeats($license, 1);
        $this->assertSame(1, $license->licenseSeats()->count());
        $this->assertSame(0, $license->freeSeats()->count());
    }

    public function test_seats_cannot_drop_below_the_checked_out_ones(): void
    {
        $license = License::factory()->seats(2)->create();
        $this->service()->checkOut($license, User::factory()->create());
        $this->service()->checkOut($license, User::factory()->create());

        $this->expectException(LicenseSeatException::class);
        $this->service()->syncSeats($license, 1);
    }

    public function test_check_out_to_a_user_and_an_asset_until_none_is_free(): void
    {
        $license = License::factory()->seats(2)->create();
        $user = User::factory()->create(['name' => 'Ada']);
        $asset = Asset::factory()->create(['name' => 'Build server']);

        $a = $this->service()->checkOut($license, $user, 'for the project');
        $b = $this->service()->checkOut($license, $asset);

        $this->assertSame($user->id, $a->assigned_user_id);
        $this->assertSame($asset->id, $b->assigned_asset_id);
        $this->assertNotSame($a->id, $b->id);
        $this->assertNotNull($a->checked_out_at);

        $entry = Activity::query()->where('subject_id', $license->id)->where('event', 'checked_out')->oldest('id')->first();
        $this->assertSame('User: Ada', $entry->getProperty('target'));
        $this->assertSame('for the project', $entry->getProperty('note'));

        $this->expectExceptionMessage('All seats of this license are checked out.');
        $this->service()->checkOut($license, User::factory()->create());
    }

    public function test_check_in_frees_the_seat_or_uses_it_up(): void
    {
        $reassignable = License::factory()->seats(1)->create(['reassignable' => true]);
        $seat = $this->service()->checkOut($reassignable, User::factory()->create());
        $this->service()->checkIn($seat, 'returned');
        $this->assertFalse($seat->refresh()->isCheckedOut());
        $this->assertFalse($seat->retired);
        $this->assertSame(1, $reassignable->freeSeats()->count());
        $this->assertSame('returned', Activity::query()->where('subject_id', $reassignable->id)->where('event', 'checked_in')->sole()->getProperty('note'));

        $oneWay = License::factory()->seats(1)->create(['reassignable' => false]);
        $seat = $this->service()->checkOut($oneWay, User::factory()->create());
        $this->service()->checkIn($seat);
        $this->assertTrue($seat->refresh()->retired);
        $this->assertSame(0, $oneWay->freeSeats()->count());

        $this->expectException(LicenseSeatException::class);
        $this->service()->checkIn($seat);
    }

    public function test_the_product_key_is_encrypted_and_never_in_the_history(): void
    {
        $license = License::factory()->create(['product_key' => 'AAAAA-BBBBB']);

        $this->assertNotSame('AAAAA-BBBBB', \DB::table('licenses')->where('id', $license->id)->value('product_key'));
        $this->assertSame('AAAAA-BBBBB', $license->refresh()->product_key);
        $this->assertStringNotContainsString('AAAAA', json_encode(Activity::query()->where('subject_id', $license->id)->get()));
    }
}
