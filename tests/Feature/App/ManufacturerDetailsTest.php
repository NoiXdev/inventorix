<?php

namespace Tests\Feature\App;

use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManufacturerDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['login_enabled' => true]);
        Storage::fake();
    }

    /** @return array<string, string> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Acme',
            'url' => 'https://acme.test',
            'support_url' => 'https://acme.test/support',
            'warranty_lookup_url' => 'https://acme.test/warranty',
            'support_phone' => '+49 30 123456',
            'support_email' => 'support@acme.test',
            'notes' => 'Reseller contract runs to 2027.',
        ], $overrides);
    }

    public function test_store_persists_every_detail_field(): void
    {
        $this->actingAs($this->user)
            ->post('/app/manufacturers', $this->validPayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('manufacturers', [
            'name' => 'Acme',
            'url' => 'https://acme.test',
            'support_url' => 'https://acme.test/support',
            'warranty_lookup_url' => 'https://acme.test/warranty',
            'support_phone' => '+49 30 123456',
            'support_email' => 'support@acme.test',
            'notes' => 'Reseller contract runs to 2027.',
        ]);
    }

    public function test_every_detail_field_is_optional(): void
    {
        $this->actingAs($this->user)
            ->post('/app/manufacturers', ['name' => 'Bare'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $m = Manufacturer::where('name', 'Bare')->firstOrFail();

        $this->assertNull($m->url);
        $this->assertNull($m->support_email);
        $this->assertNull($m->notes);
        $this->assertNull($m->image_path);
    }

    public function test_it_rejects_malformed_urls_and_emails(): void
    {
        $this->actingAs($this->user)->post('/app/manufacturers', $this->validPayload([
            'url' => 'not-a-url',
            'support_url' => 'also not',
            'warranty_lookup_url' => 'nope',
            'support_email' => 'not-an-email',
        ]))->assertSessionHasErrors(['url', 'support_url', 'warranty_lookup_url', 'support_email']);
    }

    public function test_update_changes_the_detail_fields(): void
    {
        $m = Manufacturer::factory()->create();

        $this->actingAs($this->user)
            ->put('/app/manufacturers/'.$m->id, $this->validPayload(['name' => 'Renamed']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $m->fresh()->name);
        $this->assertSame('support@acme.test', $m->fresh()->support_email);
    }

    public function test_it_stores_an_uploaded_logo_and_records_the_path(): void
    {
        $this->actingAs($this->user)->post('/app/manufacturers', $this->validPayload([
            'image' => UploadedFile::fake()->image('logo.png', 64, 64),
        ]))->assertSessionHasNoErrors();

        $m = Manufacturer::where('name', 'Acme')->firstOrFail();

        $this->assertNotNull($m->image_path);
        Storage::assertExists($m->image_path);
    }

    public function test_image_path_cannot_be_set_through_the_form(): void
    {
        // image_path holds a storage path; accepting it from input would let a
        // client point the record at an arbitrary file.
        $this->actingAs($this->user)->post('/app/manufacturers', $this->validPayload([
            'image_path' => 'some/other/file.png',
        ]))->assertSessionHasNoErrors();

        $this->assertNull(Manufacturer::where('name', 'Acme')->firstOrFail()->image_path);
    }

    public function test_it_rejects_a_non_image_upload(): void
    {
        $this->actingAs($this->user)->post('/app/manufacturers', $this->validPayload([
            'image' => UploadedFile::fake()->create('payload.pdf', 10, 'application/pdf'),
        ]))->assertSessionHasErrors('image');
    }

    public function test_it_rejects_an_svg_upload(): void
    {
        // SVG is served inline and can carry script — stored XSS via the logo.
        $this->actingAs($this->user)->post('/app/manufacturers', $this->validPayload([
            'image' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
        ]))->assertSessionHasErrors('image');
    }

    public function test_it_rejects_an_oversized_image(): void
    {
        $this->actingAs($this->user)->post('/app/manufacturers', $this->validPayload([
            'image' => UploadedFile::fake()->image('huge.png')->size(3000),
        ]))->assertSessionHasErrors('image');
    }

    public function test_replacing_the_logo_deletes_the_previous_file(): void
    {
        $this->actingAs($this->user)->post('/app/manufacturers', $this->validPayload([
            'image' => UploadedFile::fake()->image('first.png'),
        ]));

        $m = Manufacturer::where('name', 'Acme')->firstOrFail();
        $first = $m->image_path;

        $this->actingAs($this->user)->put('/app/manufacturers/'.$m->id, $this->validPayload([
            'image' => UploadedFile::fake()->image('second.png'),
        ]));

        $second = $m->fresh()->image_path;

        $this->assertNotSame($first, $second);
        Storage::assertMissing($first);
        Storage::assertExists($second);
    }

    public function test_remove_image_clears_the_field_and_deletes_the_file(): void
    {
        $this->actingAs($this->user)->post('/app/manufacturers', $this->validPayload([
            'image' => UploadedFile::fake()->image('logo.png'),
        ]));

        $m = Manufacturer::where('name', 'Acme')->firstOrFail();
        $path = $m->image_path;

        $this->actingAs($this->user)->put('/app/manufacturers/'.$m->id, $this->validPayload([
            'remove_image' => true,
        ]));

        $this->assertNull($m->fresh()->image_path);
        Storage::assertMissing($path);
    }

    public function test_remove_image_accepts_the_string_flag_the_form_sends(): void
    {
        // The form serialises remove_image as '1'/'0' rather than a boolean so
        // the `boolean` rule matches regardless of Inertia's FormData encoding.
        $this->actingAs($this->user)->post('/app/manufacturers', $this->validPayload([
            'image' => UploadedFile::fake()->image('logo.png'),
        ]));

        $m = Manufacturer::where('name', 'Acme')->firstOrFail();

        $this->actingAs($this->user)->put('/app/manufacturers/'.$m->id, $this->validPayload([
            'remove_image' => '1',
        ]))->assertSessionHasNoErrors();

        $this->assertNull($m->fresh()->image_path);
    }

    public function test_the_zero_flag_leaves_the_logo_alone(): void
    {
        $this->actingAs($this->user)->post('/app/manufacturers', $this->validPayload([
            'image' => UploadedFile::fake()->image('logo.png'),
        ]));

        $m = Manufacturer::where('name', 'Acme')->firstOrFail();
        $path = $m->image_path;

        $this->actingAs($this->user)->put('/app/manufacturers/'.$m->id, $this->validPayload([
            'remove_image' => '0',
        ]))->assertSessionHasNoErrors();

        $this->assertSame($path, $m->fresh()->image_path);
        Storage::assertExists($path);
    }

    public function test_an_update_without_a_new_file_keeps_the_existing_logo(): void
    {
        $this->actingAs($this->user)->post('/app/manufacturers', $this->validPayload([
            'image' => UploadedFile::fake()->image('logo.png'),
        ]));

        $m = Manufacturer::where('name', 'Acme')->firstOrFail();
        $path = $m->image_path;

        $this->actingAs($this->user)->put('/app/manufacturers/'.$m->id, $this->validPayload(['name' => 'Acme 2']));

        $this->assertSame($path, $m->fresh()->image_path);
        Storage::assertExists($path);
    }

    public function test_the_image_route_streams_the_logo(): void
    {
        $this->actingAs($this->user)->post('/app/manufacturers', $this->validPayload([
            'image' => UploadedFile::fake()->image('logo.png'),
        ]));

        $m = Manufacturer::where('name', 'Acme')->firstOrFail();

        $this->actingAs($this->user)->get('/app/manufacturers/'.$m->id.'/image')->assertOk();
    }

    public function test_the_image_route_404s_without_a_logo(): void
    {
        $m = Manufacturer::factory()->create();

        $this->actingAs($this->user)->get('/app/manufacturers/'.$m->id.'/image')->assertNotFound();
    }

    public function test_the_image_route_requires_authentication(): void
    {
        $m = Manufacturer::factory()->create();

        $this->get('/app/manufacturers/'.$m->id.'/image')->assertRedirect();
    }

    public function test_deleting_a_manufacturer_keeps_its_logo_file(): void
    {
        // Manufacturers are soft-deleted and can be restored, so the file must
        // outlive the row.
        $this->actingAs($this->user)->post('/app/manufacturers', $this->validPayload([
            'image' => UploadedFile::fake()->image('logo.png'),
        ]));

        $m = Manufacturer::where('name', 'Acme')->firstOrFail();
        $path = $m->image_path;

        $this->actingAs($this->user)->delete('/app/manufacturers/'.$m->id);

        Storage::assertExists($path);
    }

    public function test_edit_exposes_the_detail_fields_and_the_image_url(): void
    {
        $this->actingAs($this->user)->post('/app/manufacturers', $this->validPayload([
            'image' => UploadedFile::fake()->image('logo.png'),
        ]));

        $m = Manufacturer::where('name', 'Acme')->firstOrFail();

        $this->actingAs($this->user)->get('/app/manufacturers/'.$m->id.'/edit')
            ->assertInertia(fn ($page) => $page
                ->component('manufacturers/edit')
                ->where('manufacturer.support_email', 'support@acme.test')
                ->where('manufacturer.notes', 'Reseller contract runs to 2027.')
                ->where('manufacturer.image_url', '/app/manufacturers/'.$m->id.'/image')
                ->etc());
    }

    public function test_index_exposes_the_image_url_only_when_a_logo_exists(): void
    {
        $withLogo = Manufacturer::factory()->create(['name' => 'Withlogo']);
        $withLogo->forceFill(['image_path' => 'manufacturers/x.png'])->save();
        Manufacturer::factory()->create(['name' => 'Nologo']);

        $this->actingAs($this->user)->get('/app/manufacturers')
            ->assertInertia(fn ($page) => $page
                ->component('manufacturers/index')
                ->where('manufacturers.data', fn ($rows) => collect($rows)->firstWhere('name', 'Withlogo')['image_url'] !== null
                    && collect($rows)->firstWhere('name', 'Nologo')['image_url'] === null)
                ->etc());
    }
}
