<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaSmokeTest extends TestCase
{
    public function test_renders_the_inertia_smoke_page_at_app(): void
    {
        $this->get('/app')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('smoke'));
    }
}
