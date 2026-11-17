<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PullToRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_layout_has_pull_to_refresh_in_the_body(): void
    {
        foreach (['end_user' => 'dashboard', 'expert' => 'expert.dashboard', 'admin' => 'admin.dashboard'] as $role => $page) {
            $user = User::factory()->create(['role' => $role]);

            $html = $this->actingAs($user)->get(route($page))->assertOk()->getContent();

            $this->assertStringContainsString('id="ptrIndicator"', $html);
            // The indicator is an element, so it must come after <body> (not inside <head>).
            $this->assertGreaterThan(strpos($html, '<body'), strpos($html, 'id="ptrIndicator"'));
        }
    }
}
