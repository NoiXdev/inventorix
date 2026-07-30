<?php

namespace Tests\Feature\App;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RootRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_app(): void
    {
        $this->get('/')->assertRedirect('/app');
    }
}
