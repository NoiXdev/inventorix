<?php

namespace Tests\Feature\Auth;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every route behind `auth` must carry a `can:` middleware, apart from the few that are
 * deliberately open to any (or no) logged-in user.
 */
class RouteCoverageTest extends TestCase
{
    private const EXEMPT = [
        'app.logout',
        'app.dashboard',
    ];

    public function test_every_app_route_has_a_permission_check(): void
    {
        $uncovered = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => $this->inScope($route))
            ->reject(fn (RoutingRoute $route) => collect($route->gatherMiddleware())
                ->contains(fn ($m) => is_string($m) && str_starts_with($m, 'can:')))
            ->map(fn (RoutingRoute $route) => ($route->getName() ?? '(unnamed)').' ['.$route->uri().']')
            ->values()
            ->all();

        $this->assertSame([], $uncovered, "Routes without a can: middleware:\n".implode("\n", $uncovered));
    }

    private function inScope(RoutingRoute $route): bool
    {
        if (! in_array('auth', $route->gatherMiddleware(), true)) {
            return false;
        }

        $name = (string) $route->getName();

        return ! in_array($name, self::EXEMPT, true) && ! Str::endsWith($name, '.image');
    }
}
