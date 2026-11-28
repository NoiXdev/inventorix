<?php

namespace Tests\Feature\Auth;

use App\Enums\Permission;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadOnlyGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_read_only_group_can_view_every_section(): void
    {
        $granted = Group::where('name', 'Read only')->firstOrFail()->permissions;

        $missing = collect(Permission::cases())
            ->map(fn (Permission $p) => $p->value)
            ->filter(fn (string $p) => str_ends_with($p, '.view'))
            ->reject(fn (string $p) => ($granted[$p] ?? 0) === 1)
            ->values()
            ->all();

        $this->assertSame([], $missing);
    }

    public function test_the_read_only_group_cannot_change_anything(): void
    {
        $granted = Group::where('name', 'Read only')->firstOrFail()->permissions;

        $this->assertSame([], collect($granted)->keys()->reject(fn (string $p) => str_ends_with($p, '.view'))->values()->all());
    }
}
