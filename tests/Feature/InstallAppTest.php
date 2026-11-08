<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_pages_are_installable_and_offer_install_above_the_version(): void
    {
        foreach (['end_user', 'expert', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $html = $this->actingAs($user)->get(route('profile.show'))->assertOk()->getContent();

            $this->assertStringContainsString('<link rel="manifest" href="/manifest.json">', $html, $role);
            // Hidden until the browser says the app can be installed (or on iPhone / iPad).
            $this->assertStringContainsString('id="installAppSection" hidden', $html, $role);
            $this->assertStringContainsString('Install on your iPhone or iPad', $html, $role);

            // At the bottom of the sidebar: after the last section (Support), right before the version line.
            $sidebar = substr($html, strpos($html, '<aside class="sidebar">'));
            $install = strpos($sidebar, 'id="installAppSection"');
            $this->assertGreaterThan(strrpos(substr($sidebar, 0, $install), 'sidebar-title') ?: -1, $install, $role);
            $this->assertGreaterThan(strpos($sidebar, 'Help &amp; Guide'), $install, "{$role}: below the Support section");
            $this->assertLessThan(strpos($sidebar, 'class="sidebar-version"'), $install, "{$role}: above the version");
            $this->assertStringNotContainsString('sidebar-title', substr($sidebar, $install, strpos($sidebar, 'class="sidebar-version"') - $install), $role);
        }
    }
}
